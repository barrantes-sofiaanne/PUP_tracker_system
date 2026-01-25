document.addEventListener("DOMContentLoaded", function () {
    const pageContainer = document.getElementById("pageContainer");
    const openMenuBtn = document.getElementById("openMenuBtn");
    const closeMenuBtn = document.getElementById("closeMenuBtn");
    const overlay = document.getElementById("overlay");

    if (openMenuBtn) {
        openMenuBtn.addEventListener("click", () =>
            pageContainer.classList.add("menu-open")
        );
    }
    if (closeMenuBtn) {
        closeMenuBtn.addEventListener("click", () =>
            pageContainer.classList.remove("menu-open")
        );
    }
    if (overlay) {
        overlay.addEventListener("click", () =>
            pageContainer.classList.remove("menu-open")
        );
    }

    const accordionHeaders = document.querySelectorAll(".section-title");
    accordionHeaders.forEach(header => {
        header.addEventListener("click", () => {
            if (window.innerWidth <= 768) {
                header.classList.toggle("active");
            }
        });

        const newDiv = document.createElement('div');
        newDiv.className = 'title-text';
        while(header.firstChild) {
            newDiv.appendChild(header.firstChild);
        }
        header.appendChild(newDiv);
        
        const icon = document.createElement('i');
        icon.className = 'fas fa-chevron-down toggle-icon';
        header.appendChild(icon);
    });
});