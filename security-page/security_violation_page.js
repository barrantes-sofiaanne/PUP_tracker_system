document.addEventListener("DOMContentLoaded", function () {
  let currentPage = 1;

  const pageContainer = document.getElementById("pageContainer");
  const openMenuBtn = document.getElementById("openMenuBtn");
  const closeMenuBtn = document.getElementById("closeMenuBtn");
  const overlay = document.getElementById("overlay");
  const filterForm = document.getElementById("filter-form");
  const violationTableBody = document.getElementById("violationTableBody");
  const refreshBtn = document.getElementById("refreshBtn");
  const startDateInput = document.getElementById("startDateFilter");
  const endDateInput = document.getElementById("endDateFilter");
  const generateReportBtn = document.getElementById("generateReportBtn");
  const paginationContainer = document.getElementById("paginationContainer");
  const toggleFilterBtn = document.getElementById('toggleFilterBtn');
  const filterContainer = document.getElementById('filterContainer');
  const fabAddViolation = document.getElementById('fabAddViolation');

  const renderPagination = (paginationData) => {
    const { currentPage, totalPages } = paginationData;
    paginationContainer.innerHTML = "";

    if (totalPages <= 1) return;

    let paginationHTML = '<nav><ul class="pagination">';

    paginationHTML += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a>
    </li>`;

    for (let i = 1; i <= totalPages; i++) {
        paginationHTML += `<li class="page-item ${i === currentPage ? 'active' : ''}">
            <a class="page-link" href="#" data-page="${i}">${i}</a>
        </li>`;
    }

    paginationHTML += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${currentPage + 1}">Next</a>
    </li>`;

    paginationHTML += '</ul></nav>';
    paginationContainer.innerHTML = paginationHTML;
  };

  const updateTable = (page = 1) => {
    currentPage = page;
    const formData = new FormData(filterForm);
    formData.append("action", "filter_violations");
    formData.append("page", currentPage);

    violationTableBody.innerHTML =
      '<tr><td colspan="8" class="no-records-cell" style="text-align:center;"><i class="fas fa-spinner fa-spin fa-2x"></i></td></tr>';

    fetch("security_violation_page.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          violationTableBody.innerHTML = data.html;
          renderPagination(data.pagination);
        } else {
          violationTableBody.innerHTML =
            '<tr><td colspan="8" class="no-records-cell">Error loading data.</td></tr>';
          paginationContainer.innerHTML = "";
        }
      })
      .catch((error) => {
        violationTableBody.innerHTML =
          '<tr><td colspan="8" class="no-records-cell">Request failed. Please try again.</td></tr>';
        paginationContainer.innerHTML = "";
      });
  };

  if (toggleFilterBtn && filterContainer) {
    toggleFilterBtn.addEventListener('click', () => {
        toggleFilterBtn.classList.toggle('active');
        filterContainer.classList.toggle('active');
    });
  }

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

  const datePicker = flatpickr("#dateRangePicker", {
    mode: "range",
    dateFormat: "Y-m-d",
    altInput: true,
    altFormat: "M j, Y",
    onChange: function (selectedDates, dateStr, instance) {
      if (selectedDates.length === 2) {
        startDateInput.value = instance.formatDate(selectedDates[0], "Y-m-d");
        endDateInput.value = instance.formatDate(selectedDates[1], "Y-m-d");
      } else {
        startDateInput.value = "";
        endDateInput.value = "";
      }
      updateTable(1);
    },
  });

  let debounceTimer;
  const debounceUpdateTable = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => updateTable(1), 500);
  };

  if (filterForm) {
    filterForm.addEventListener("submit", (e) => e.preventDefault());
    document
      .getElementById("courseFilter")
      .addEventListener("change", () => updateTable(1));
    document
      .getElementById("searchFilter")
      .addEventListener("input", debounceUpdateTable);
  }
  if (refreshBtn) {
    refreshBtn.addEventListener("click", () => {
      filterForm.reset();
      datePicker.clear();
      startDateInput.value = "";
      endDateInput.value = "";
      updateTable(1);
    });
  }

  if (paginationContainer) {
    paginationContainer.addEventListener('click', e => {
        e.preventDefault();
        const link = e.target.closest('.page-link');
        if (link) {
            const page = parseInt(link.dataset.page);
            const parentLi = link.parentElement;
            if (page && !parentLi.classList.contains('disabled') && !parentLi.classList.contains('active')) {
                 updateTable(page);
            }
        }
    });
  }

  if (generateReportBtn) {
    generateReportBtn.addEventListener("click", () => {
      const course = document.getElementById("courseFilter").value;
      const search = document.getElementById("searchFilter").value;
      const startDate = startDateInput.value;
      const endDate = endDateInput.value;

      const url = `generate_violation_report.php?course_id=${encodeURIComponent(
        course
      )}&start_date=${encodeURIComponent(
        startDate
      )}&end_date=${encodeURIComponent(
        endDate
      )}&search=${encodeURIComponent(search)}`;

      window.open(url, "_blank");
    });
  }

  const addViolationBtn = document.getElementById("addViolationBtn");
  const modalOverlay = document.getElementById("violationModal");

  if (modalOverlay) {
    const closeModalBtn = document.getElementById("closeModalBtn");
    const cancelFormBtn = document.getElementById("cancelFormBtn");
    const searchStep = document.getElementById("searchStep");
    const violationForm = document.getElementById("violationForm");
    const searchInput = document.getElementById("studentNumberSearchInput");
    const searchBtn = document.getElementById("executeStudentSearchBtn");
    const searchLoading = document.getElementById("searchLoadingIndicator");
    const searchResultArea = document.getElementById("studentSearchResultArea");
    const confirmedInfoDiv = document.getElementById("confirmedStudentInfo");
    const studentNumberHiddenInput = document.getElementById("studentNumber");
    const categorySelect = document.getElementById("violationCategory");
    const typeSelect = document.getElementById("violationType");
    const changeStudentBtn = document.getElementById("changeStudentBtn");
    const modalMessage = document.getElementById("modalMessage");

    const openModal = () => {
      modalOverlay.classList.add("active");
      resetModal();
    };

    if (addViolationBtn) {
        addViolationBtn.addEventListener("click", openModal);
    }
    if (fabAddViolation) {
        fabAddViolation.addEventListener('click', openModal);
    }

    const closeModal = () => {
      modalOverlay.classList.remove("active");
    };

    const resetModal = () => {
      searchStep.style.display = "block";
      violationForm.style.display = "none";
      searchResultArea.style.display = "none";
      searchLoading.style.display = "none";
      modalMessage.style.display = "none";
      searchInput.value = "";
      searchBtn.disabled = false;
      searchInput.disabled = false;
      violationForm.reset();
      typeSelect.innerHTML = '<option value="">Select Category First</option>';
      typeSelect.disabled = true;
      searchInput.focus();
    };

    const showStep = (stepToShow) => {
      [searchStep, violationForm].forEach((step) => {
        step.style.display = step === stepToShow ? "block" : "none";
      });
    };

    const showModalMessage = (message) => {
      modalMessage.textContent = message;
      modalMessage.style.display = "block";
    };

    const performSearch = async () => {
      const studentNumber = searchInput.value.trim();
      if (!studentNumber) {
        showModalMessage("Please enter a Student Number.");
        return;
      }
      searchLoading.style.display = "block";
      searchBtn.disabled = true;
      searchInput.disabled = true;
      modalMessage.style.display = "none";
      searchResultArea.style.display = "none";

      try {
        const response = await fetch(
          `security_violation_page.php?action=search_student_for_violation&student_search_number=${encodeURIComponent(
            studentNumber
          )}`
        );
        const data = await response.json();

        if (data.success && data.student) {
          const student = data.student;
          searchResultArea.innerHTML = `
                                <div class="student-info-box">
                                    <p><strong>Number:</strong> ${
                                      student.student_number
                                    }</p>
                                    <p><strong>Name:</strong> ${
                                      student.first_name || ""
                                    } ${student.middle_name || ""} ${
            student.last_name || ""
          }</p>
                                    <p><strong>Course:</strong> ${
                                      student.course_name || "N/A"
                                    } - ${
            student.year || "N/A"
          } | <strong>Section:</strong> ${student.section_name || "N/A"}</p>
                                </div>
                                <button type="button" id="useThisStudentBtn" class="action-btn use-student-btn"><i class="fas fa-user-check"></i> Use This Student</button>
                            `;
          document.getElementById("useThisStudentBtn").onclick = () => {
            confirmedInfoDiv.innerHTML =
              searchResultArea.querySelector(".student-info-box").innerHTML;
            studentNumberHiddenInput.value = student.student_number;
            showStep(violationForm);
          };
          searchResultArea.style.display = "block";
        } else {
          showModalMessage(data.message || "Student not found.");
        }
      } catch (error) {
        showModalMessage("An error occurred during the search.");
      } finally {
        searchLoading.style.display = "none";
        searchBtn.disabled = false;
        searchInput.disabled = false;
      }
    };

    const fetchViolationTypes = async () => {
      const categoryId = categorySelect.value;
      typeSelect.disabled = true;
      typeSelect.innerHTML = `<option value="">${
        categoryId ? "Loading..." : "Select Category First"
      }</option>`;

      if (!categoryId) return;

      try {
        const response = await fetch(
          `security_violation_page.php?action=get_violation_types_for_category&category_id=${categoryId}`
        );
        const data = await response.json();
        if (data.success && data.types) {
          typeSelect.innerHTML =
            '<option value="">Select Violation Type</option>';
          if (data.types.length > 0) {
            data.types.forEach((type) => {
              typeSelect.add(
                new Option(type.violation_type, type.violation_type_id)
              );
            });
            typeSelect.disabled = false;
          } else {
            typeSelect.innerHTML =
              '<option value="">No types in this category</option>';
          }
        }
      } catch (error) {
        typeSelect.innerHTML = '<option value="">Error loading types</option>';
      }
    };

    const submitViolationForm = async (e) => {
      e.preventDefault();
      const submitBtn = document.getElementById("submitViolationBtn");
      const originalBtnHtml = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Adding...`;

      try {
        const formData = new FormData(violationForm);
        const response = await fetch("security_violation_page.php", {
          method: "POST",
          body: formData,
        });
        const data = await response.json();
        if (data.success) {
          showToast(data.message, "success");
          closeModal();
          updateTable(currentPage);
        } else {
          showModalMessage(data.message || "An error occurred.");
        }
      } catch (error) {
        showModalMessage("A network error occurred.");
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnHtml;
      }
    };

    closeModalBtn.addEventListener("click", closeModal);
    cancelFormBtn.addEventListener("click", closeModal);
    modalOverlay.addEventListener("click", (e) => {
      if (e.target === modalOverlay) closeModal();
    });

    searchBtn.addEventListener("click", performSearch);
    searchInput.addEventListener("keypress", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        performSearch();
      }
    });

    changeStudentBtn.addEventListener("click", () => showStep(searchStep));
    categorySelect.addEventListener("change", fetchViolationTypes);
    violationForm.addEventListener("submit", submitViolationForm);
  }

  const tableContainer = document.querySelector(".main-table-scroll-container");
  if (tableContainer) {
    tableContainer.addEventListener("click", function (e) {
      const summaryRow = e.target.closest(".student-summary-row");
      if (summaryRow) {
        summaryRow.classList.toggle("expanded");
        const detailRow = document.getElementById(summaryRow.dataset.target);
        if (detailRow) {
          detailRow.classList.toggle("active");
        }
      }
    });
  }

  function showToast(message, type = "success", duration = 3000) {
    const toast = document.getElementById("toast-notification");
    if (!toast) return;
    toast.textContent = message;
    toast.className = "toast";
    toast.classList.add(type, "show");
    setTimeout(() => {
      toast.classList.remove("show");
    }, duration);
  }

  updateTable(1);
});