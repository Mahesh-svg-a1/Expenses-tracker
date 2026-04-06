// public/assets/js/app.js

(function () {
  "use strict";

  function setupConfirmForms() {
    document.addEventListener("submit", function (event) {
      const form = event.target;

      if (!form || !form.matches("form[data-confirm]")) {
        return;
      }

      const message = form.getAttribute("data-confirm") || "Are you sure?";
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    });
  }

  function setupResendCountdown() {
    const button = document.querySelector("[data-resend-seconds]");
    if (!button) {
      return;
    }

    let seconds = Number(button.getAttribute("data-resend-seconds") || 0);
    const readyLabel = button.getAttribute("data-resend-label") || "Resend code";

    function updateButton() {
      if (seconds > 0) {
        button.disabled = true;
        button.textContent = "Resend in " + seconds + "s";
        return;
      }

      button.disabled = false;
      button.textContent = readyLabel;
    }

    updateButton();

    if (seconds <= 0) {
      return;
    }

    const timer = window.setInterval(function () {
      seconds -= 1;
      updateButton();

      if (seconds <= 0) {
        window.clearInterval(timer);
      }
    }, 1000);
  }

  function setupReportsCharts() {
    if (typeof window.__REPORTS__ === "undefined" || typeof Chart === "undefined") {
      return;
    }

    const categoryChart = document.getElementById("catChart");
    if (categoryChart) {
      new Chart(categoryChart, {
        type: "doughnut",
        data: {
          labels: window.__REPORTS__.cat.labels,
          datasets: [{ data: window.__REPORTS__.cat.totals }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { position: "bottom" },
            tooltip: {
              callbacks: {
                label: function (context) {
                  return context.label + ": Rs. " + Number(context.parsed).toFixed(2);
                }
              }
            }
          }
        }
      });
    }

    const monthChart = document.getElementById("monthChart");
    if (monthChart) {
      new Chart(monthChart, {
        type: "bar",
        data: {
          labels: window.__REPORTS__.month.labels,
          datasets: [{ data: window.__REPORTS__.month.totals }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function (context) {
                  return "Rs. " + Number(context.parsed.y).toFixed(2);
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: function (value) {
                  return "Rs. " + value;
                }
              }
            }
          }
        }
      });
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    setupResendCountdown();
    setupReportsCharts();
  });

  setupConfirmForms();
})();
