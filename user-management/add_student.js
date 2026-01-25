document.addEventListener("DOMContentLoaded", function() {
    const addStudentButton = document.getElementById("add-student");
    const modal = document.getElementById("modal");
    const closeModalButton = document.querySelector(".modal .close");
    const form = document.getElementById("student-form");

    addStudentButton.addEventListener("click", function() {
        modal.style.display = "block";
    });

    closeModalButton.addEventListener("click", function() {
        modal.style.display = "none";
    });

    window.addEventListener("click", function(event) {
        if (event.target === modal) {
            modal.style.display = "none";
        }
    });

    form.addEventListener("submit", function(event) {
        event.preventDefault();

        const formData = new FormData(form);

        fetch('add_student.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if (data === 'Student added successfully') {
                alert('Student added successfully');
                window.location.reload();
            } else {
                alert('Failed to add student');
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    });
});
