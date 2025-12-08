<?php
// Load configuration from the `src/` folder. Project layout is fixed:
// project_root/
//   public/ (this file)
//   src/
//   vendor/
require_once __DIR__ . '/../src/config.php';

// Handle book operations for librarians and admins
if ($_SESSION['loggedin'] && ($_SESSION['user_type'] == USER_LIBRARIAN || $_SESSION['user_type'] == USER_ADMIN)) {
    // Add new book
    if (isset($_POST['add_book'])) {
        // Validate input values
        $puslapiu_sk = intval($_POST['puslapiu_sk']);
        $egzemplioriu_sk = intval($_POST['egzemplioriu_sk']);
        $isdavimo_laikas = intval($_POST['isdavimo_laikas']);
        
        $validation_errors = [];
        
        if ($puslapiu_sk <= 0) {
            $validation_errors[] = "Puslapių skaičius turi būti teigiamas skaičius.";
        }
        if ($egzemplioriu_sk <= 0) {
            $validation_errors[] = "Egzempliorių skaičius turi būti teigiamas skaičius.";
        }
        if ($isdavimo_laikas <= 0) {
            $validation_errors[] = "Išdavimo laikas turi būti teigiamas skaičius.";
        }
        
        if (empty($validation_errors)) {
            $query = "INSERT INTO knyga (pavadinimas, autorius, puslapiu_sk, egzemplioriu_sk, isdavimo_laikas) 
                      VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            if ($stmt->execute([
                $_POST['pavadinimas'],
                $_POST['autorius'],
                $puslapiu_sk,
                $egzemplioriu_sk,
                $isdavimo_laikas
            ])) {
                $book_success = "Knyga sėkmingai pridėta.";
            } else {
                $book_error = "Nepavyko pridėti knygos.";
            }
        } else {
            $book_error = implode(" ", $validation_errors);
        }
    }

    // Edit book
    if (isset($_POST['edit_book'])) {
        $book_id = $_POST['book_id'];
        $puslapiu_sk = intval($_POST['puslapiu_sk']);
        $egzemplioriu_sk = intval($_POST['egzemplioriu_sk']);
        $isdavimo_laikas = intval($_POST['isdavimo_laikas']);
        
        $validation_errors = [];
        
        // Get current book data to check available copies
        $current_book_query = "SELECT k.egzemplioriu_sk, 
                              COUNT(CASE WHEN r.ar_grazinta = FALSE THEN r.id END) as active_reservations
                              FROM knyga k 
                              LEFT JOIN rezervacija r ON k.id = r.fk_knygaid 
                              WHERE k.id = ? 
                              GROUP BY k.id";
        $current_book_stmt = $db->prepare($current_book_query);
        $current_book_stmt->execute([$book_id]);
        $current_book = $current_book_stmt->fetch(PDO::FETCH_ASSOC);
        
        $current_active_reservations = $current_book ? $current_book['active_reservations'] : 0;
        
        if ($puslapiu_sk <= 0) {
            $validation_errors[] = "Puslapių skaičius turi būti teigiamas skaičius.";
        }
        if ($egzemplioriu_sk <= 0) {
            $validation_errors[] = "Egzempliorių skaičius turi būti teigiamas skaičius.";
        }
        if ($isdavimo_laikas <= 0) {
            $validation_errors[] = "Išdavimo laikas turi būti teigiamas skaičius.";
        }
        if ($egzemplioriu_sk < $current_active_reservations) {
            $validation_errors[] = "Negalima nustatyti mažesnio egzempliorių skaičiaus nei aktyvių rezervacijų ($current_active_reservations).";
        }
        
        if (empty($validation_errors)) {
            $query = "UPDATE knyga SET pavadinimas=?, autorius=?, puslapiu_sk=?, egzemplioriu_sk=?, isdavimo_laikas=? 
                      WHERE id=?";
            $stmt = $db->prepare($query);
            if ($stmt->execute([
                $_POST['pavadinimas'],
                $_POST['autorius'],
                $puslapiu_sk,
                $egzemplioriu_sk,
                $isdavimo_laikas,
                $book_id
            ])) {
                $book_success = "Knyga sėkmingai atnaujinta.";
            } else {
                $book_error = "Nepavyko atnaujinti knygos.";
            }
        } else {
            $book_error = implode(" ", $validation_errors);
        }
    }
}

