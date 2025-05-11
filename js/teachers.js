// Function to show the subjects modal for a teacher
function showSubjectsModal(teacherId) {
  // Show loading indicator
  document.getElementById("modalContent").innerHTML =
    '<div class="flex justify-center items-center p-8"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div></div>'

  // Show the modal
  document.getElementById("modal").classList.remove("hidden")

  // Fetch the teacher's subjects
  fetch("get_teacher_subjects.php?id=" + teacherId)
    .then((response) => {
      if (!response.ok) {
        throw new Error("Network response was not ok")
      }
      return response.text()
    })
    .then((html) => {
      document.getElementById("modalContent").innerHTML = html
    })
    .catch((error) => {
      console.error("Error:", error)
      document.getElementById("modalContent").innerHTML = `
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
                    <p class="font-bold">Error</p>
                    <p>${error.message}</p>
                </div>
            `
    })
}

// Function to close the modal
function closeModal() {
  document.getElementById("modal").classList.add("hidden")
}

// Add event listeners when the DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  // Close modal when clicking outside the modal content
  document.getElementById("modal").addEventListener("click", function (e) {
    if (e.target === this) {
      closeModal()
    }
  })

  // Close modal when clicking the close button
  document.getElementById("closeModal").addEventListener("click", closeModal)
})
