function showToast(message, type = 'success', duration = 3000, position = 'top-center') {
    const toast = document.getElementById('toast-notification');
    if (!toast) {
        console.error('Toast element not found!');
        return;
    }
    toast.textContent = message;
    toast.className = 'toast';
    toast.classList.add(type);
    toast.classList.remove('top-center', 'bottom-center');
    if (position === 'bottom-center') {
        toast.classList.add('bottom-center');
    } else {
        toast.classList.add('top-center');
    }
    toast.classList.remove('show');
    void toast.offsetWidth;
    toast.classList.add('show');
    setTimeout(() => {
        toast.classList.remove('show');
    }, duration);
}

document.addEventListener("DOMContentLoaded", () => {
    const studentViolationModal = document.getElementById("modal");
    const addStudentViolationBtn = document.getElementById("addViolationBtn");
    const closeStudentViolationModalBtn = document.getElementById("closeModal");
    const studentViolationForm = document.getElementById("violationForm");
    const studentModalMessageDiv = document.getElementById("modalMessage");
    const closeStudentModalInHeader = document.querySelector("#modal .head-modal .close-modal-button");
    const searchStudentStepDiv = document.getElementById("searchStudentStep");
    const studentNumberSearchInput = document.getElementById("studentNumberSearchInput");
    const executeStudentSearchBtn = document.getElementById("executeStudentSearchBtn");
    const studentSearchResultArea = document.getElementById("studentSearchResultArea");
    const searchLoadingIndicator = document.getElementById("searchLoadingIndicator");
    const confirmedStudentInfoDiv = document.getElementById("confirmedStudentInfo");
    const studentNumberInputForForm = document.getElementById("studentNumber");
    const violationCategorySelect = document.getElementById("violationCategory");
    const violationTypeSelect = document.getElementById("violationType");
    const violationRemarksTextarea = document.getElementById("violationRemarks");
    const changeStudentBtn = document.getElementById("changeStudentBtn");
    const addViolationCategoryModal = document.getElementById("addViolationCategoryModal");
    const addViolationCategoryBtn = document.getElementById("addViolationCategoryBtn");
    const closeAddViolationCategoryModalBtn = document.querySelector("#addViolationCategoryModal .close-modal-category-button");
    const addViolationCategoryForm = document.getElementById("addViolationCategoryForm");
    const addViolationCategoryModalMessageDiv = document.getElementById("addViolationCategoryModalMessage");
    const addCategoryStep1 = document.getElementById("addCategoryStep1");
    const addCategoryStep2 = document.getElementById("addCategoryStep2");
    const nextToCategoryStep2Btn = document.getElementById("nextToCategoryStep2");
    const backToCategoryStep1Btn = document.getElementById("backToCategoryStep1");
    const cancelCategoryStep1Btn = document.getElementById("cancelCategoryStep1");
    const cancelCategoryStep2Btn = document.getElementById("cancelCategoryStep2");
    const addTypeToCategoryModal = document.getElementById("addTypeToCategoryModal");
    const closeAddTypeToCategoryModalBtn = document.getElementById("closeAddTypeToCategoryModal");
    const addTypeToCategoryForm = document.getElementById("addTypeToCategoryForm");
    const addTypeToCategoryModalMessageDiv = document.getElementById("addTypeToCategoryModalMessage");
    const closeAddTypeToCategoryModalInHeader = document.querySelector("#addTypeToCategoryModal .head-modal .close-modal-add-type-button");
    const editViolationTypeModal = document.getElementById("editViolationTypeModal");
    const editViolationTypeForm = document.getElementById("editViolationTypeForm");
    const editViolationTypeModalMessageDiv = document.getElementById("editViolationTypeModalMessage");
    const closeEditViolationTypeModalBtn = document.getElementById("cancelEditViolationTypeModal");
    const closeEditModalInHeader = document.querySelector("#editViolationTypeModal .head-modal .close-modal-edit-button");
    const deleteViolationTypeModal = document.getElementById("deleteViolationTypeModal");
    const deleteViolationTypeModalMessageDiv = document.getElementById("deleteViolationTypeModalMessage");
    const closeDeleteViolationTypeModalBtn = document.getElementById("cancelDeleteViolationTypeModal");
    const closeDeleteModalInHeader = document.querySelector("#deleteViolationTypeModal .head-modal .close-modal-delete-button");
    const confirmDeleteViolationTypeBtn = document.getElementById("confirmDeleteViolationTypeBtn");
    const refreshTableBtn = document.getElementById('refreshTableBtn');
    const tableSpinner = document.getElementById('tableSpinner');
    const filterForm = document.getElementById('filter-form');
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');
    const accordionHeaders = document.querySelectorAll('.accordion-header');
    let currentViolationTypeIdToDelete = null;
    let activeRowActionButtonsContainer = null;

    function showTableSpinner() {
        if (tableSpinner) {
            tableSpinner.style.display = 'flex';
        }
    }

    function displayModalMessage(modalMessageDiv, message, type = 'error') {
        if (modalMessageDiv) {
            modalMessageDiv.textContent = message;
            modalMessageDiv.className = `modal-message ${type}-message`;
            modalMessageDiv.style.display = 'block';
        } else {
            alert(message);
        }
    }

    function clearModalMessage(modalMessageDiv) {
        if (modalMessageDiv) {
            modalMessageDiv.textContent = '';
            modalMessageDiv.style.display = 'none';
        }
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const targetTab = tab.dataset.tab;
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            tabContents.forEach(content => {
                content.style.display = content.id === targetTab ? 'block' : 'none';
            });
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('tab', targetTab);
            currentUrl.searchParams.delete('violation_category');
            currentUrl.searchParams.delete('course_id');
            currentUrl.searchParams.delete('year_id');
            currentUrl.searchParams.delete('search');
            window.history.pushState({ path: currentUrl.href }, '', currentUrl.href);
        });
    });

    accordionHeaders.forEach(header => {
        header.addEventListener('click', () => {
            const accordionItem = header.parentElement;
            const accordionContent = header.nextElementSibling;
            document.querySelectorAll('.accordion-item.active').forEach(openItem => {
                if (openItem !== accordionItem) {
                    openItem.classList.remove('active');
                    openItem.querySelector('.accordion-content').style.maxHeight = null;
                }
            });
            accordionItem.classList.toggle('active');
            accordionContent.style.maxHeight = accordionContent.style.maxHeight ? null : accordionContent.scrollHeight + "px";
        });
    });

    document.querySelectorAll('.accordion-item.active .accordion-content').forEach(content => {
        content.style.maxHeight = content.scrollHeight + "px";
    });

    function showSearchStep() {
        searchStudentStepDiv.style.display = "block";
        studentViolationForm.style.display = "none";
        studentModalMessageDiv.style.display = "none";
        studentModalMessageDiv.textContent = "";
        studentNumberSearchInput.value = "";
        studentSearchResultArea.innerHTML = "";
        studentSearchResultArea.style.display = "none";
        searchLoadingIndicator.style.display = "none";
        studentNumberSearchInput.disabled = false;
        executeStudentSearchBtn.disabled = false;
        studentNumberSearchInput.focus();
    }

    function showViolationFormStep(student) {
        searchStudentStepDiv.style.display = "none";
        studentViolationForm.style.display = "block";
        studentModalMessageDiv.style.display = "none";
        studentModalMessageDiv.textContent = "";
        confirmedStudentInfoDiv.innerHTML = `
            <p><strong>Student:</strong> ${student.first_name} ${student.middle_name || ''} ${student.last_name}</p>
            <p><strong>Number:</strong> ${student.student_number}</p>
            <p><strong>Course:</strong> ${student.course_name || 'N/A'} (${student.year || 'N/A'}) | <strong>Section:</strong> ${student.section_name || 'N/A'}</p>
        `;
        studentNumberInputForForm.value = student.student_number;
        violationCategorySelect.value = "";
        violationTypeSelect.innerHTML = '<option value="">Select Category First</option>';
        violationTypeSelect.disabled = true;
        if (violationRemarksTextarea) {
            violationRemarksTextarea.value = "";
        }
        violationCategorySelect.focus();
    }

    if (addStudentViolationBtn) {
        addStudentViolationBtn.addEventListener("click", () => {
            if (studentViolationModal) {
                studentViolationModal.style.display = "block";
                showSearchStep();
            }
        });
    }

    function closeEntireStudentViolationModal() {
        if (studentViolationModal) {
            studentViolationModal.style.display = "none";
        }
        studentViolationForm.reset();
        studentNumberSearchInput.value = "";
        studentSearchResultArea.innerHTML = "";
        studentSearchResultArea.style.display = "none";
        searchLoadingIndicator.style.display = "none";
        violationCategorySelect.value = "";
        violationTypeSelect.innerHTML = '<option value="">Select Category First</option>';
        violationTypeSelect.disabled = true;
        if (violationRemarksTextarea) {
            violationRemarksTextarea.value = "";
        }
        clearModalMessage(studentModalMessageDiv);
    }

    if (closeStudentModalInHeader) {
        closeStudentModalInHeader.addEventListener("click", closeEntireStudentViolationModal);
    }
    if (closeStudentViolationModalBtn) {
        closeStudentViolationModalBtn.addEventListener("click", closeEntireStudentViolationModal);
    }
    if (changeStudentBtn) {
        changeStudentBtn.addEventListener("click", () => {
            showSearchStep();
        });
    }

    if (executeStudentSearchBtn) {
        executeStudentSearchBtn.addEventListener("click", async () => {
            const studentSearchNumber = studentNumberSearchInput.value.trim();
            if (!studentSearchNumber) {
                displayModalMessage(studentModalMessageDiv, "Please enter a Student Number to search.", 'error');
                return;
            }
            clearModalMessage(studentModalMessageDiv);
            studentSearchResultArea.style.display = "none";
            studentSearchResultArea.innerHTML = "";
            searchLoadingIndicator.style.display = "block";
            executeStudentSearchBtn.disabled = true;
            studentNumberSearchInput.disabled = true;
            try {
                const response = await fetch(`${window.location.pathname}?action=search_student_for_violation&student_search_number=${encodeURIComponent(studentSearchNumber)}`);
                const result = await response.json();
                if (result.success && result.student) {
                    studentSearchResultArea.innerHTML = `
                        <h4>Student Found:</h4>
                        <p><strong>Number:</strong> ${result.student.student_number}</p>
                        <p><strong>Name:</strong> ${result.student.first_name} ${result.student.middle_name || ''} ${result.student.last_name}</p>
                        <p><strong>Course:</strong> ${result.student.course_name || 'N/A'} - ${result.student.year || 'N/A'}</p>
                        <p><strong>Section:</strong> ${result.student.section_name || 'N/A'}</p>
                        <button type="button" id="confirmStudentSelectionBtn" class="modal-button-confirm" data-student-json='${JSON.stringify(result.student)}' style="margin-top:10px;">
                            <i class="fas fa-user-check"></i> Use This Student
                        </button>
                    `;
                    studentSearchResultArea.style.display = "block";
                    document.getElementById("confirmStudentSelectionBtn").addEventListener("click", function() {
                        const studentData = JSON.parse(this.dataset.studentJson);
                        showViolationFormStep(studentData);
                    });
                } else {
                    displayModalMessage(studentModalMessageDiv, result.message || "Could not find student or error occurred.", 'error');
                }
            } catch (error) {
                console.error('Student search error:', error);
                displayModalMessage(studentModalMessageDiv, "Search failed: " + error.message, 'error');
            } finally {
                searchLoadingIndicator.style.display = "none";
                executeStudentSearchBtn.disabled = false;
                studentNumberSearchInput.disabled = false;
            }
        });
    }

    if (studentNumberSearchInput && executeStudentSearchBtn) {
        studentNumberSearchInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter' || event.keyCode === 13) {
                event.preventDefault();
                executeStudentSearchBtn.click();
            }
        });
    }

    if (violationCategorySelect) {
        violationCategorySelect.addEventListener('change', async function() {
            const categoryId = this.value;
            violationTypeSelect.innerHTML = '<option value="">Loading types...</option>';
            violationTypeSelect.disabled = true;
            studentModalMessageDiv.style.display = "none";
            if (!categoryId) {
                violationTypeSelect.innerHTML = '<option value="">Select Category First</option>';
                return;
            }
            try {
                const response = await fetch(`${window.location.pathname}?action=get_violation_types_for_category&category_id=${categoryId}`);
                const result = await response.json();
                if (result.success && result.types) {
                    violationTypeSelect.innerHTML = '<option value="">Select Violation Type</option>';
                    if (result.types.length > 0) {
                        result.types.forEach(type => {
                            const option = document.createElement('option');
                            option.value = type.violation_type_id;
                            option.textContent = type.violation_type;
                            violationTypeSelect.appendChild(option);
                        });
                        violationTypeSelect.disabled = false;
                    } else {
                        violationTypeSelect.innerHTML = '<option value="">No types in this category</option>';
                    }
                } else {
                    violationTypeSelect.innerHTML = '<option value="">Error loading types</option>';
                    displayModalMessage(studentModalMessageDiv, result.message || 'Could not load violation types.', 'error');
                }
            } catch (error) {
                console.error('Error fetching violation types:', error);
                violationTypeSelect.innerHTML = '<option value="">Error loading types</option>';
                displayModalMessage(studentModalMessageDiv, 'Network error: Could not load violation types.', 'error');
            }
        });
    }

    if (studentViolationForm) {
        studentViolationForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            clearModalMessage(studentModalMessageDiv);
            const studentNumberVal = studentNumberInputForForm.value.trim();
            const violationTypeVal = violationTypeSelect.value;
            const violationCategoryVal = violationCategorySelect.value;
            if (!studentNumberVal) {
                displayModalMessage(studentModalMessageDiv, "Student Number is missing. Please select a student first.", 'error');
                showSearchStep();
                return;
            }
            if (!violationCategoryVal) {
                displayModalMessage(studentModalMessageDiv, "Please select a Violation Category.", 'error');
                return;
            }
            if (!violationTypeVal) {
                displayModalMessage(studentModalMessageDiv, "Please select a Violation Type.", 'error');
                return;
            }
            const formData = new FormData(studentViolationForm);
            const submitButton = studentViolationForm.querySelector('button[type="submit"].modal-button-add');
            const originalButtonContent = submitButton ? submitButton.innerHTML : '';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            }
            try {
                const response = await fetch(studentViolationForm.action, { method: 'POST', body: formData });
                if (!response.ok) {
                    let errorMsg = `Server error: ${response.status}`;
                    try {
                        const errorData = await response.json();
                        if (errorData && errorData.message) errorMsg = errorData.message;
                    } catch (jsonError) {}
                    throw new Error(errorMsg);
                }
                const result = await response.json();
                if (result.success) {
                    closeEntireStudentViolationModal();
                    showToast(result.message, 'success', 3000);
                    showTableSpinner();
                    setTimeout(() => {
                        window.location.href = window.location.pathname + '?tab=Violation';
                    }, 500);
                } else {
                    displayModalMessage(studentModalMessageDiv, result.message || "An error occurred.", 'error');
                }
            } catch (error) {
                console.error('Student violation form submission error:', error);
                displayModalMessage(studentModalMessageDiv, "Submission failed: " + error.message, 'error');
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonContent;
                }
            }
        });
    }

    if (addViolationCategoryBtn) {
        addViolationCategoryBtn.addEventListener("click", () => {
            if (addViolationCategoryModal) {
                addViolationCategoryModal.style.display = "block";
                addViolationCategoryForm.reset();
                clearModalMessage(addViolationCategoryModalMessageDiv);
                addCategoryStep1.style.display = 'block';
                addCategoryStep2.style.display = 'none';
                document.getElementById("newCategoryName").focus();
                addInputListenersForCapslock();
            }
        });
    }
    function closeAddViolationCategoryModal() {
        if (addViolationCategoryModal) addViolationCategoryModal.style.display = "none";
        addViolationCategoryForm.reset();
        clearModalMessage(addViolationCategoryModalMessageDiv);
        addCategoryStep1.style.display = 'block';
        addCategoryStep2.style.display = 'none';
    }
    if (closeAddViolationCategoryModalBtn) {
        closeAddViolationCategoryModalBtn.addEventListener("click", closeAddViolationCategoryModal);
    }
    if (cancelCategoryStep1Btn) {
        cancelCategoryStep1Btn.addEventListener("click", closeAddViolationCategoryModal);
    }
    if (cancelCategoryStep2Btn) {
        cancelCategoryStep2Btn.addEventListener("click", closeAddViolationCategoryModal);
    }
    if (nextToCategoryStep2Btn) {
        nextToCategoryStep2Btn.addEventListener("click", () => {
            const newCategoryNameInput = document.getElementById("newCategoryName");
            if (!newCategoryNameInput.value.trim()) {
                displayModalMessage(addViolationCategoryModalMessageDiv, "Please enter Violation Category Name.", 'error');
                return;
            }
            clearModalMessage(addViolationCategoryModalMessageDiv);
            addCategoryStep1.style.display = 'none';
            addCategoryStep2.style.display = 'block';
            document.getElementById("newResolutionNumberCatModal").focus();
        });
    }
    if (backToCategoryStep1Btn) {
        backToCategoryStep1Btn.addEventListener("click", () => {
            clearModalMessage(addViolationCategoryModalMessageDiv);
            addCategoryStep2.style.display = 'none';
            addCategoryStep1.style.display = 'block';
            document.getElementById("newCategoryName").focus();
        });
    }
    document.querySelector('#Configuration').addEventListener('click', (e) => {
        const addTypeBtn = e.target.closest('.add-type-to-category-btn');
        if (addTypeBtn) {
            if (addTypeToCategoryModal) {
                addTypeToCategoryModal.style.display = "block";
                addTypeToCategoryForm.reset();
                clearModalMessage(addTypeToCategoryModalMessageDiv);
                const categoryName = addTypeBtn.dataset.categoryName;
                document.getElementById("existingCategoryName").value = categoryName;
                document.getElementById("newResolutionNumberTypeModal").focus();
                addInputListenersForCapslock();
            }
        }
    });
    function closeAddTypeToCategoryModal() {
        if (addTypeToCategoryModal) addTypeToCategoryModal.style.display = "none";
        addTypeToCategoryForm.reset();
        clearModalMessage(addTypeToCategoryModalMessageDiv);
    }
    if (closeAddTypeToCategoryModalBtn) {
        closeAddTypeToCategoryModalBtn.addEventListener("click", closeAddTypeToCategoryModal);
    }
    if (closeAddTypeToCategoryModalInHeader) {
        closeAddTypeToCategoryModalInHeader.addEventListener("click", closeAddTypeToCategoryModal);
    }
    function openEditViolationTypeModal(details) {
        if (editViolationTypeModal && details) {
            document.getElementById("editViolationTypeId").value = details.violation_type_id;
            document.getElementById("editResolutionNumberConfig").value = details.resolution_number || '';
            document.getElementById("editViolationCategoryConfig").value = details.category_name || '';
            document.getElementById("editViolationTypeConfig").value = details.violation_type || '';
            document.getElementById("editViolationDescriptionConfig").value = details.violation_description || '';
            clearModalMessage(editViolationTypeModalMessageDiv);
            editViolationTypeModal.style.display = "block";
            document.getElementById("editResolutionNumberConfig").focus();
            addInputListenersForCapslock();
        }
    }
    function closeEditViolationTypeModal() {
        if (editViolationTypeModal) editViolationTypeModal.style.display = "none";
        editViolationTypeForm.reset();
        clearModalMessage(editViolationTypeModalMessageDiv);
    }
    if (closeEditViolationTypeModalBtn) {
        closeEditViolationTypeModalBtn.addEventListener("click", closeEditViolationTypeModal);
    }
    if (closeEditModalInHeader) {
        closeEditModalInHeader.addEventListener("click", closeEditViolationTypeModal);
    }
    function openDeleteViolationTypeModal(details) {
        if (deleteViolationTypeModal && details) {
            currentViolationTypeIdToDelete = details.violation_type_id;
            document.getElementById("deleteViolationCategoryDisplay").textContent = details.category_name || 'N/A';
            document.getElementById("deleteViolationTypeDisplay").textContent = details.violation_type || 'N/A';
            document.getElementById("deleteViolationDescriptionDisplay").textContent = details.violation_description || 'N/A';
            clearModalMessage(deleteViolationTypeModalMessageDiv);
            deleteViolationTypeModal.style.display = "block";
        }
    }
    function closeDeleteViolationTypeModal() {
        if (deleteViolationTypeModal) deleteViolationTypeModal.style.display = "none";
        currentViolationTypeIdToDelete = null;
        clearModalMessage(deleteViolationTypeModalMessageDiv);
    }
    if (closeDeleteViolationTypeModalBtn) {
        closeDeleteViolationTypeModalBtn.addEventListener("click", closeDeleteViolationTypeModal);
    }
    if (closeDeleteModalInHeader) {
        closeDeleteModalInHeader.addEventListener("click", closeDeleteViolationTypeModal);
    }
    if (confirmDeleteViolationTypeBtn) {
        confirmDeleteViolationTypeBtn.addEventListener("click", async () => {
            if (currentViolationTypeIdToDelete) {
                const submitButton = confirmDeleteViolationTypeBtn;
                const originalButtonContent = submitButton ? submitButton.innerHTML : '';
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
                }
                try {
                    const formData = new FormData();
                    formData.append('delete_violation_type_id', currentViolationTypeIdToDelete);
                    const response = await fetch(window.location.pathname, { method: 'POST', body: formData });
                    if (!response.ok) { throw new Error(`Server error: ${response.status}`); }
                    const result = await response.json();
                    if (result.success) {
                        closeDeleteViolationTypeModal();
                        showToast(result.message, 'success', 3000, 'bottom-center');
                        window.location.href = window.location.pathname + '?tab=Configuration';
                    } else {
                        displayModalMessage(deleteViolationTypeModalMessageDiv, result.message || "Failed to delete.", 'error');
                    }
                } catch (error) {
                    console.error('Delete submission error:', error);
                    displayModalMessage(deleteViolationTypeModalMessageDiv, "Deletion failed: " + error.message, 'error');
                } finally {
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.innerHTML = originalButtonContent;
                    }
                }
            }
        });
    }
    window.addEventListener("click", (event) => {
        if (event.target === studentViolationModal) { closeEntireStudentViolationModal(); }
        if (event.target === addViolationCategoryModal) { closeAddViolationCategoryModal(); }
        if (event.target === addTypeToCategoryModal) { closeAddTypeToCategoryModal(); }
        if (event.target === editViolationTypeModal) { closeEditViolationTypeModal(); }
        if (event.target === deleteViolationTypeModal) { closeDeleteViolationTypeModal(); }
    });
    document.querySelector('#Configuration').addEventListener('click', async (e) => {
        const editBtn = e.target.closest('.edit-violation-type-btn');
        const deleteBtn = e.target.closest('.delete-violation-type-btn');
        const violationRow = e.target.closest('.violation-type-row');
        if (activeRowActionButtonsContainer && activeRowActionButtonsContainer !== violationRow?.querySelector('.action-buttons-container')) {
            activeRowActionButtonsContainer.style.display = 'none';
        }
        if (violationRow && !editBtn && !deleteBtn) {
            const actionContainer = violationRow.querySelector('.action-buttons-container');
            if (actionContainer) {
                actionContainer.style.display = actionContainer.style.display === 'flex' ? 'none' : 'flex';
                activeRowActionButtonsContainer = actionContainer.style.display === 'flex' ? actionContainer : null;
            }
        }
        if (editBtn) {
            const violationTypeId = editBtn.dataset.id;
            try {
                const response = await fetch(`${window.location.pathname}?action=get_violation_type_details&id=${violationTypeId}`);
                if (!response.ok) { throw new Error(`Server error: ${response.status}`); }
                const result = await response.json();
                if (result.success && result.data) { openEditViolationTypeModal(result.data); } else { showToast(result.message || "Failed to fetch violation details for editing.", 'error'); }
            } catch (error) { console.error('Error fetching violation details:', error); showToast("Error fetching details: " + error.message, 'error'); }
        }
        if (deleteBtn) {
            const violationTypeId = deleteBtn.dataset.id;
            try {
                const response = await fetch(`${window.location.pathname}?action=get_violation_type_details&id=${violationTypeId}`);
                if (!response.ok) { throw new Error(`Server error: ${response.status}`); }
                const result = await response.json();
                if (result.success && result.data) { openDeleteViolationTypeModal(result.data); } else { showToast(result.message || "Failed to fetch violation details for deletion.", 'error'); }
            } catch (error) { console.error('Error fetching violation details:', error); showToast("Error fetching details: " + error.message, 'error'); }
        }
    });
    if (addViolationCategoryForm) {
        addViolationCategoryForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            clearModalMessage(addViolationCategoryModalMessageDiv);
            const newCategoryName = document.getElementById("newCategoryName").value.trim();
            const newResolutionNumber = document.getElementById("newResolutionNumberCatModal").value.trim();
            const newViolationType = document.getElementById("newViolationTypeCatModal").value.trim();
            const newViolationDescription = document.getElementById("newViolationDescriptionCatModal").value.trim();
            if (!newCategoryName || !newResolutionNumber || !newViolationType || !newViolationDescription) {
                displayModalMessage(addViolationCategoryModalMessageDiv, "All fields are required.", 'error');
                return;
            }
            const formData = new FormData();
            formData.append('add_new_category_and_type', '1');
            formData.append('new_category_name', newCategoryName);
            formData.append('new_resolution_number_cat_modal', newResolutionNumber);
            formData.append('new_violation_type_cat_modal', newViolationType);
            formData.append('new_violation_description_cat_modal', newViolationDescription);
            const submitButton = addViolationCategoryForm.querySelector('button[type="submit"]');
            const originalButtonContent = submitButton ? submitButton.innerHTML : '';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publishing...';
            }
            try {
                const response = await fetch(addViolationCategoryForm.action, { method: 'POST', body: formData });
                if (!response.ok) {
                    let errorMsg = `Server error: ${response.status}`;
                    try { const errorData = await response.json(); if (errorData && errorData.message) errorMsg = errorData.message; } catch (jsonError) {}
                    throw new Error(errorMsg);
                }
                const result = await response.json();
                if (result.success) {
                    closeAddViolationCategoryModal();
                    showToast(result.message, 'success', 3000, 'bottom-center');
                    window.location.href = window.location.pathname + '?tab=Configuration';
                } else {
                    displayModalMessage(addViolationCategoryModalMessageDiv, result.message || "An error occurred.", 'error');
                }
            } catch (error) {
                console.error('Add category+type form submission error:', error);
                displayModalMessage(addViolationCategoryModalMessageDiv, "Submission failed: " + error.message, 'error');
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonContent;
                }
            }
        });
    }
    if (addTypeToCategoryForm) {
        addTypeToCategoryForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            clearModalMessage(addTypeToCategoryModalMessageDiv);
            const newResolutionNumber = document.getElementById("newResolutionNumberTypeModal").value.trim();
            const newViolationType = document.getElementById("newViolationTypeTypeModal").value.trim();
            const newViolationDescription = document.getElementById("newViolationDescriptionTypeModal").value.trim();
            const existingCategoryName = document.getElementById("existingCategoryName").value.trim();
            if (!newResolutionNumber || !newViolationType || !newViolationDescription || !existingCategoryName) {
                displayModalMessage(addTypeToCategoryModalMessageDiv, "All fields are required.", 'error');
                return;
            }
            const formData = new FormData();
            formData.append('add_type_to_existing_category', '1');
            formData.append('new_resolution_number_type_modal', newResolutionNumber);
            formData.append('new_violation_type_type_modal', newViolationType);
            formData.append('new_violation_description_type_modal', newViolationDescription);
            formData.append('existing_category_name', existingCategoryName);
            const submitButton = addTypeToCategoryForm.querySelector('button[type="submit"]');
            const originalButtonContent = submitButton ? submitButton.innerHTML : '';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publishing...';
            }
            try {
                const response = await fetch(addTypeToCategoryForm.action, { method: 'POST', body: formData });
                if (!response.ok) {
                    let errorMsg = `Server error: ${response.status}`;
                    try { const errorData = await response.json(); if (errorData && errorData.message) errorMsg = errorData.message; } catch (jsonError) {}
                    throw new Error(errorMsg);
                }
                const result = await response.json();
                if (result.success) {
                    closeAddTypeToCategoryModal();
                    showToast(result.message, 'success', 3000, 'bottom-center');
                    window.location.href = window.location.pathname + '?tab=Configuration';
                } else {
                    displayModalMessage(addTypeToCategoryModalMessageDiv, result.message || "An error occurred.", 'error');
                }
            } catch (error) {
                console.error('Add type to category form submission error:', error);
                displayModalMessage(addTypeToCategoryModalMessageDiv, "Submission failed: " + error.message, 'error');
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonContent;
                }
            }
        });
    }
    if (editViolationTypeForm) {
        editViolationTypeForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            clearModalMessage(editViolationTypeModalMessageDiv);
            const violationTypeId = document.getElementById("editViolationTypeId").value;
            const newResolutionNumber = document.getElementById("editResolutionNumberConfig").value.trim();
            const newViolationCategory = document.getElementById("editViolationCategoryConfig").value.trim();
            const newViolationType = document.getElementById("editViolationTypeConfig").value.trim();
            const newViolationDescription = document.getElementById("editViolationDescriptionConfig").value.trim();
            if (!violationTypeId || !newResolutionNumber || !newViolationCategory || !newViolationType || !newViolationDescription) {
                displayModalMessage(editViolationTypeModalMessageDiv, "All fields are required.", 'error');
                return;
            }
            const formData = new FormData(editViolationTypeForm);
            formData.set('violation_type_id', violationTypeId);
            formData.set('edit_resolution_number_config', newResolutionNumber);
            formData.set('edit_violation_category_config', newViolationCategory);
            formData.set('edit_violation_type_config', newViolationType);
            formData.set('edit_violation_description_config', newViolationDescription);
            const submitButton = editViolationTypeForm.querySelector('button[type="submit"]');
            const originalButtonContent = submitButton ? submitButton.innerHTML : '';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            }
            try {
                const response = await fetch(editViolationTypeForm.action, { method: 'POST', body: formData });
                if (!response.ok) {
                    let errorMsg = `Server error: ${response.status}`;
                    try { const errorData = await response.json(); if (errorData && errorData.message) errorMsg = errorData.message; } catch (jsonError) {}
                    throw new Error(errorMsg);
                }
                const result = await response.json();
                if (result.success) {
                    closeEditViolationTypeModal();
                    showToast(result.message, 'success', 3000, 'bottom-center');
                    window.location.href = window.location.pathname + '?tab=Configuration';
                } else {
                    displayModalMessage(editViolationTypeModalMessageDiv, result.message || "An error occurred.", 'error');
                }
            } catch (error) {
                console.error('Edit violation type form submission error:', error);
                displayModalMessage(editViolationTypeModalMessageDiv, "Submission failed: " + error.message, 'error');
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonContent;
                }
            }
        });
    }
    function addInputListenersForCapslock() {
        const inputsToAddCategoryCapslock = [ document.getElementById('newCategoryName'), document.getElementById('newResolutionNumberCatModal'), document.getElementById('newViolationTypeCatModal') ];
        inputsToAddCategoryCapslock.forEach(input => { if (input) { input.removeEventListener('input', handleInputUppercase); input.addEventListener('input', handleInputUppercase); } });
        const inputsToAddTypeCapslock = [ document.getElementById('newResolutionNumberTypeModal'), document.getElementById('newViolationTypeTypeModal') ];
        inputsToAddTypeCapslock.forEach(input => { if (input) { input.removeEventListener('input', handleInputUppercase); input.addEventListener('input', handleInputUppercase); } });
        const inputsToEditCapslock = [ document.getElementById('editResolutionNumberConfig'), document.getElementById('editViolationCategoryConfig'), document.getElementById('editViolationTypeConfig') ];
        inputsToEditCapslock.forEach(input => { if (input) { input.removeEventListener('input', handleInputUppercase); input.addEventListener('input', handleInputUppercase); } });
    }
    function handleInputUppercase() { this.value = this.value.toUpperCase(); }
    addInputListenersForCapslock();
    if (refreshTableBtn) {
        refreshTableBtn.addEventListener('click', () => {
            showTableSpinner();
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('tab', 'Violation');
            currentUrl.searchParams.delete('violation_category');
            currentUrl.searchParams.delete('course_id');
            currentUrl.searchParams.delete('year_id');
            currentUrl.searchParams.delete('search');
            window.location.href = currentUrl.href;
        });
    }
    if (filterForm) {
        const selects = filterForm.querySelectorAll('select');
        selects.forEach(select => { select.addEventListener('change', () => { showTableSpinner(); filterForm.submit(); }); });
        filterForm.addEventListener('submit', () => { showTableSpinner(); });
    }
});