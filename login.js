const loginForm = document.getElementById("loginForm");
const loginButton = document.getElementById("loginButton");
const loginMessage = document.getElementById("loginMessage");

loginForm.addEventListener("submit", async function (event) {

    event.preventDefault();

    const username = document
        .getElementById("username")
        .value
        .trim();

    const password = document
        .getElementById("password")
        .value;

    if (username === "" || password === "") {

        showMessage(
            "Please enter username and password.",
            "error"
        );

        return;
    }

    loginButton.disabled = true;
    loginButton.textContent = "🔄 Logging in...";

    showMessage("", "");

    try {

        const loginEndpoint = (["localhost", "127.0.0.1"].includes(location.hostname) && ["", "80"].includes(location.port))
            ? "./login.php"
            : "/api/login";
        const response = await fetch(loginEndpoint, {
            method: "POST",

            headers: {
                "Content-Type": "application/json"
            },

            body: JSON.stringify({
                username: username,
                password: password
            })
        });

        if (!response.ok) {
            throw new Error(
                "HTTP Error: " + response.status
            );
        }

        const data = await response.json();

        if (data.success) {

            showMessage(
                "Login successful. Opening dashboard...",
                "success"
            );

            localStorage.setItem(
                "isLoggedIn",
                "true"
            );

            localStorage.setItem(
                "loggedInUser",
                JSON.stringify(data.user)
            );

            setTimeout(function () {

                window.location.href = "./index.html";

            }, 700);

        } else {

            showMessage(
                data.message || "Invalid username or password.",
                "error"
            );

            loginButton.disabled = false;
            loginButton.textContent = "🔐 Login";
        }

    } catch (error) {

        console.error("Login error:", error);

        showMessage(
            "Unable to connect to server.",
            "error"
        );

        loginButton.disabled = false;
        loginButton.textContent = "🔐 Login";
    }

});


function showMessage(message, type) {

    loginMessage.textContent = message;

    loginMessage.className = "";

    if (type !== "") {
        loginMessage.classList.add(type);
    }
}
