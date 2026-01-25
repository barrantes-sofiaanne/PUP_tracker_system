document.addEventListener('DOMContentLoaded', function() {
    // Modals
    const editModal = document.getElementById('announcementModal');
    const viewModal = document.getElementById('viewAnnouncementModal');
    const confirmModal = document.getElementById('confirmModal');

    // Add/Edit Modal Elements
    const addBtn = document.getElementById('addAnnouncementBtn');
    const editCloseBtn = editModal.querySelector('.close-btn');
    const cancelBtn = editModal.querySelector('.cancel-btn');
    const form = document.getElementById('announcementForm');
    const modalTitle = document.getElementById('modalTitle');
    const formAction = document.getElementById('formAction');
    const announcementIdInput = document.getElementById('announcementId');
    const contentInput = document.getElementById('content-input');
    const attachmentInput = document.getElementById('attachment');
    const clearFileBtn = document.getElementById('clearFileBtn');
    const existingAttachmentInfo = document.getElementById('existing-attachment-info');
    const existingAttachmentLink = document.getElementById('existing-attachment-link');
    const removeAttachmentCheckbox = document.querySelector('input[name="remove_attachment"]');

    // View Modal Elements
    const viewModalTitle = document.getElementById('viewModalTitle');
    const viewModalBody = document.getElementById('viewModalBody');
    const viewCloseBtn = viewModal.querySelector('.close-btn');

    // Confirm Modal Elements
    const confirmModalTitle = document.getElementById('confirmModalTitle');
    const confirmModalMessage = document.getElementById('confirmModalMessage');
    const confirmOkBtn = document.getElementById('confirmOkBtn');
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');
    const confirmCloseBtn = confirmModal.querySelector('.close-btn');

    // List & Toast Containers
    const announcementList = document.getElementById('announcementList');
    const toastContainer = document.getElementById('toast-container');

    const quill = new Quill('#editor-container', {
        theme: 'snow',
        modules: { toolbar: [['bold', 'italic', 'underline', 'link'], [{ 'list': 'ordered'}, { 'list': 'bullet' }]] }
    });

    const showToast = (message, type = 'success') => {
        const toast = document.createElement('div');
        toast.className = `toast-message ${type}`;
        toast.textContent = message;
        toastContainer.appendChild(toast);
        setTimeout(() => toast.remove(), 3500);
    };

    const openModal = (modal) => modal.style.display = 'block';
    const closeModal = (modal) => modal.style.display = 'none';

    const resetEditModal = () => {
        form.reset();
        quill.root.innerHTML = '';
        attachmentInput.value = '';
        clearFileBtn.style.display = 'none';
        existingAttachmentInfo.style.display = 'none';
        if(removeAttachmentCheckbox) removeAttachmentCheckbox.checked = false;
    };
    
    if (addBtn) addBtn.onclick = () => {
        resetEditModal();
        modalTitle.textContent = 'Add New Announcement';
        formAction.value = 'add';
        announcementIdInput.value = '';
        openModal(editModal);
    };
    
    if (editCloseBtn) editCloseBtn.onclick = () => closeModal(editModal);
    if (cancelBtn) cancelBtn.onclick = () => closeModal(editModal);
    if (viewCloseBtn) viewCloseBtn.onclick = () => closeModal(viewModal);
    if (confirmCloseBtn) confirmCloseBtn.onclick = () => closeModal(confirmModal);
    if (confirmCancelBtn) confirmCancelBtn.onclick = () => closeModal(confirmModal);

    attachmentInput.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            const file = e.target.files[0];
            const MAX_SIZE = 5 * 1024 * 1024; // 5MB in bytes
            const ALLOWED_EXTENSIONS = ['jpeg', 'jpg', 'png', 'gif', 'pdf'];
            const fileExtension = file.name.split('.').pop().toLowerCase();

            if (file.size > MAX_SIZE) {
                showToast('File is too large. Maximum size is 5MB.', 'error');
                e.target.value = '';
                clearFileBtn.style.display = 'none';
                return;
            }

            if (!ALLOWED_EXTENSIONS.includes(fileExtension)) {
                showToast('Invalid file type. Only images and PDFs are allowed.', 'error');
                e.target.value = '';
                clearFileBtn.style.display = 'none';
                return;
            }
            clearFileBtn.style.display = 'block';
        } else {
            clearFileBtn.style.display = 'none';
        }
    });

    clearFileBtn.addEventListener('click', function() {
        attachmentInput.value = '';
        this.style.display = 'none';
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        contentInput.value = quill.root.innerHTML;
        const formData = new FormData(form);

        fetch('admin_announcements.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) {
                closeModal(editModal);
                setTimeout(() => window.location.reload(), 1500);
            }
        });
    });

    if (announcementList) {
        announcementList.addEventListener('click', function(e) {
            const listItem = e.target.closest('.list-item');
            if (!listItem) return;

            const id = listItem.dataset.id;
            const title = listItem.querySelector('.col-title span').textContent.trim();
            const actionTarget = e.target.closest('.icon-btn');

            const handleAction = (action) => {
                switch(action) {
                    case 'edit':
                        resetEditModal();
                        modalTitle.textContent = 'Edit Announcement';
                        formAction.value = 'edit';
                        announcementIdInput.value = id;
                        fetch(`admin_announcements.php?fetch=true&id=${id}`)
                            .then(res => res.json()).then(data => {
                                if (data) {
                                    form.querySelector('#title').value = data.title;
                                    quill.root.innerHTML = data.content;
                                    if (data.attachment_path) {
                                        existingAttachmentLink.href = `../uploads/announcements/${data.attachment_path}`;
                                        existingAttachmentLink.textContent = data.attachment_path;
                                        existingAttachmentInfo.style.display = 'block';
                                    }
                                    openModal(editModal);
                                }
                            });
                        break;
                    case 'delete':
                        confirmModalTitle.textContent = 'Delete Announcement';
                        confirmModalMessage.textContent = `Are you sure you want to permanently delete "${title}"? This action cannot be undone.`;
                        confirmOkBtn.textContent = 'Delete';
                        confirmOkBtn.className = 'action-btn delete';
                        openModal(confirmModal);

                        confirmOkBtn.onclick = () => {
                            const formData = new FormData();
                            formData.append('action', 'delete');
                            formData.append('announcement_id', id);
                            fetch('admin_announcements.php', { method: 'POST', body: formData })
                                .then(res => res.json()).then(data => {
                                    showToast(data.message, data.success ? 'success' : 'error');
                                    if (data.success) listItem.remove();
                                });
                            closeModal(confirmModal);
                        };
                        break;
                    case 'view':
                        fetch(`admin_announcements.php?fetch=true&id=${id}`)
                            .then(res => res.json()).then(data => {
                                if (data) {
                                    viewModalTitle.textContent = data.title;
                                    let contentHTML = data.content;
                                    if (data.attachment_path) {
                                        const filePath = `../uploads/announcements/${data.attachment_path}`;
                                        const isImage = /\.(jpe?g|png|gif)$/i.test(data.attachment_path);
                                        contentHTML += '<hr>';
                                        if (isImage) {
                                            contentHTML += `<img src="${filePath}" alt="Attachment" class="view-attachment-image">`;
                                        } else {
                                            contentHTML += `<a href="${filePath}" target="_blank" class="view-attachment-link">Download Attached File</a>`;
                                        }
                                    }
                                    viewModalBody.innerHTML = contentHTML;
                                    openModal(viewModal);
                                }
                            });
                        break;
                }
            };

            if (actionTarget) {
                if(actionTarget.classList.contains('edit-btn')) handleAction('edit');
                else if(actionTarget.classList.contains('delete-btn')) handleAction('delete');
                else handleAction('view');
            } else {
                handleAction('view');
            }
        });
    }

    window.addEventListener('click', function(e) {
        if (e.target === editModal) closeModal(editModal);
        if (e.target === viewModal) closeModal(viewModal);
        if (e.target === confirmModal) closeModal(confirmModal);
    });
});