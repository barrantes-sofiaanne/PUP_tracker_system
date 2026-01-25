document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("modal");
    const openModalBtn = document.querySelector(".open-modal-btn");
    const closeModalBtn = document.querySelector(".close-btn");
    const addButton = document.querySelector(".add-btn");
    const deleteButton = document.querySelector(".delete-btn");

    // Open modal
    openModalBtn.addEventListener("click", function () {
        modal.style.display = "flex";
    });

    // Close modal
    closeModalBtn.addEventListener("click", function () {
        modal.style.display = "none";
    });

    // Function to collect data and send it to admin_violation.html
    addButton.addEventListener("click", function () {
        const studentNumber = document.querySelector("input[placeholder='Student Number']").value;
        const courseYear = document.querySelector("select").value;
        const firstName = document.querySelector("input[placeholder='First Name']").value;
        const date = document.querySelector("input[type='date']").value;
        const middleName = document.querySelector("input[placeholder='Middle Name']").value;
        const violation = document.querySelectorAll("select")[1].value;
        const lastName = document.querySelector("input[placeholder='Last Name']").value;

        if (studentNumber && courseYear && firstName && date && middleName && violation && lastName) {
            const studentData = {
                studentNumber,
                fullName: `${firstName} ${middleName} ${lastName}`,
                courseYear,
                violation,
                date
            };
            
            localStorage.setItem("violationEntry", JSON.stringify(studentData));
            window.location.href = "/HTML/admin_violation.html";
        } else {
            alert("Please fill in all fields before adding.");
        }
    });

    // Delete all fields
    deleteButton.addEventListener("click", function () {
        document.querySelectorAll("input").forEach(input => input.value = "");
        document.querySelectorAll("select").forEach(select => select.selectedIndex = 0);
    });
});