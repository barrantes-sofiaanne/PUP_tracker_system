/*document.addEventListener("DOMContentLoaded", () => {
    // Hardcoded data
    const violations = {
        "Late ID Validation": 10,
        "Lost ID": 8,
        "No ID": 12,
        "Late Registration Card": 5,
        "Lost Registration Card": 4,
        "Hair Color": 6,
        "Prohibited Clothes": 7,
        "Other Violation": 3
    };

    const totalStudents = Object.values(violations).reduce((a, b) => a + b, 0);

    // Animate student count
    let count = 0;
    const target = totalStudents;
    const interval = setInterval(() => {
        if (count <= target) {
            document.getElementById('student-count').textContent = count;
            count++;
        } else {
            clearInterval(interval);
        }
    }, 50);

    // Chart setup
    const ctx = document.getElementById('violationChart').getContext('2d');
    const labels = Object.keys(violations);
    const data = Object.values(violations);
    const backgroundColors = [
        '#ff6384', '#36a2eb', '#cc65fe', '#ffce56', '#ffa600',
        '#4bc0c0', '#9966ff', '#c9cbcf'
    ];

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: '% of Violations',
                data: data.map(val => ((val / totalStudents) * 100).toFixed(2)),
                backgroundColor: backgroundColors
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Percentage (%)'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.raw + '%';
                        }
                    }
                }
            }
        }
    });
}); 

*/

document.addEventListener("DOMContentLoaded", async () => {
    let courses = await fetch("../PHP/admin_homepage.php");
    courses = await courses.json();

    const courseCtx = document.getElementById("coursePieChart").getContext("2d");
    new Chart(courseCtx, {
        type: "pie",
        data: {
            labels: courses.courses.labels,
            datasets: [
                {
                    data: courses.courses.data,
                },
            ],
        },
        options: {
            plugins: {
                legend: {
                    position: "right",
                },
            },
        },
    });

    const violationCtx = document
        .getElementById("violationChart")
        .getContext("2d");
    new Chart(violationCtx, {
        type: "bar",
        data: {
            labels: courses.violation.labels,
            datasets: [
                {
                    data: courses.violation.data,
                    backgroundColor: [
                        "#ff6384",
                        "#36a2eb",
                        "#ffcd56",
                        "#4bc0c0",
                        "#9966ff",
                    ], // optional colors
                },
            ],
        },
        options: {
            plugins: {
                legend: {
                    position: "right",
                },
            },
        },
    });
});
