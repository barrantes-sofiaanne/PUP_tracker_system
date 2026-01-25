document.addEventListener("DOMContentLoaded", () => {
    const viewSanctionDetailsModal = document.getElementById('viewSanctionDetailsModal');
    const approveSanctionForm = document.getElementById('approveSanctionForm');
    const approveSanctionModalMessage = document.getElementById('approveSanctionModalMessage');
    const addSanctionModal = document.getElementById('addSanctionModal');
    const addSanctionForm = document.getElementById('addSanctionForm');
    const editSanctionModal = document.getElementById('editSanctionModal');
    const editSanctionForm = document.getElementById('editSanctionForm');
    const deleteConfirmationModal = document.getElementById('deleteConfirmationModal');
    const deleteSanctionForm = document.getElementById('deleteSanctionForm');
    const violationSelectModal = document.getElementById('violation_type_id_sanction_modal');

    const openModal = (modalElement) => {
        if (modalElement) modalElement.style.display = "flex";
    };

    const closeModal = (modalElement) => {
        if (modalElement) {
            modalElement.style.display = "none";
            if (modalElement.id === 'addSanctionModal') {
                if (violationSelectModal) {
                    violationSelectModal.disabled = false;
                }
            }
        }
    };

    document.body.addEventListener('click', function(e) {
        if (e.target.classList.contains('close-modal-button')) {
            const modalId = e.target.dataset.modal;
            const modal = document.getElementById(modalId);
            if (modal) closeModal(modal);
        }
        if (e.target.classList.contains('modal')) {
            closeModal(e.target);
        }
    });

    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            const targetTab = tab.dataset.tab;
            const searchParams = new URLSearchParams(window.location.search);
            const searchQuery = searchParams.get('search') || '';

            let newUrl = `?tab=${targetTab}`;
            if (targetTab === 'sanction-compliance') {
                newUrl += '&status_filter=All';
            }
            if(searchQuery) {
                newUrl += `&search=${encodeURIComponent(searchQuery)}`;
            }
            
            window.location.href = newUrl;
        });
    });

    document.querySelector('#sanction-request')?.addEventListener('click', (e) => {
        const viewBtn = e.target.closest('.view-manage-btn');
        if (viewBtn) {
            document.getElementById('detailStudentName').textContent = viewBtn.dataset.studentName;
            document.getElementById('detailStudentNumber').textContent = viewBtn.dataset.studentNumber;
            document.getElementById('detailCourseYearSection').textContent = viewBtn.dataset.courseYearSection;
            document.getElementById('detailViolationType').textContent = viewBtn.dataset.violationType;
            document.getElementById('detailDisciplinarySanction').textContent = viewBtn.dataset.disciplinarySanction || 'N/A';
            document.getElementById('detailOffenseLevel').textContent = viewBtn.dataset.offenseLevel || 'N/A';
            document.getElementById('detailDateRequested').textContent = viewBtn.dataset.dateRequested;
            document.getElementById('approveRequestId').value = viewBtn.dataset.requestId;
            document.getElementById('approveStudentNumber').value = viewBtn.dataset.studentNumber;
            document.getElementById('approveViolationTypeId').value = viewBtn.dataset.violationTypeId;
            document.getElementById('approveAssignedSanctionId').value = viewBtn.dataset.assignedSanctionId;
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('deadlineDate').setAttribute('min', today);
            document.getElementById('deadlineDate').value = today;
            openModal(viewSanctionDetailsModal);
            if (approveSanctionModalMessage) approveSanctionModalMessage.style.display = 'none';
        }
    });

    document.querySelector('#sanction-compliance')?.addEventListener('click', async (e) => {
        const updateBtn = e.target.closest('.update-status-btn');
        if (updateBtn) {
            const recordId = updateBtn.dataset.recordId;
            const newStatus = updateBtn.dataset.newStatus;
            const studentNumber = updateBtn.dataset.studentNumber;
            const originalButtonContent = updateBtn.innerHTML;
            updateBtn.disabled = true;
            updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            try {
                const formData = new FormData();
                formData.append('update_sanction_status', '1');
                formData.append('record_id', recordId);
                formData.append('new_status', newStatus);
                formData.append('student_number', studentNumber);
                const response = await fetch(window.location.pathname, { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(result.message || 'An error occurred.', 'error');
                    updateBtn.disabled = false;
                    updateBtn.innerHTML = originalButtonContent;
                }
            } catch (error) {
                showToast('A network error occurred.', 'error');
                updateBtn.disabled = false;
                updateBtn.innerHTML = originalButtonContent;
            }
        }
    });

    if (approveSanctionForm) {
        approveSanctionForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitButton = approveSanctionForm.querySelector('button[type="submit"]');
            const originalButtonContent = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Approving...';
            try {
                const formData = new FormData(approveSanctionForm);
                const response = await fetch(window.location.pathname, { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    closeModal(viewSanctionDetailsModal);
                    showToast(result.message, 'success');
                    setTimeout(() => { window.location.href = window.location.pathname + '?tab=sanction-request' }, 1500);
                } else {
                    if (approveSanctionModalMessage) {
                        approveSanctionModalMessage.textContent = result.message || 'An unknown error occurred.';
                        approveSanctionModalMessage.className = 'modal-message error-message';
                        approveSanctionModalMessage.style.display = 'block';
                    }
                }
            } catch (error) {
                if (approveSanctionModalMessage) {
                    approveSanctionModalMessage.textContent = 'A network error occurred. Please try again.';
                    approveSanctionModalMessage.className = 'modal-message error-message';
                    approveSanctionModalMessage.style.display = 'block';
                }
            } finally {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonContent;
            }
        });
    }

    document.querySelectorAll('#sanction-config .accordion-header').forEach(header => {
        header.addEventListener('click', async () => {
            const content = header.nextElementSibling;
            const body = content.querySelector('.sanction-table-body');
            const item = header.closest('.accordion-item');
            item.classList.toggle('active');
            if (item.classList.contains('active')) {
                content.style.maxHeight = content.scrollHeight + "px";
                if (body.dataset.loaded !== 'true') {
                    body.innerHTML = "<tr><td colspan='3' class='no-records-cell'><i class='fas fa-spinner fa-spin'></i> Loading...</td></tr>";
                    const violationTypeId = header.dataset.violationTypeId;
                    try {
                        const response = await fetch(`?action=get_sanctions_for_violation_type&violation_type_id=${violationTypeId}`);
                        const result = await response.json();
                        if (result.success) {
                            renderDisciplinarySanctionTable(body, result.sanctions);
                            body.dataset.loaded = 'true';
                        } else {
                            body.innerHTML = `<tr><td colspan='3' class='no-records-cell error-text'>${result.message}</td></tr>`;
                        }
                    } catch (err) {
                        body.innerHTML = "<tr><td colspan='3' class='no-records-cell error-text'>Failed to load sanctions.</td></tr>";
                    }
                }
            } else {
                content.style.maxHeight = null;
            }
        });
    });

    const renderDisciplinarySanctionTable = (tableBody, sanctions) => {
        tableBody.innerHTML = '';
        if (sanctions.length === 0) {
            tableBody.innerHTML = "<tr><td colspan='3' class='no-records-cell'>No sanctions defined for this violation.</td></tr>";
            return;
        }
        sanctions.forEach(sanction => {
            const row = tableBody.insertRow();
            row.innerHTML = `
                <td>${sanction.offense_level}</td>
                <td class="text-wrap-content">${sanction.disciplinary_sanction}</td>
                <td class="actions-column">
                    <button class='btn btn-primary btn-sm edit-sanction-btn' data-id='${sanction.disciplinary_sanction_id}'><i class="fas fa-edit"></i> Update</button>
                    <button class='btn btn-danger btn-sm delete-sanction-btn' data-id='${sanction.disciplinary_sanction_id}' data-offense='${sanction.offense_level}' data-sanction='${sanction.disciplinary_sanction}'><i class="fas fa-trash"></i> Delete</button>
                </td>
            `;
        });
    }
    
    document.querySelector('#sanction-config')?.addEventListener('click', async (e) => {
        const addBtn = e.target.closest('.add-sanction-btn');
        const editBtn = e.target.closest('.edit-sanction-btn');
        const deleteBtn = e.target.closest('.delete-sanction-btn');

        if (addBtn) {
            e.preventDefault();
            addSanctionForm.reset();
            document.getElementById('addSanctionModalMessage').style.display = 'none';

            const violationTypeId = addBtn.dataset.violationTypeId;
            const violationTypeName = addBtn.dataset.violationTypeName;
            
            if (violationTypeId && violationSelectModal) {
                violationSelectModal.value = violationTypeId;
                violationSelectModal.disabled = true;
                document.getElementById('add_violation_type_name_hidden').value = violationTypeName;
            } else if (violationSelectModal) {
                violationSelectModal.value = "";
                violationSelectModal.disabled = false;
                document.getElementById('add_violation_type_name_hidden').value = "";
            }
            openModal(addSanctionModal);
        }

        if (editBtn) {
            e.preventDefault();
            editSanctionForm.reset();
            document.getElementById('editSanctionModalMessage').style.display = 'none';
            const sanctionId = editBtn.dataset.id;
            const response = await fetch(`?action=get_disciplinary_sanction_details&id=${sanctionId}`);
            const result = await response.json();
            if (result.success && result.data) {
                const data = result.data;
                document.getElementById('edit_disciplinary_sanction_id').value = data.disciplinary_sanction_id;
                document.getElementById('edit_violation_type_id_sanction_modal').value = data.violation_type_id;
                document.getElementById('edit_violation_type_name_hidden').value = data.violation_type_name;
                document.getElementById('editSanctionViolationName').textContent = data.violation_type_name;
                document.getElementById('edit_offense_level_sanction_modal').value = data.offense_level;
                document.getElementById('edit_disciplinary_sanction_text').value = data.disciplinary_sanction;
                openModal(editSanctionModal);
            } else {
                showToast(result.message || 'Could not fetch sanction details.', 'error');
            }
        }
        
        if (deleteBtn) {
            const sanctionId = deleteBtn.dataset.id;
            const accordionHeader = deleteBtn.closest('.accordion-item').querySelector('.accordion-header');
            document.getElementById('delete_disciplinary_sanction_id').value = sanctionId;
            document.getElementById('delete_violation_type_id_hidden').value = accordionHeader.dataset.violationTypeId;
            document.getElementById('delete_violation_type_name_hidden').value = accordionHeader.dataset.violationTypeName;
            document.getElementById('delete_offense_level_hidden').value = deleteBtn.dataset.offense;
            document.getElementById('delete_sanction_details_hidden').value = deleteBtn.dataset.sanction;
            openModal(deleteConfirmationModal);
        }
    });
    
    if (violationSelectModal) {
        violationSelectModal.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const nameInput = document.getElementById('add_violation_type_name_hidden');
            if (selectedOption && nameInput) {
                nameInput.value = selectedOption.dataset.name || '';
            }
        });
    }

    const handleSanctionFormSubmit = async (form, modal, messageElementId) => {
        const submitButton = form.querySelector('button[type="submit"]');
        const originalButtonContent = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        try {
            const formData = new FormData(form);
            const response = await fetch(window.location.pathname, { method: 'POST', body: formData });
            const result = await response.json();
            const messageElement = document.getElementById(messageElementId);
            if (result.success) {
                closeModal(modal);
                showToast(result.message, 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                if (messageElement) {
                    messageElement.textContent = result.message || 'An unknown error occurred.';
                    messageElement.className = 'modal-message error-message';
                    messageElement.style.display = 'block';
                }
            }
        } catch (error) {
            const messageElement = document.getElementById(messageElementId);
            if (messageElement) {
                messageElement.textContent = 'A network error occurred. Please try again.';
                messageElement.className = 'modal-message error-message';
                messageElement.style.display = 'block';
            }
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonContent;
        }
    };

    addSanctionForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        handleSanctionFormSubmit(addSanctionForm, addSanctionModal, 'addSanctionModalMessage');
    });

    editSanctionForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        handleSanctionFormSubmit(editSanctionForm, editSanctionModal, 'editSanctionModalMessage');
    });

    if (deleteSanctionForm) {
        deleteSanctionForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitButton = deleteSanctionForm.querySelector('button[type="submit"]');
            const originalButtonContent = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
            try {
                const formData = new FormData(deleteSanctionForm);
                const response = await fetch(window.location.pathname, { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    closeModal(deleteConfirmationModal);
                    showToast(result.message, 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    document.getElementById('deleteModalErrorMessage').textContent = result.message || 'An error occurred.';
                    document.getElementById('deleteModalErrorMessage').style.display = 'block';
                }
            } catch (error) {
                document.getElementById('deleteModalErrorMessage').textContent = 'A network error occurred.';
                document.getElementById('deleteModalErrorMessage').style.display = 'block';
            } finally {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonContent;
            }
        });
    }
    
    function showToast(message, type = 'success', duration = 3000) {
        const toast = document.getElementById('toast-notification');
        if (!toast) return;
        toast.textContent = message;
        toast.className = `toast show ${type}`;
        setTimeout(() => { toast.classList.remove('show'); }, duration);
    }
    
    const violationSearchInput = document.getElementById('violation-type-search');
    if (violationSearchInput) {
        violationSearchInput.addEventListener('keyup', () => {
            const filter = violationSearchInput.value.toUpperCase();
            document.querySelectorAll('.violation-type-item').forEach(item => {
                const name = item.dataset.violationTypeName;
                if (name.toUpperCase().indexOf(filter) > -1) {
                    item.style.display = "";
                } else {
                    item.style.display = "none";
                }
            });
        });
    }
});