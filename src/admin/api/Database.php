<?php
class Database {
    private $host = "localhost";
    private $db_name = "course_management";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );

            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch(PDOException $exception) {
            echo json_encode(["success" => false, "message" => "Database Connection Failed"]);
            exit;
        }

        return $this->conn;
    }
}
?>
