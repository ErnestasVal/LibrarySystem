<?php
// Database configuration - read env vars from project setup (.env supported via vendor/autoload)
// Composer autoload is expected at project_root/vendor/autoload.php
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
    if (class_exists('Dotenv\\Dotenv')) {
        try {
            Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
        } catch (Exception $e) {
            // ignore
        }
    }
}

// Helper to read env with default
function cfg_env(string $name, $default) {
    $v = getenv($name);
    if ($v !== false && $v !== null) return $v;
    if (isset($_ENV[$name]) && $_ENV[$name] !== '') return $_ENV[$name];
    return $default;
}

define('DB_HOST', cfg_env('DB_HOST', 'localhost'));
define('DB_NAME', cfg_env('DB_NAME', 'biblioteka'));
define('DB_USER', cfg_env('DB_USER', 'stud'));
define('DB_PASS', cfg_env('DB_PASS', 'stud'));

// User types
define('USER_GUEST', 'guest');
define('USER_CLIENT', 'Klientas');
define('USER_LIBRARIAN', 'Bibliotekininkas');
define('USER_ADMIN', 'Administratorius');

// Start session
session_start();

// Set default user mode if not set
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['loggedin'] = false;
    $_SESSION['user_type'] = USER_GUEST;
    $_SESSION['user_id'] = null;
    $_SESSION['username'] = null;
}

// Database connection - use __DIR__ so includes work regardless of include_path or caller CWD
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/User.php';
$database = new Database();
$db = $database->getConnection();
$user = new User($db);

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    session_start();
    $_SESSION['loggedin'] = false;
    $_SESSION['user_type'] = USER_GUEST;
    $_SESSION['user_id'] = null;
    $_SESSION['username'] = null;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle librarian registration by admin
if (isset($_POST['register_librarian']) && $_SESSION['loggedin'] && $_SESSION['user_type'] == USER_ADMIN) {
    $user->vardas = $_POST['vardas'];
    $user->pavarde = $_POST['pavarde'];
    $user->username = $_POST['username'];
    $user->password = $_POST['password'];
    $user->tipas = USER_LIBRARIAN;
    
    if($user->usernameExists()) {
        $librarian_error = "Toks vartotojo vardas jau egzistuoja.";
    } else {
        if($user->register()) {
            $librarian_success = "Bibliotekininko paskyra sėkmingai sukurta!";
        } else {
            $librarian_error = "Bibliotekininko registracija nepavyko. Bandykite dar kartą.";
        }
    }
}

// Handle user deletion by admin
if (isset($_POST['delete_user']) && $_SESSION['loggedin'] && $_SESSION['user_type'] == USER_ADMIN) {
    $user_id = $_POST['user_id'];
    
    if ($user_id != $_SESSION['user_id']) {
        // Check if user has active reservations
        $check_query = "SELECT COUNT(*) as reservation_count FROM rezervacija WHERE fk_vartotojasid = ? AND ar_grazinta = FALSE";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$user_id]);
        $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['reservation_count'] > 0) {
            $user_management_error = "Negalima ištrinti vartotojo, nes jis turi aktyvių rezervacijų.";
        } else {
            // Delete user's returned reservations first
            $delete_reservations_query = "DELETE FROM rezervacija WHERE fk_vartotojasid = ? AND ar_grazinta = TRUE";
            $delete_reservations_stmt = $db->prepare($delete_reservations_query);
            $delete_reservations_stmt->execute([$user_id]);
            
            // Delete user
            $delete_query = "DELETE FROM vartotojas WHERE id = ? AND tipas != 'Administratorius'";
            $delete_stmt = $db->prepare($delete_query);
            if ($delete_stmt->execute([$user_id])) {
                $user_management_success = "Vartotojas sėkmingai ištrintas.";
            } else {
                $user_management_error = "Nepavyko ištrinti vartotojo.";
            }
        }
    } else {
        $user_management_error = "Negalite ištrinti savo paskyros.";
    }
}

// Handle book return by librarian
if (isset($_POST['mark_returned']) && $_SESSION['loggedin'] && $_SESSION['user_type'] == USER_LIBRARIAN) {
    $reservation_id = $_POST['reservation_id'];
    $query = "UPDATE rezervacija SET ar_grazinta = TRUE, grazinimo_data = CURDATE() WHERE id = ?";
    $stmt = $db->prepare($query);
    if ($stmt->execute([$reservation_id])) {
        $return_success = "Knyga pažymėta kaip grąžinta.";
    } else {
        $return_error = "Nepavyko pažymėti knygos kaip grąžintos.";
    }
}

