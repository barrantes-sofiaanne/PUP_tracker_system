document.addEventListener("DOMContentLoaded", () => {
    const notificationLink = document.getElementById('notificationLinkToggle');
    const notificationDropdown = document.getElementById('notificationsDropdownContent');
    
    if (!notificationLink || !notificationDropdown) return;

    notificationLink.addEventListener('click', (e) => {
        e.preventDefault();
        const isVisible = notificationDropdown.classList.toggle('show');
        const notificationCountBadge = notificationLink.querySelector('.notification-count');

        if (isVisible && notificationCountBadge) {
            const formData = new FormData();
            formData.append('action', 'mark_admin_notifs_read');
            
            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    notificationCountBadge.style.display = 'none';
                }
            }).catch(error => console.error('Error marking notifications as read:', error));
        }
    });

    document.addEventListener('click', (e) => {
        if (!notificationLink.contains(e.target) && !notificationDropdown.contains(e.target)) {
            notificationDropdown.classList.remove('show');
        }
    });
});