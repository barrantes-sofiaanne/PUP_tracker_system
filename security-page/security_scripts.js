document.addEventListener("DOMContentLoaded", () => {
    const pageContainer = document.getElementById('pageContainer');
    const openMenuBtn = document.getElementById('openMenuBtn');
    const closeMenuBtn = document.getElementById('closeMenuBtn');
    const overlay = document.getElementById('overlay');
    const filtersToggleBtn = document.getElementById('filtersToggleBtn');
    const filtersContainer = document.getElementById('filtersContainer');

    const courseFilter = document.getElementById("courseFilter");
    const yearFilter = document.getElementById("yearFilter");
    const loadingOverlay = document.getElementById("loadingOverlay");
    const datePickerInput = document.getElementById("dateRangePicker");
    const startDateFilter = document.getElementById("startDateFilter");
    const endDateFilter = document.getElementById("endDateFilter");

    let violationChartInstance = null;

    if (openMenuBtn) {
        openMenuBtn.addEventListener('click', () => pageContainer.classList.add('menu-open'));
    }
    if (closeMenuBtn) {
        closeMenuBtn.addEventListener('click', () => pageContainer.classList.remove('menu-open'));
    }
    if(overlay) {
        overlay.addEventListener('click', () => pageContainer.classList.remove('menu-open'));
    }
    if(filtersToggleBtn) {
        filtersToggleBtn.addEventListener('click', () => {
            filtersToggleBtn.classList.toggle('active');
            filtersContainer.classList.toggle('active');
        });
    }

    const datePicker = flatpickr(datePickerInput, {
        mode: "range",
        dateFormat: "Y-m-d",
        altInput: true,
        altFormat: "M j, Y",
        onChange: function (selectedDates, dateStr, instance) {
            if (selectedDates.length === 2) {
                startDateFilter.value = instance.formatDate(selectedDates[0], "Y-m-d");
                endDateFilter.value = instance.formatDate(selectedDates[1], "Y-m-d");
            } else {
                startDateFilter.value = "";
                endDateFilter.value = "";
            }
            updateDashboard();
        },
    });

    const renderEmptyState = (containerId, message) => {
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = `
                <div class="empty-state-visual">
                    <i class="fas fa-chart-pie"></i>
                    <h3>No Data Available</h3>
                    <p>${message}</p>
                </div>
            `;
        }
    };
    
    const checkActiveFilters = () => {
        const isCourseFiltered = courseFilter.value !== 'all';
        const isYearFiltered = yearFilter.value !== 'all';
        const isDateFiltered = datePickerInput.value !== '';
        
        if (isCourseFiltered || isYearFiltered || isDateFiltered) {
            filtersToggleBtn.classList.add('filters-applied');
        } else {
            filtersToggleBtn.classList.remove('filters-applied');
        }
    };

    const populateFilters = async () => {
        try {
            const [coursesRes, yearsRes] = await Promise.all([
                fetch("security_dashboard.php?action=get_courses"),
                fetch("security_dashboard.php?action=get_years"),
            ]);
            const coursesData = await coursesRes.json();
            const yearsData = await yearsRes.json();

            if (coursesData && coursesData.data) {
                coursesData.data.forEach((c) =>
                    courseFilter.add(new Option(c.course_name, c.course_id))
                );
            }
            if (yearsData && yearsData.data) {
                yearsData.data.forEach((y) => {
                    const suffix =
                        new Map([
                            [1, "st"],
                            [2, "nd"],
                            [3, "rd"],
                        ]).get(parseInt(y.year)) || "th";
                    yearFilter.add(new Option(`${y.year}${suffix} Year`, y.year_id));
                });
            }
        } catch (error) {
            console.error("Error populating filters:", error);
        }
    };

    const centerTextPlugin = {
        id: 'centerText',
        afterDraw: (chart, args, options) => {
            if (!options.label || !options.percentage) {
                return;
            }
            const { ctx } = chart;
            const x = chart.getDatasetMeta(0).data[0].x;
            const y = chart.getDatasetMeta(0).data[0].y;
            
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            ctx.font = 'bold 24px Inter';
            ctx.fillStyle = '#1f2937';
            ctx.fillText(`${options.percentage}%`, x, y - 10);

            ctx.font = '14px Inter';
            ctx.fillStyle = '#6b7280';
            ctx.fillText(options.label, x, y + 15);
            ctx.restore();
        }
    };

    const updateDashboard = async () => {
        loadingOverlay.style.display = "flex";
        if (violationChartInstance) violationChartInstance.destroy();
        document.getElementById("violationInsight").textContent = "";
        checkActiveFilters();

        const url = `security_dashboard.php?action=get_dashboard_data&course=${
            courseFilter.value
        }&year=${yearFilter.value}&start_date=${startDateFilter.value}&end_date=${
            endDateFilter.value
        }`;

        try {
            const response = await fetch(url);
            const result = await response.json();
            if (result.error || !result.data)
                throw new Error(result.error || "No data received");
            const data = result.data;

            document.getElementById("violationChartContainer").innerHTML =
                '<canvas id="violationChart"></canvas>';
            const chartCtx = document.getElementById("violationChart");

            if (data.violation && data.violation.labels.length > 0) {
                const topViolationLabel = data.violation.labels[0];
                const totalViolations = data.violation.data.reduce((a, b) => a + b, 0);
                const topViolationCount = data.violation.data[0];
                const topPercentage =
                    totalViolations > 0
                        ? ((topViolationCount / totalViolations) * 100).toFixed(0)
                        : 0;
                document.getElementById(
                    "violationInsight"
                ).textContent = `'${topViolationLabel}' is the most common issue, making up ${topPercentage}% of cases.`;

                const isMobile = window.innerWidth <= 768;
                
                const themeChartColors = [
                    '#ffc425', 
                    '#800000', 
                    '#d4a31d', 
                    '#b91c1c', 
                    '#fde047', 
                    '#610000', 
                    '#6b7280',
                    '#374151' 
                ];
                
                let chartConfig;

                if (isMobile) {
                    chartConfig = {
                        type: 'doughnut',
                        data: {
                            labels: data.violation.labels,
                            datasets: [{
                                label: 'Violations',
                                data: data.violation.data,
                                backgroundColor: themeChartColors,
                                borderColor: 'var(--secondary-bg)',
                                borderWidth: 2,
                                hoverBorderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '75%',
                            plugins: {
                                centerText: {
                                    label: topViolationLabel,
                                    percentage: topPercentage
                                },
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    displayColors: true,
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.raw;
                                            const total = context.chart.getDatasetMeta(0).total;
                                            const percentage = total > 0 ? ((value / total) * 100).toFixed(0) : 0;
                                            return ` ${label}: ${value} (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        },
                        plugins: [centerTextPlugin]
                    };
                } else {
                    chartConfig = {
                        type: "bar",
                        data: {
                            labels: data.violation.labels,
                            datasets: [{
                                label: "Violations",
                                data: data.violation.data,
                                backgroundColor: (c) => c.dataIndex === 0 ? "#ffc425" : "#800000",
                                borderRadius: 4,
                            }],
                        },
                        options: {
                            indexAxis: "y",
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false }, tooltip: { displayColors: false } },
                            scales: { x: { grid: { display: false, drawBorder: false }, ticks: { precision: 0 } }, y: { grid: { display: false, drawBorder: false } } },
                        },
                    };
                }
                violationChartInstance = new Chart(chartCtx, chartConfig);

            } else {
                renderEmptyState(
                    "violationChartContainer",
                    "Try adjusting the filters to find some data."
                );
            }
        } catch (error) {
            console.error("Error updating dashboard:", error);
            renderEmptyState("violationChartContainer", "Could not load chart data.");
        } finally {
            loadingOverlay.style.display = "none";
        }
    };

    [courseFilter, yearFilter].forEach((filter) => {
        filter.addEventListener("change", updateDashboard);
    });
    
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            updateDashboard();
        }, 250);
    });

    populateFilters().then(updateDashboard);
});