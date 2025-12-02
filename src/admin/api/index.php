<?php
session_set_cookie_params([
    'path' => '/course-project-itcs333-section7/'
]);

session_start(); // 🔥 REQUIRED for login-based access

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

function getStudents(PDO $db) {
    $search = isset($_GET["search"]) ? trim($_GET["search"]) : "";
    $sort   = isset($_GET["sort"]) ? $_GET["sort"] : "name";
    $order  = isset($_GET["order"]) ? strtolower($_GET["order"]) : "asc";

    $allowedSort  = ["name", "student_id", "email"];
    $allowedOrder = ["asc", "desc"];

    if (!in_array($sort, $allowedSort, true)) $sort = "name";
    if (!in_array($order, $allowedOrder, true)) $order = "asc";

    $sql    = "SELECT id, student_id, name, email, created_at FROM students";
    $params = [];

    if ($search !== "") {
        $sql .= " WHERE name LIKE :search OR student_id LIKE :search OR email LIKE :search";
        $params[":search"] = "%{$search}%";
    }

    $sql .= " ORDER BY {$sort} {$order}";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(["success" => true, "data" => $students], 200);
}

function getStudentById(PDO $db, $studentId) {
    $studentId = sanitizeInput($studentId);

    $stmt = $db->prepare("SELECT id, student_id, name, email, created_at FROM students WHERE student_id = :sid");
    $stmt->bindValue(":sid", $studentId, PDO::PARAM_STR);
    $stmt->execute();
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
        empty($data["name"])       ||
        empty($data["email"])      ||
        empty($data["password"])
    ) {
        sendResponse(["success" => false, "message" => "Missing required fields"], 400);
    }

    $studentId = sanitizeInput($data["student_id"]);
    $name      = sanitizeInput($data["name"]);
    $email     = sanitizeInput($data["email"]);
    $password  = $data["password"];

    if (!validateEmail($email)) {
        sendResponse(["success" => false, "message" => "Invalid email format"], 400);
    }

    $check = $db->prepare("SELECT id FROM students WHERE student_id = :sid OR email = :email");
    $check->execute([
        ":sid"   => $studentId,
        ":email" => $email
    ]);

    if ($check->fetch(PDO::FETCH_ASSOC)) {
        sendResponse(["success" => false, "message" => "Student ID or email already exists"], 409);
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare("
        INSERT INTO students (student_id, name, email, password)
        VALUES (:sid, :name, :email, :password)
    ");
    $ok = $stmt->execute([
        ":sid"      => $studentId,
        ":name"     => $name,
        ":email"    => $email,
        ":password" => $hashed
    ]);

    if ($ok) {
        sendResponse(["success" => true, "message" => "Student created"], 201);
    } else {
        sendResponse(["success" => false, "message" => "Failed to create student"], 500);
    }
}

function updateStudent(PDO $db, $data) {
    if (empty($data["original_student_id"])) {
        sendResponse(["success" => false, "message" => "original_student_id is required"], 400);
    }

    $originalId = sanitizeInput($data["original_student_id"]);
    $newId      = sanitizeInput($data["new_student_id"] ?? $originalId);
    $name       = sanitizeInput($data["name"] ?? "");
    $email      = sanitizeInput($data["email"] ?? "");

    $check = $db->prepare("SELECT id FROM students WHERE student_id = :sid");
    $check->execute([":sid" => $originalId]);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        sendResponse(["success" => false, "message" => "Student not found"], 404);
    }

    if ($newId !== $originalId) {
        $checkID = $db->prepare("SELECT id FROM students WHERE student_id = :sid");
        $checkID->execute([":sid" => $newId]);

        if ($checkID->fetch(PDO::FETCH_ASSOC)) {
            sendResponse(["success" => false, "message" => "New Student ID already exists"], 409);
        }
    }

    if (!empty($email)) {
        if (!validateEmail($email)) {
            sendResponse(["success" => false, "message" => "Invalid email"], 400);
        }

        $checkEmail = $db->prepare("SELECT id FROM students WHERE email = :email AND student_id != :sid");
        $checkEmail->execute([":email" => $email, ":sid" => $originalId]);

        if ($checkEmail->fetch(PDO::FETCH_ASSOC)) {
            sendResponse(["success" => false, "message" => "Email already in use"], 409);
        }
    }

    $fields = [];
    $params = [];

    if (!empty($name)) {
        $fields[] = "name = :name";
        $params[":name"] = $name;
    }

    if (!empty($email)) {
        $fields[] = "email = :email";
        $params[":email"] = $email;
    }

    if (!empty($newId)) {
        $fields[] = "student_id = :newId";
        $params[":newId"] = $newId;
    }

    if (empty($fields)) {
        sendResponse(["success" => false, "message" => "Nothing to update"], 400);
    }

    $params[":originalId"] = $originalId;

    $sql = "UPDATE students SET " . implode(", ", $fields) . " WHERE student_id = :originalId";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    sendResponse(["success" => true, "message" => "Student updated"], 200);
}

