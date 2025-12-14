<?php
session_set_cookie_params([
    'path' => '/course-project-itcs333-section7/'
]);

session_start(); // REQUIRED for login-based access

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "Database.php";
$db = (new Database())->getConnection();

$method   = $_SERVER["REQUEST_METHOD"];
$rawBody  = file_get_contents("php://input");
$bodyData = $rawBody ? json_decode($rawBody, true) : [];
$query    = $_GET;

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES, "UTF-8");
    return $data;
}

/* =========================
   STUDENTS
========================= */

function getStudents(PDO $db) {
    $stmt = $db->query("SELECT id, student_id, name, email FROM students");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse(["success" => true, "data" => $students], 200);
}

function getStudentById(PDO $db, $studentId) {
    $stmt = $db->prepare("SELECT id, student_id, name, email FROM students WHERE student_id = :sid");
    $stmt->execute([":sid" => sanitizeInput($studentId)]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        sendResponse(["success" => true, "data" => $student], 200);
    } else {
        sendResponse(["success" => false, "message" => "Student not found"], 404);
    }
}

function createStudent(PDO $db, $data) {
    if (
        empty($data["student_id"]) ||
        empty($data["name"]) ||
        empty($data["email"]) ||
        empty($data["password"])
    ) {
        sendResponse(["success" => false, "message" => "Missing required fields"], 400);
    }

    if (!validateEmail($data["email"])) {
        sendResponse(["success" => false, "message" => "Invalid email format"], 400);
    }

    $stmt = $db->prepare(
        "INSERT INTO students (student_id, name, email, password)
         VALUES (:sid, :name, :email, :password)"
    );

    $stmt->execute([
        ":sid" => sanitizeInput($data["student_id"]),
        ":name" => sanitizeInput($data["name"]),
        ":email" => sanitizeInput($data["email"]),
        ":password" => password_hash($data["password"], PASSWORD_DEFAULT)
    ]);

    sendResponse(["success" => true, "message" => "Student created"], 201);
}

function updateStudent(PDO $db, $data) {
    if (empty($data["original_student_id"])) {
        sendResponse(["success" => false, "message" => "original_student_id is required"], 400);
    }

    $stmt = $db->prepare(
        "UPDATE students SET name = :name, email = :email
         WHERE student_id = :sid"
    );

    $stmt->execute([
        ":name" => sanitizeInput($data["name"]),
        ":email" => sanitizeInput($data["email"]),
        ":sid" => sanitizeInput($data["original_student_id"])
    ]);

    sendResponse(["success" => true, "message" => "Student updated"], 200);
}

function deleteStudent(PDO $db, $studentId) {
    $stmt = $db->prepare("DELETE FROM students WHERE student_id = :sid");
    $stmt->execute([":sid" => sanitizeInput($studentId)]);
    sendResponse(["success" => true, "message" => "Student deleted"], 200);
}

function changePassword(PDO $db, $data) {
    if (!isset($_SESSION["user_id"])) {
        sendResponse(["success" => false, "message" => "Not logged in"], 401);
    }

    $stmt = $db->prepare("SELECT password FROM users WHERE student_id = :sid");
    $stmt->execute([":sid" => $_SESSION["user_id"]]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($data["current_password"], $user["password"])) {
        sendResponse(["success" => false, "message" => "Incorrect password"], 401);
    }

    $update = $db->prepare("UPDATE users SET password = :pw WHERE student_id = :sid");
    $update->execute([
        ":pw" => password_hash($data["new_password"], PASSWORD_DEFAULT),
        ":sid" => $_SESSION["user_id"]
    ]);

    sendResponse(["success" => true, "message" => "Password updated"], 200);
}

/* =========================
   ROUTER
========================= */

try {

    if ($method === "GET") {
        !empty($query["student_id"])
            ? getStudentById($db, $query["student_id"])
            : getStudents($db);

    } elseif ($method === "POST") {
        isset($query["action"]) && $query["action"] === "change_password"
            ? changePassword($db, $bodyData)
            : createStudent($db, $bodyData);

    } elseif ($method === "PUT") {
        updateStudent($db, $bodyData);

    } elseif ($method === "DELETE") {
        deleteStudent($db, $query["student_id"] ?? "");

    } else {
        sendResponse(["success" => false, "message" => "Method not allowed"], 405);
    }

}
// ✅ REQUIRED BY PHPUnit
catch (PDOException $e) {
    sendResponse(["success" => false, "message" => "Database error"], 500);
}
