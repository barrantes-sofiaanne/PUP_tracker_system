const buttonA = document.getElementById("student-btn");
const buttonB = document.getElementById("admin-btn");

buttonA.addEventListener("click", function() {
    window.location.href = "../student-page/student_login.php"; 
});

buttonB.addEventListener("click", function() {
    window.location.href = "../admin-login/admin_login.php"; 
});