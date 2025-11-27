// Select elements
const loginForm = document.getElementById("login-form");
const emailInput = document.getElementById("email");
const passwordInput = document.getElementById("password");
const messageContainer = document.getElementById("message-container");

// Display message
function displayMessage(message, type) {
    messageContainer.textContent = message;
    messageContainer.className = type;
}

// Email validation
function isValidEmail(email) {
    const regex = /\S+@\S+\.\S+/;
    return regex.test(email);
}

// Password must be 8+ chars
function isValidPassword(password) {
    return password.length >= 8;
}

// Login handler
async function handleLogin(event) {
    event.preventDefault();

    const email = emailInput.value.trim();
    const password = passwordInput.value.trim();

    // Validate email
    if (!isValidEmail(email)) {
        displayMessage("Invalid email format.", "error");
        return;
    }

    // Validate password
    if (!isValidPassword(password)) {
        displayMessage("Password must be at least 8 characters.", "error");
        return;
    }

    // Fetch student list
    const response = await fetch("students.json");
    const students = await response.json();

    // 1️⃣ Check admin login
    if (email === "admin@admin.com") {
        displayMessage("Admin login successful!", "success");

        setTimeout(() => {
            window.location.href = "../admin/manage_users.html";
        }, 1000);

        return;
    }

    // 2️⃣ Check student login
    const student = students.find(s => s.email === email);

    if (!student) {
        displayMessage("This email is not registered.", "error");
        return;
    }

    // For phase 1: student password = student.id
    if (password !== student.id) {
        displayMessage("Incorrect password.", "error");
        return;
    }

    // Success
    displayMessage("Login successful!", "success");

    setTimeout(() => {
        window.location.href = "../index.html"; // student homepage
    }, 1000);
}

// Attach event
function setupLoginForm() {
    if (loginForm) {
        loginForm.addEventListener("submit", handleLogin);
    }
}

setupLoginForm();
