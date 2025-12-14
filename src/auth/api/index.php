<?php

session_set_cookie_params([
    'path' => '/course-project-itcs333-section7/'
]);

// 🔥 ADDED — start session
session_start();

header("Content-Type: application/json");
require_once "Database.php";

// Create DB connection
$db = new Database();
$conn = $db->getConnection();

// Helper to send JSON response
function sendResponse($success, $message = "", $data = null)
{
    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data"    => $data,
    ]);
    exit;
}

// Read action
$action = $_GET["action"] ?? "";

// Read JSON Body
$input = json_decode(file_get_contents("php://input"), true);

// ------------------------------
// 1) LOGIN
// ------------------------------
if ($action === "login") {

    try {

        if (!$input || empty($input["email"]) || empty($input["password"])) {
            sendResponse(false, "Missing email or password.");
        }

        $email = $input["email"];
        $password = $input["password"];

      
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendResponse(false, "Invalid email format.");
        }

        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            sendResponse(false, "Email not found.");
        }

        if (!password_verify($password, $user["password"])) {
            sendResponse(false, "Incorrect password.");
        }

        $_SESSION["user_id"] = $user["student_id"];
        $_SESSION["role"] = $user["role"];

        sendResponse(true, "Login successful!", [
            "role" => $user["role"]
        ]);

    }
    
    catch (PDOException $e) {
        sendResponse(false, "Database error.");
    }
}


// ------------------------------
// 2) LOAD STUDENTS (GET)
// ------------------------------
if ($_SERVER["REQUEST_METHOD"] === "GET" && empty($action)) {

    try {
        $stmt = $conn->query("SELECT name, student_id, email FROM students");
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendResponse(true, "Students loaded.", $students);

    } catch (PDOException $e) {
        sendResponse(false, "Database error.");
    }
}


// ------------------------------
// 3) ADD STUDENT (POST)
// ------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && empty($action)) {

    if (!$input ||
        empty($input["name"]) ||
        empty($input["student_id"]) ||
        empty($input["email"]) ||
        empty($input["password"])) {

        sendResponse(false, "Missing required student fields.");
    }

    $name = $input["name"];
    $student_id = $input["student_id"];
    $email = $input["email"];
    $password = password_hash($input["password"], PASSWORD_DEFAULT);

    try {
        $stmt = $conn->prepare("
            INSERT INTO students (name, student_id, email, password)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$name, $student_id, $email, $password]);

        sendResponse(true, "Student added successfully.");

    } catch (PDOException $e) {
        sendResponse(false, "Database error.");
    }
}


// ------------------------------
// 4) DELETE STUDENT (DELETE)
// ------------------------------
if ($_SERVER["REQUEST_METHOD"] === "DELETE") {

    if (empty($_GET["student_id"])) {
        sendResponse(false, "Missing student_id.");
    }

    $student_id = $_GET["student_id"];

    try {
        $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
        $stmt->execute([$student_id]);

        sendResponse(true, "Student deleted successfully.");

    } catch (PDOException $e) {
        sendResponse(false, "Database error.");
    }
}


// ------------------------------
// 5) CHANGE PASSWORD
// ------------------------------
if ($action === "change_password") {

    if (!isset($_SESSION["user_id"])) {
        sendResponse(false, "You are not logged in.");
    }

    $student_id = $_SESSION["user_id"];

    if (
        !$input ||
        empty($input["current_password"]) ||
        empty($input["new_password"])
    ) {
        sendResponse(false, "Missing required fields.");
    }

    $current = $input["current_password"];
    $new = $input["new_password"];

    try {

        $stmt = $conn->prepare("SELECT * FROM users WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            sendResponse(false, "Admin not found.");
        }

        if (!password_verify($current, $user["password"])) {
            sendResponse(false, "Incorrect current password.");
        }

        $newHash = password_hash($new, PASSWORD_DEFAULT);

        $update = $conn->prepare(
            "UPDATE users SET password = ? WHERE student_id = ?"
        );
        $update->execute([$newHash, $student_id]);

        sendResponse(true, "Password updated successfully.");

    } catch (PDOException $e) {
        sendResponse(false, "Database error.");
    }
}


// ------------------------------
// IF NOTHING MATCHED
// ------------------------------
sendResponse(false, "Invalid request.");
