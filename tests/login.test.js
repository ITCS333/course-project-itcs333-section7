// Function to display messages to the user
function displayMessage(message, type) {
  const messageContainer = document.getElementById('message');
  if (messageContainer) {
    messageContainer.textContent = message;
    messageContainer.className = type;
  }
}

// Function to validate email format
function isValidEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

// Function to validate password length
function isValidPassword(password) {
  return password.length >= 8;
}

// Function to handle login form submission
function handleLogin(event) {
  event.preventDefault();
  
  const email = document.getElementById('email').value;
  const password = document.getElementById('password').value;
  
  // Validate email
  if (!isValidEmail(email)) {
    displayMessage('Please enter a valid email address', 'error');
    return;
  }
  
  // Validate password
  if (!isValidPassword(password)) {
    displayMessage('Password must be at least 8 characters long', 'error');
    return;
  }
  
  // If validation passes
  displayMessage('Login successful!', 'success');
}

// Function to set up the login form
function setupLoginForm() {
  const loginForm = document.getElementById('login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', handleLogin);
  }
}

// Call setup function when page loads
setupLoginForm();