function deleteStudent(PDO $db, $studentId) {
    if (empty($studentId)) {
        sendResponse(["success" => false, "message" => "student_id is required"], 400);
    }

    $studentId = sanitizeInput($studentId);

    $check = $db->prepare("SELECT id FROM students WHERE student_id = :sid");
    $check->execute([":sid" => $studentId]);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        sendResponse(["success" => false, "message" => "Student not found"], 404);
    }

    $stmt = $db->prepare("DELETE FROM students WHERE student_id = :sid");
    $ok   = $stmt->execute([":sid" => $studentId]);

    if ($ok) {
        sendResponse(["success" => true, "message" => "Student deleted"], 200);
    } else {
        sendResponse(["success" => false, "message" => "Failed to delete student"], 500);
    }
}

function changePassword(PDO $db, $data) {

    // Use logged-in user from session
    if (!isset($_SESSION["user_id"])) {
        sendResponse(["success" => false, "message" => "Not logged in"], 401);
    }

    // This came from the `users` table at login
    $adminId = $_SESSION["user_id"];

    if (
        empty($data["current_password"]) ||
        empty($data["new_password"])
    ) {
        sendResponse(["success" => false, "message" => "Missing fields"], 400);
    }

    $current = $data["current_password"];
    $new     = $data["new_password"];

    if (strlen($new) < 8) {
        sendResponse(["success" => false, "message" => "Password must be at least 8 characters"], 400);
    }

    // 🔥 FETCH from `users` table (admin account)
    $stmt = $db->prepare("SELECT password FROM users WHERE student_id = :sid");
    $stmt->execute([":sid" => $adminId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        sendResponse(["success" => false, "message" => "Admin not found"], 404);
    }

    // Check current password
    if (!password_verify($current, $row["password"])) {
        sendResponse(["success" => false, "message" => "Current password is incorrect"], 401);
    }

    // Hash new password and update
    $hashed = password_hash($new, PASSWORD_DEFAULT);
    $update = $db->prepare("UPDATE users SET password = :password WHERE student_id = :sid");
    $ok     = $update->execute([":password" => $hashed, ":sid" => $adminId]);

    if ($ok) {
        sendResponse(["success" => true, "message" => "Password updated"], 200);
    } else {
        sendResponse(["success" => false, "message" => "Failed to update password"], 500);
    }
}


// ======================================================
// ROUTER
// ======================================================

try {
    if ($method === "GET") {

        if (!empty($query["student_id"])) {
            getStudentById($db, $query["student_id"]);
        } else {
            getStudents($db);
        }

    } elseif ($method === "POST") {

        if (isset($query["action"]) && $query["action"] === "change_password") {
            changePassword($db, $bodyData);
        } else {
            createStudent($db, $bodyData);
        }

    } elseif ($method === "PUT") {

        updateStudent($db, $bodyData);

    } elseif ($method === "DELETE") {

        $sid = $query["student_id"] ?? ($bodyData["student_id"] ?? "");
        deleteStudent($db, $sid);

    } else {

        sendResponse(["success" => false, "message" => "Method not allowed"], 405);
    }

} catch (Exception $e) {
    sendResponse(["success" => false, "message" => "Server error: ".$e->getMessage()], 500);
}
