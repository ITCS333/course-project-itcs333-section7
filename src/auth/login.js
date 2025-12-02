document.getElementById("login-form").addEventListener("submit", async (e) => {
    e.preventDefault();

    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value.trim();

    if (!email || !password) {
        alert("Please enter your email and password.");
        return;
    }

    try {
        const response = await fetch("./api/index.php?action=login", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email, password })
        });

        const data = await response.json();
        console.log("API Response:", data);

        if (data.success) {
            alert("Login successful!");

            // FIXED REDIRECT — correct path to your admin page
            window.location.href = "../admin/manage_users.html";
        } else {
            alert(data.message || "Invalid email or password.");
        }
    } catch (error) {
        console.error("Login error:", error);
        alert("Server error. Try again later.");
    }
});
