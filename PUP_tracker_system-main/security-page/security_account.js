document.addEventListener("DOMContentLoaded", function() {
    const pageContainer = document.getElementById("pageContainer");
    const openMenuBtn = document.getElementById("openMenuBtn");
    const closeMenuBtn = document.getElementById("closeMenuBtn");
    const overlay = document.getElementById("overlay");

    if (openMenuBtn) {
        openMenuBtn.addEventListener("click", () => pageContainer.classList.add("menu-open"));
    }
    if (closeMenuBtn) {
        closeMenuBtn.addEventListener("click", () => pageContainer.classList.remove("menu-open"));
    }
    if (overlay) {
        overlay.addEventListener("click", () => pageContainer.classList.remove("menu-open"));
    }

    const securityFirstNameElement = document.getElementById("securityFirstName");
    const securityMiddleNameElement = document.getElementById("securityMiddleName");
    const securityLastNameElement = document.getElementById("securityLastName");
    const securityPositionElement = document.getElementById("securityPosition");
    const securityEmailElement = document.getElementById("securityEmail");
    const signOutBtn = document.getElementById("signOutBtn");
    const errorMessageDisplayElement = document.getElementById("errorMessageDisplay");

    if (securityData && securityData.isLoggedIn && !securityData.errorMessage) {
        if (securityFirstNameElement) securityFirstNameElement.textContent = securityData.firstName;
        if (securityMiddleNameElement) securityMiddleNameElement.textContent = securityData.middleName || 'N/A';
        if (securityLastNameElement) securityLastNameElement.textContent = securityData.lastName;
        if (securityPositionElement) securityPositionElement.textContent = securityData.position;
        if (securityEmailElement) securityEmailElement.textContent = securityData.email;
    } else {
        if (securityFirstNameElement) securityFirstNameElement.textContent = 'N/A';
        if (securityMiddleNameElement) securityMiddleNameElement.textContent = 'N/A';
        if (securityLastNameElement) securityLastNameElement.textContent = 'N/A';
        if (securityPositionElement) securityPositionElement.textContent = 'N/A';
        if (securityEmailElement) securityEmailElement.textContent = 'N/A';
        
        if (errorMessageDisplayElement && securityData.errorMessage) {
            errorMessageDisplayElement.textContent = "Error: " + securityData.errorMessage;
        }
        if (securityData.errorMessage && (securityData.errorMessage.includes("Not authorized") || securityData.errorMessage.includes("session has expired"))) {
            setTimeout(() => {
                window.location.href = './security_login.php';
            }, 3000);
        }
    }

    if (signOutBtn) {
        signOutBtn.addEventListener("click", function() {
            window.location.href = '../PHP/logout.php';
        });
    }
});