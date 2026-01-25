document.addEventListener("DOMContentLoaded", () => {
    const notificationLink = document.getElementById('notificationLinkToggle');
    const notificationDropdown = document.getElementById('notificationsDropdownContent');
    const notificationCountBadge = notificationLink?.querySelector('.notification-count');

    notificationLink?.addEventListener('click', (e) => {
        e.preventDefault();
        const isVisible = notificationDropdown.classList.toggle('show');

        if (isVisible && notificationCountBadge && notificationCountBadge.style.display !== 'none') {
            const formData = new FormData();
            formData.append('action', 'mark_admin_notifs_read');
            
            fetch(window.location.href, {
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
        if (!notificationLink?.contains(e.target) && !notificationDropdown?.contains(e.target)) {
            notificationDropdown?.classList.remove('show');
        }
    });
});