/**
 * Dashboard JavaScript functionality
 */

document.addEventListener("DOMContentLoaded", () => {
    // Toggle sidebar on mobile
    const sidebarToggle = document.querySelector(".sidebar-toggle")
    if (sidebarToggle) {
      sidebarToggle.addEventListener("click", () => {
        document.querySelector(".sidebar").classList.toggle("show")
      })
    }
  
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map((tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl))
  
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    popoverTriggerList.map((popoverTriggerEl) => new bootstrap.Popover(popoverTriggerEl))
  
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
      const alerts = document.querySelectorAll(".alert:not(.alert-permanent)")
      alerts.forEach((alert) => {
        const bsAlert = new bootstrap.Alert(alert)
        bsAlert.close()
      })
    }, 5000)
  
    // Confirm delete actions
    const deleteButtons = document.querySelectorAll(".btn-delete")
    deleteButtons.forEach((button) => {
      button.addEventListener("click", (e) => {
        if (!confirm("Are you sure you want to delete this item? This action cannot be undone.")) {
          e.preventDefault()
        }
      })
    })
  
    // Print button functionality
    const printButtons = document.querySelectorAll(".btn-print")
    printButtons.forEach((button) => {
      button.addEventListener("click", () => {
        window.print()
      })
    })
  })
  
  