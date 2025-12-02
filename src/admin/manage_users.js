

console.log("manage_users.js loaded");

const apiURL = "./api/index.php";

document.addEventListener("DOMContentLoaded", () => {
    loadStudents();

    const pwForm = document.getElementById("passwordForm");
    if (pwForm) pwForm.addEventListener("submit", handlePasswordChange);

    document.getElementById("addBtn").onclick = addStudent;
});

// ============================
// LOAD STUDENTS
// ============================
async function loadStudents() {
    const res = await fetch(apiURL, {
        method: "GET",
        credentials: "include"
    });

    const result = await res.json();
    if (!result.success) return alert(result.message);

    const table = document.getElementById("studentsTable");
    table.innerHTML = "";

    result.data.forEach(s => {
        table.innerHTML += `
        <tr>
            <td>${s.name}</td>
            <td>${s.student_id}</td>
            <td>${s.email}</td>
            <td>
                <button onclick='startEdit(${JSON.stringify(s)})'>Edit</button>
                <button class="secondary" onclick='deleteStudent("${s.student_id}")'>Delete</button>
            </td>
        </tr>`;
    });
}

// ============================
// ADD STUDENT
// ============================
async function addStudent() {
    const data = {
        name: document.getElementById("name").value.trim(),
        student_id: document.getElementById("studentId").value.trim(),
        email: document.getElementById("email").value.trim(),
        password: document.getElementById("password").value.trim()
    };

    const res = await fetch(apiURL, {
        method: "POST",
        credentials: "include",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify(data)
    });

    const r = await res.json();
    alert(r.message);

    if (r.success) {
        resetForm();
        loadStudents();
    }
}

// ============================
// START EDIT MODE
// ============================
function startEdit(s) {
    document.getElementById("name").value = s.name;
    document.getElementById("studentId").value = s.student_id;
    document.getElementById("email").value = s.email;

    // SAVE ORIGINAL ID (REQUIRED FOR BACKEND)
    document.getElementById("originalStudentId").value = s.student_id;

    // Allow editing student ID (fix)
    document.getElementById("studentId").disabled = false;

    document.getElementById("addBtn").textContent = "Save Changes";
    document.getElementById("addBtn").onclick = updateStudent;
}

// ============================
// UPDATE STUDENT
// ============================
async function updateStudent() {
    const payload = {
        original_student_id: document.getElementById("originalStudentId").value,
        new_student_id: document.getElementById("studentId").value.trim(),
        name: document.getElementById("name").value.trim(),
        email: document.getElementById("email").value.trim()
    };

    console.log("UPDATE PAYLOAD:", payload);

    const res = await fetch(apiURL, {
        method: "PUT",
        credentials: "include",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify(payload)
    });

    const r = await res.json();
    alert(r.message);

    if (r.success) {
        resetForm();
        loadStudents();
    }
}

// ============================
// DELETE STUDENT
// ============================
async function deleteStudent(id) {
    if (!confirm("Delete this student?")) return;

    const res = await fetch(apiURL + "?student_id=" + id, {
        method: "DELETE",
        credentials: "include"
    });

    const r = await res.json();
    alert(r.message);

    if (r.success) loadStudents();
}

// ============================
// RESET FORM
// ============================
function resetForm() {
    document.getElementById("name").value = "";
    document.getElementById("studentId").value = "";
    document.getElementById("email").value = "";
    document.getElementById("password").value = "";

    document.getElementById("studentId").disabled = false;
    document.getElementById("originalStudentId").value = "";

    document.getElementById("addBtn").textContent = "Add Student";
    document.getElementById("addBtn").onclick = addStudent;
}

// ============================
// CHANGE PASSWORD
// ============================
async function handlePasswordChange(e) {
    e.preventDefault();

    const current_password = document.getElementById("currentPassword").value.trim();
    const new_password     = document.getElementById("newPassword").value.trim();
    const confirm_password = document.getElementById("confirmPassword").value.trim();

    if (new_password !== confirm_password) {
        alert("New passwords do not match!");
        return;
    }

    const res = await fetch(apiURL + "?action=change_password", {
        method: "POST",
        credentials: "include",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({
            current_password,
            new_password
        })
    });

    const r = await res.json();
    alert(r.message);

    if (r.success) document.getElementById("passwordForm").reset();
}