// Handle book filter
$book_filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filter_condition = "";
if ($book_filter === 'available') {
    $filter_condition = "HAVING available_copies > 0";
}

// Fetch books with available copies count and filtering
$query = "SELECT k.*, 
          (k.egzemplioriu_sk - COUNT(CASE WHEN r.ar_grazinta = FALSE THEN r.id END)) as available_copies,
          COUNT(CASE WHEN r.ar_grazinta = FALSE THEN r.id END) as active_reservations
          FROM knyga k 
          LEFT JOIN rezervacija r ON k.id = r.fk_knygaid
          GROUP BY k.id 
          $filter_condition
          ORDER BY k.pavadinimas";
$stmt = $db->prepare($query);
$stmt->execute();
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active reservations for librarians (only non-returned books)
if ($_SESSION['loggedin'] && $_SESSION['user_type'] == USER_LIBRARIAN) {
    $reservations_query = "SELECT r.*, k.pavadinimas, k.autorius, v.vardas, v.pavarde, v.username 
                           FROM rezervacija r 
                           JOIN knyga k ON r.fk_knygaid = k.id 
                           JOIN vartotojas v ON r.fk_vartotojasid = v.id 
                           WHERE r.ar_grazinta = FALSE 
                           ORDER BY r.issiemimo_data";
    $reservations_stmt = $db->prepare($reservations_query);
    $reservations_stmt->execute();
    $active_reservations = $reservations_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check for upcoming due dates (3 days or less) for clients
if ($_SESSION['loggedin'] && $_SESSION['user_type'] == USER_CLIENT) {
    $upcoming_due_query = "SELECT k.pavadinimas, r.grazinimo_data 
                           FROM rezervacija r 
                           JOIN knyga k ON r.fk_knygaid = k.id 
                           WHERE r.fk_vartotojasid = ? 
                           AND r.ar_grazinta = FALSE 
                           AND r.grazinimo_data BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                           ORDER BY r.grazinimo_data";
    $upcoming_due_stmt = $db->prepare($upcoming_due_query);
    $upcoming_due_stmt->execute([$_SESSION['user_id']]);
    $upcoming_due_books = $upcoming_due_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bibliotekos Sistema</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Bibliotekos Sistema</h1>
            
            <!-- User Info and Login/Logout -->
            <div class="user-info">
                <?php if ($_SESSION['loggedin']): ?>
                    <div class="welcome">
                        Sveiki, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> 
                        (<?= $_SESSION['user_type'] ?>) 
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="logout" class="btn-logout">Atsijungti</button>
                        </form>
                    </div>
                    
                    <!-- Late Reservation Warning -->
                    <?php if (isset($has_late_reservations) && $has_late_reservations): ?>
                        <div class="alert alert-warning">
                            <strong>Dėmesio!</strong> Turite pavėluotų grąžinti knygų. Negalite rezervuoti naujų knygų kol negrąžinsite pavėluotų.
                        </div>
                    <?php endif; ?>

                    <!-- Upcoming Due Date Reminder -->
                    <?php if (isset($upcoming_due_books) && !empty($upcoming_due_books)): ?>
                        <div class="alert alert-info">
                            <strong>Priminimas!</strong> Jūsų šios knygos greičiau grąžinimo terminas (per 3 dienas arba mažiau):
                            <ul style="margin: 5px 0 0 0; padding-left: 20px;">
                                <?php foreach ($upcoming_due_books as $due_book): 
                                    $days_remaining = ceil((strtotime($due_book['grazinimo_data']) - time()) / (60 * 60 * 24));
                                    $days_text = $days_remaining == 1 ? 'dieną' : ($days_remaining <= 0 ? 'šiandien' : 'dienas');
                                ?>
                                <li>
                                    <strong><?= htmlspecialchars($due_book['pavadinimas']) ?></strong> 
                                    - grąžinimo data: <?= $due_book['grazinimo_data'] ?> 
                                    (<?= $days_remaining > 0 ? "liko $days_remaining $days_text" : "grąžinimas šiandien" ?>)
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <button onclick="openLoginModal()" class="btn-login">Prisijungti</button>
                <?php endif; ?>
            </div>
        </header>

        <!-- Success/Error Messages -->
        <?php if (isset($reservation_success)): ?>
            <div class="alert alert-success"><?= $reservation_success ?></div>
        <?php endif; ?>
        <?php if (isset($reservation_error)): ?>
            <div class="alert alert-error"><?= $reservation_error ?></div>
        <?php endif; ?>
        <?php if (isset($return_success)): ?>
            <div class="alert alert-success"><?= $return_success ?></div>
        <?php endif; ?>
        <?php if (isset($return_error)): ?>
            <div class="alert alert-error"><?= $return_error ?></div>
        <?php endif; ?>
        <?php if (isset($book_success)): ?>
            <div class="alert alert-success"><?= $book_success ?></div>
        <?php endif; ?>
        <?php if (isset($book_error)): ?>
            <div class="alert alert-error"><?= $book_error ?></div>
        <?php endif; ?>

        <!-- Client Reservations Panel -->
        <?php if ($_SESSION['loggedin'] && $_SESSION['user_type'] == USER_CLIENT): ?>
        <div class="client-reservations-panel">
            <h2>Mano rezervacijos</h2>
            
            <?php if (empty($client_reservations)): ?>
                <p>Jūs neturite jokių rezervacijų.</p>
            <?php else: ?>
                <div class="reservations-table-container">
                    <table class="reservations-table">
                        <thead>
                            <tr>
                                <th>Knyga</th>
                                <th>Autorius</th>
                                <th>Išėmimo data</th>
                                <th>Grąžinimo data</th>
                                <th>Būsena</th>
                                <th>Pastabos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($client_reservations as $reservation): 
                                $is_late = !$reservation['ar_grazinta'] && $reservation['grazinimo_data'] < date('Y-m-d');
                                $status_class = $reservation['ar_grazinta'] ? 'returned' : ($is_late ? 'late' : 'active');
                                $status_text = $reservation['ar_grazinta'] ? 'Grąžinta' : ($is_late ? 'Pavėluota' : 'Aktyvi');
                            ?>
                            <tr class="reservation-status-<?= $status_class ?>">
                                <td><?= htmlspecialchars($reservation['pavadinimas']) ?></td>
                                <td><?= htmlspecialchars($reservation['autorius']) ?></td>
                                <td><?= $reservation['issiemimo_data'] ?></td>
                                <td><?= $reservation['grazinimo_data'] ?></td>
                                <td>
                                    <span class="status-badge status-<?= $status_class ?>">
                                        <?= $status_text ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($is_late): ?>
                                        <span class="late-warning">❗ Praėjo grąžinimo terminas</span>
                                    <?php elseif (!$reservation['ar_grazinta']): ?>
                                        <span class="days-remaining">
                                            <?php
                                            $days_remaining = ceil((strtotime($reservation['grazinimo_data']) - time()) / (60 * 60 * 24));
                                            if ($days_remaining > 0) {
                                                echo "Liko $days_remaining diena(os)";
                                            } else {
                                                echo "Grąžinimo data šiandien";
                                            }
                                            ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="returned-date">Grąžinta: <?= $reservation['grazinimo_data'] ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Reservations Panel for Librarians -->
        <?php if ($_SESSION['loggedin'] && $_SESSION['user_type'] == USER_LIBRARIAN): ?>
        <div class="reservations-panel">
            <h2>Aktyvios rezervacijos</h2>
            
            <?php if (empty($active_reservations)): ?>
                <p>Aktyvių rezervacijų nėra.</p>
            <?php else: ?>
                <div class="reservations-table-container">
                    <table class="reservations-table">
                        <thead>
                            <tr>
                                <th>Knyga</th>
                                <th>Autorius</th>
                                <th>Skaitovas</th>
                                <th>Išėmimo data</th>
                                <th>Grąžinimo data</th>
                                <th>Būsena</th>
                                <th>Veiksmai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_reservations as $reservation): 
                                $is_late = $reservation['grazinimo_data'] < date('Y-m-d');
                                $status_class = $is_late ? 'late' : 'active';
                                $status_text = $is_late ? 'Pavėluota' : 'Aktyvi';
                            ?>
                            <tr class="reservation-status-<?= $status_class ?>">
                                <td><?= htmlspecialchars($reservation['pavadinimas']) ?></td>
                                <td><?= htmlspecialchars($reservation['autorius']) ?></td>
                                <td><?= htmlspecialchars($reservation['vardas'] . ' ' . $reservation['pavarde']) ?><br>
                                    <small>(<?= htmlspecialchars($reservation['username']) ?>)</small></td>
                                <td><?= $reservation['issiemimo_data'] ?></td>
                                <td><?= $reservation['grazinimo_data'] ?></td>
                                <td>
                                    <span class="status-badge status-<?= $status_class ?>">
                                        <?= $status_text ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" class="action-form">
                                        <input type="hidden" name="reservation_id" value="<?= $reservation['id'] ?>">
                                        <button type="submit" name="mark_returned" class="btn-return" 
                                                onclick="return confirm('Ar tikrai norite pažymėti šią knygą kaip grąžintą?')">
                                            Pažymėti grąžinta
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Admin Panel (Admin only) -->
        <?php if ($_SESSION['loggedin'] && $_SESSION['user_type'] == USER_ADMIN): ?>
        <div class="admin-panel">
            <h2>Administratoriaus skydas</h2>
            
            <div class="admin-sections">
                <!-- Create Librarian Account -->
                <div class="admin-section">
                    <h3>Sukurti bibliotekininko paskyrą</h3>
                    
                    <?php if (isset($librarian_error)): ?>
                        <div class="alert alert-error"><?= $librarian_error ?></div>
                    <?php endif; ?>
                    <?php if (isset($librarian_success)): ?>
                        <div class="alert alert-success"><?= $librarian_success ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" class="librarian-form">
                        <div class="form-row">
                            <input type="text" name="vardas" placeholder="Vardas" required>
                            <input type="text" name="pavarde" placeholder="Pavardė" required>
                        </div>
                        <div class="form-row">
                            <input type="text" name="username" placeholder="Vartotojo vardas" required>
                            <input type="password" name="password" placeholder="Slaptažodis" required minlength="6">
                        </div>
                        <button type="submit" name="register_librarian" class="btn-create-librarian">Sukurti bibliotekininko paskyrą</button>
                    </form>
                </div>

                <!-- User Management Section -->
                <div class="admin-section">
                    <h3>Vartotojų valdymas</h3>
                    
                    <?php if (isset($user_management_error)): ?>
                        <div class="alert alert-error"><?= $user_management_error ?></div>
                    <?php endif; ?>
                    <?php if (isset($user_management_success)): ?>
                        <div class="alert alert-success"><?= $user_management_success ?></div>
                    <?php endif; ?>
                    
                    <?php
                    $users_query = "SELECT id, vardas, pavarde, username, tipas FROM vartotojas ORDER BY tipas, vardas";
                    $users_stmt = $db->prepare($users_query);
                    $users_stmt->execute();
                    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <?php if (empty($users)): ?>
                        <p>Vartotojų nerasta.</p>
                    <?php else: ?>
                        <div class="users-list">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>Vardas</th>
                                        <th>Pavardė</th>
                                        <th>Vartotojo vardas</th>
                                        <th>Tipas</th>
                                        <th>Veiksmai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user_item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user_item['vardas']) ?></td>
                                        <td><?= htmlspecialchars($user_item['pavarde']) ?></td>
                                        <td><?= htmlspecialchars($user_item['username']) ?></td>
                                        <td>
                                            <span class="user-type-badge <?= strtolower($user_item['tipas']) ?>">
                                                <?= $user_item['tipas'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user_item['tipas'] != USER_ADMIN && $user_item['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" class="action-form">
                                                <input type="hidden" name="user_id" value="<?= $user_item['id'] ?>">
                                                <button type="submit" name="delete_user" class="btn-delete-small" 
                                                        onclick="return confirm('Ar tikrai norite ištrinti šį vartotoją? Visos grąžintos jo rezervacijos bus ištrintos.')">
                                                    Ištrinti
                                                </button>
                                            </form>
                                            <?php else: ?>
                                            <span class="no-action">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Add Book Form (Librarian/Admin only) -->
        <?php if ($_SESSION['loggedin'] && ($_SESSION['user_type'] == USER_LIBRARIAN || $_SESSION['user_type'] == USER_ADMIN)): ?>
        <div class="add-book-form">
            <h2>Pridėti naują knygą</h2>
            <form method="POST">
                <input type="text" name="pavadinimas" placeholder="Pavadinimas" required>
                <input type="text" name="autorius" placeholder="Autorius" required>
                <input type="number" name="puslapiu_sk" placeholder="Puslapių sk." required min="1">
                <input type="number" name="egzemplioriu_sk" placeholder="Egzempliorių sk." required min="1">
                <input type="number" name="isdavimo_laikas" placeholder="Išdavimo laikas (dienos)" required min="1">
                <button type="submit" name="add_book">Pridėti knygą</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Books List -->
        <div class="books-list">
            <div class="books-header">
                <h2>Knygų sąrašas</h2>
                
                <!-- Book Filter for All Users -->
                   <!-- Search bar: filters by book title or author -->
                   <div class="search-bar">
                       <input type="search" id="bookSearch" placeholder="Ieškoti pagal pavadinimą arba autorių" aria-label="Ieškoti knygų">
                   </div>
                <div class="book-filter">
                    <span class="filter-label">Rodyti:</span>
                    <a href="?filter=all" class="filter-btn <?= $book_filter === 'all' ? 'active' : '' ?>">Visas knygas</a>
                    <a href="?filter=available" class="filter-btn <?= $book_filter === 'available' ? 'active' : '' ?>">Tik laisvas knygas</a>
                </div>
            </div>
            
            <?php if (empty($books)): ?>
                <p>Knygų nerasta.</p>
            <?php else: ?>
                <div class="books-grid">
                    <?php foreach ($books as $book): ?>
                    <div class="book-card <?= $book['available_copies'] <= 0 ? 'unavailable' : '' ?>">
                        <h3><?= htmlspecialchars($book['pavadinimas']) ?></h3>
                        <p><strong>Autorius:</strong> <?= htmlspecialchars($book['autorius']) ?></p>
                        <p><strong>Puslapiai:</strong> <?= $book['puslapiu_sk'] ?></p>
                        <p><strong>Iš viso egzempliorių:</strong> <?= $book['egzemplioriu_sk'] ?></p>
                        <p><strong>Laisvų egzempliorių:</strong> 
                            <span class="available-copies <?= $book['available_copies'] <= 0 ? 'out-of-stock' : 'in-stock' ?>">
                                <?= $book['available_copies'] ?>
                            </span>
                        </p>
                        <p><strong>Išdavimo laikas:</strong> <?= $book['isdavimo_laikas'] ?> dienos</p>
                        
                        <div class="book-actions">
                            <!-- Reserve button for logged in clients -->
                            <?php if ($_SESSION['loggedin'] && $_SESSION['user_type'] == USER_CLIENT): ?>
                                <?php if (isset($has_late_reservations) && $has_late_reservations): ?>
                                <button class="btn-reserve disabled" disabled title="Negalite rezervuoti, nes turite pavėluotų knygų">
                                    Rezervuoti (užblokuota)
                                </button>
                                <?php elseif ($book['available_copies'] > 0): ?>
                                <button onclick="openReservationModal(<?= htmlspecialchars(json_encode($book)) ?>)" 
                                        class="btn-reserve">Rezervuoti</button>
                                <?php else: ?>
                                <button class="btn-reserve disabled" disabled>Nėra laisvų egzempliorių</button>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- Edit/Delete buttons for librarians/admins -->
                            <?php if ($_SESSION['loggedin'] && ($_SESSION['user_type'] == USER_LIBRARIAN || $_SESSION['user_type'] == USER_ADMIN)): ?>
                            <div class="librarian-actions">
                                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($book)) ?>)" 
                                        class="btn-edit">Redaguoti</button>
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                    <button type="submit" name="delete_book" class="btn-delete" 
                                            onclick="return confirm('Ar tikrai norite ištrinti šią knygą? Visos grąžintos rezervacijos bus ištrintos.')">
                                        Ištrinti
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p id="noResults" class="no-results" style="display:none">Nerasta rezultatų.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeLoginModal()">&times;</span>
            <h2>Prisijungti</h2>
            
            <?php if (isset($login_error)): ?>
                <div class="alert alert-error"><?= $login_error ?></div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm">
                <input type="text" name="username" placeholder="Vartotojo vardas" required>
                <input type="password" name="password" placeholder="Slaptažodis" required>
                <button type="submit" name="login">Prisijungti</button>
            </form>
            
            <div class="modal-footer">
                <p>Neturite paskyros? <a href="#" onclick="showRegisterForm()">Registruokitės</a></p>
            </div>
        </div>
    </div>

    <!-- Register Modal -->
    <div id="registerModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeRegisterModal()">&times;</span>
            <h2>Registracija</h2>
            
            <?php if (isset($register_error)): ?>
                <div class="alert alert-error"><?= $register_error ?></div>
            <?php endif; ?>
            <?php if (isset($register_success)): ?>
                <div class="alert alert-success"><?= $register_success ?></div>
            <?php endif; ?>
            
            <form method="POST" id="registerForm">
                <input type="text" name="vardas" placeholder="Vardas" required>
                <input type="text" name="pavarde" placeholder="Pavardė" required>
                <input type="text" name="username" placeholder="Vartotojo vardas" required>
                <input type="password" name="password" placeholder="Slaptažodis" required minlength="6">
                <button type="submit" name="register">Registruotis</button>
            </form>
            
            <div class="modal-footer">
                <p>Jau turite paskyrą? <a href="#" onclick="showLoginForm()">Prisijunkite</a></p>
            </div>
        </div>
    </div>

    <!-- Reservation Confirmation Modal -->
    <?php if ($_SESSION['loggedin'] && $_SESSION['user_type'] == USER_CLIENT): ?>
    <div id="reservationModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeReservationModal()">&times;</span>
            <h2>Patvirtinti rezervaciją</h2>
            
            <div id="reservationDetails">
                <!-- Reservation details will be filled by JavaScript -->
            </div>
            
            <form method="POST" id="reservationForm">
                <input type="hidden" name="book_id" id="reservation_book_id">
                <div class="reservation-actions">
                    <button type="button" onclick="closeReservationModal()" class="btn-cancel">Atšaukti</button>
                    <button type="submit" name="confirm_reservation" class="btn-confirm">Patvirtinti rezervaciją</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Edit Book Modal (Librarian/Admin only) -->
    <?php if ($_SESSION['loggedin'] && ($_SESSION['user_type'] == USER_LIBRARIAN || $_SESSION['user_type'] == USER_ADMIN)): ?>
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Redaguoti knygą</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="book_id" id="edit_book_id">
                <label for="edit_pavadinimas">Pavadinimas</label>
                <input type="text" name="pavadinimas" id="edit_pavadinimas" placeholder="Pavadinimas" required>
                <label for="edit_autorius">Autorius</label>
                <input type="text" name="autorius" id="edit_autorius" placeholder="Autorius" required>
                <label for="edit_puslapiu_sk">Puslapių skaičius</label>
                <input type="number" name="puslapiu_sk" id="edit_puslapiu_sk" placeholder="Puslapių sk." required min="1">
                <label for="edit_egzemplioriu_sk">Egzempliorių skaičius</label>
                <input type="number" name="egzemplioriu_sk" id="edit_egzemplioriu_sk" placeholder="Egzempliorių sk." required min="1">
                <label for="edit_isdavimo_laikas">Išdavimo laikas (dienos)</label>
                <input type="number" name="isdavimo_laikas" id="edit_isdavimo_laikas" placeholder="Išdavimo laikas (dienos)" required min="1">
                <button type="submit" name="edit_book">Išsaugoti pakeitimus</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script src="js/script.js"></script>
</body>
</html>