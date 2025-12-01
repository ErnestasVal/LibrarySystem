<?php
class User {
    private $conn;
    private $table_name = "vartotojas";

    public $id;
    public $vardas;
    public $pavarde;
    public $username;
    public $password;
    public $tipas;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Register new user
    public function register() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET vardas=:vardas, pavarde=:pavarde, username=:username, password=:password, tipas=:tipas";
        
        $stmt = $this->conn->prepare($query);
        
        // Hash password
        $hashed_password = password_hash($this->password, PASSWORD_DEFAULT);
        
        // Bind values
        $stmt->bindParam(":vardas", $this->vardas);
        $stmt->bindParam(":pavarde", $this->pavarde);
        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":password", $hashed_password);
        $stmt->bindParam(":tipas", $this->tipas);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Check if username exists
    public function usernameExists() {
        $query = "SELECT id, vardas, pavarde, password, tipas 
                  FROM " . $this->table_name . " 
                  WHERE username = :username 
                  LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $this->username);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->vardas = $row['vardas'];
            $this->pavarde = $row['pavarde'];
            $this->password = $row['password'];
            $this->tipas = $row['tipas'];
            return true;
        }
        return false;
    }

    // Verify password
    public function verifyPassword($password) {
        return password_verify($password, $this->password);
    }
}
?>