// Handle book deletion with reservation checks
if (isset($_POST['delete_book']) && $_SESSION['loggedin'] && ($_SESSION['user_type'] == USER_LIBRARIAN || $_SESSION['user_type'] == USER_ADMIN)) {
    $book_id = $_POST['book_id'];
    
    // Check if book has active reservations
    $check_query = "SELECT COUNT(*) as active_reservations FROM rezervacija WHERE fk_knygaid = ? AND ar_grazinta = FALSE";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$book_id]);
    $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['active_reservations'] > 0) {
        $book_error = "Negalima ištrinti knygos, nes ji turi aktyvių rezervacijų.";
    } else {
        // Delete returned reservations for this book
        $delete_reservations_query = "DELETE FROM rezervacija WHERE fk_knygaid = ? AND ar_grazinta = TRUE";
        $delete_reservations_stmt = $db->prepare($delete_reservations_query);
        $delete_reservations_stmt->execute([$book_id]);
        
        // Delete the book
        $delete_query = "DELETE FROM knyga WHERE id = ?";
        $delete_stmt = $db->prepare($delete_query);
        if ($delete_stmt->execute([$book_id])) {
            $book_success = "Knyga sėkmingai ištrinta.";
        } else {
            $book_error = "Nepavyko ištrinti knygos.";
        }
    }
}
// Check if user has late reservations
function hasLateReservations($db, $user_id) {
    $query = "SELECT COUNT(*) as late_count 
              FROM rezervacija 
              WHERE fk_vartotojasid = ? 
              AND ar_grazinta = FALSE 
              AND grazinimo_data < CURDATE()";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['late_count'] > 0;
}

// Handle reservation confirmation with late reservation check
if ($_SESSION['loggedin'] && $_SESSION['user_type'] == USER_CLIENT && isset($_POST['confirm_reservation'])) {
    $book_id = $_POST['book_id'];
    
    // Check if user has late reservations
    if (hasLateReservations($db, $_SESSION['user_id'])) {
        $reservation_error = "Negalite rezervuoti naujų knygų, nes turite pavėluotų grąžinti knygų. Prašome grąžinti visas pavėluotas knygas prieš rezervuodami naujas.";
    } else {
        // Check if book is still available
        $availability_query = "SELECT (egzemplioriu_sk - 
                              (SELECT COUNT(*) FROM rezervacija WHERE fk_knygaid = ? AND ar_grazinta = FALSE)) 
                              as available_copies 
                              FROM knyga WHERE id = ?";
        $availability_stmt = $db->prepare($availability_query);
        $availability_stmt->execute([$book_id, $book_id]);
        $availability = $availability_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($availability['available_copies'] > 0) {
            // Calculate return date
            $book_query = "SELECT isdavimo_laikas FROM knyga WHERE id = ?";
            $book_stmt = $db->prepare($book_query);
            $book_stmt->execute([$book_id]);
            $book_data = $book_stmt->fetch(PDO::FETCH_ASSOC);
            
            $return_date = date('Y-m-d', strtotime("+" . $book_data['isdavimo_laikas'] . " days"));
            
            // Create reservation
            $query = "INSERT INTO rezervacija (issiemimo_data, grazinimo_data, ar_grazinta, fk_vartotojasid, fk_knygaid) 
                      VALUES (CURDATE(), ?, FALSE, ?, ?)";
            $stmt = $db->prepare($query);
            if ($stmt->execute([$return_date, $_SESSION['user_id'], $book_id])) {
                $reservation_success = "Knyga sėkmingai rezervuota! Grąžinimo data: " . $return_date;
            } else {
                $reservation_error = "Nepavyko rezervuoti knygos.";
            }
        } else {
            $reservation_error = "Atsiprašome, ši knyga daugiau nėra laisva.";
        }
    }
}

// Fetch client's reservations
if ($_SESSION['loggedin'] && $_SESSION['user_type'] == USER_CLIENT) {
    $client_reservations_query = "SELECT r.*, k.pavadinimas, k.autorius, k.isdavimo_laikas
                                  FROM rezervacija r 
                                  JOIN knyga k ON r.fk_knygaid = k.id 
                                  WHERE r.fk_vartotojasid = ? 
                                  ORDER BY r.ar_grazinta, r.grazinimo_data";
    $client_reservations_stmt = $db->prepare($client_reservations_query);
    $client_reservations_stmt->execute([$_SESSION['user_id']]);
    $client_reservations = $client_reservations_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if user has late reservations
    $has_late_reservations = hasLateReservations($db, $_SESSION['user_id']);
}
// Handle login
if (isset($_POST['login'])) {
    $user->username = $_POST['username'];
    $password = $_POST['password'];
    
    if($user->usernameExists()) {
        if($user->verifyPassword($password)) {
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user->id;
            $_SESSION['username'] = $user->username;
            $_SESSION['user_type'] = $user->tipas;
            
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            // Generic error message - don't reveal whether username exists
            $login_error = "Neteisingas vartotojo vardas arba slaptažodis.";
        }
    } else {
        // Same generic error message for both cases
        $login_error = "Neteisingas vartotojo vardas arba slaptažodis.";
    }
}

// Handle registration
if (isset($_POST['register'])) {
    $user->vardas = $_POST['vardas'];
    $user->pavarde = $_POST['pavarde'];
    $user->username = $_POST['username'];
    $user->password = $_POST['password'];
    $user->tipas = USER_CLIENT;
    
    if($user->usernameExists()) {
        $register_error = "Toks vartotojo vardas jau egzistuoja.";
    } else {
        if($user->register()) {
            $register_success = "Registracija sėkminga! Galite prisijungti.";
        } else {
            $register_error = "Registracija nepavyko. Bandykite dar kartą.";
        }
    }
}
?>