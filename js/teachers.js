// Save new teacher
function saveNewTeacher() {
  const form = document.getElementById("addTeacherForm")

  // Prevent multiple submissions
  if (form.dataset.submitting === "true") {
    console.log("Form already being submitted, preventing duplicate submission")
    return false
  }

  // Mark form as being submitted
  form.dataset.submitting = "true"

  // Disable submit button
  const submitButton = form.querySelector('button[type="submit"]')
  if (submitButton) {
    submitButton.disabled = true
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...'
  }

  const formData = new FormData(form)

  // Validate passwords match
  const password = formData.get("password")
  const confirmPassword = formData.get("confirm_password")

  if (password !== confirmPassword) {
    Swal.fire({
      title: "Error!",
      text: "Passwords do not match.",
      icon: "error",
      confirmButtonColor: "#3085d6",
    })
    // Reset submission state
    form.dataset.submitting = "false"
    if (submitButton) {
      submitButton.disabled = false
      submitButton.innerHTML = "Add Teacher"
    }
    return false
  }

  // Department handling removed

  // Add a unique token to prevent duplicate submissions
  const token = Date.now().toString() + Math.random().toString(36).substr(2, 5)
  formData.append("submission_token", token)

  // Show loading state
  Swal.fire({
    title: "Saving...",
    html: "Please wait while we add the new teacher.",
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading()
    },
  })

  // Send AJAX request
  fetch("save_new_teacher.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) {
        return response.text().then((text) => {
          throw new Error("Server error: " + (text || response.statusText))
        })
      }
      return response.json()
    })
    .then((data) => {
      if (data.success) {
        Swal.fire({
          title: "Success!",
          text: data.message,
          icon: "success",
          confirmButtonColor: "#3085d6",
        }).then(() => {
          closeAddTeacherModal()
          window.location.reload()
        })
      } else {
        Swal.fire({
          title: "Error!",
          text: data.message || "An unknown error occurred.",
          icon: "error",
          confirmButtonColor: "#3085d6",
        })
        // Reset submission state
        form.dataset.submitting = "false"
        if (submitButton) {
          submitButton.disabled = false
          submitButton.innerHTML = "Add Teacher"
        }
      }
    })
    .catch((error) => {
      console.error("Error:", error)
      Swal.fire({
        title: "Error!",
        text: "An error occurred while saving: " + error.message,
        icon: "error",
        confirmButtonColor: "#3085d6",
      })
      // Reset submission state
      form.dataset.submitting = "false"
      if (submitButton) {
        submitButton.disabled = false
        submitButton.innerHTML = "Add Teacher"
      }
    })

  return false
}

// Save teacher edit
function saveTeacherEdit() {
  const form = document.getElementById("editTeacherForm")

  // Prevent multiple submissions
  if (form.dataset.submitting === "true") {
    console.log("Form already being submitted, preventing duplicate submission")
    return false
  }

  // Mark form as being submitted
  form.dataset.submitting = "true"

  // Disable submit button
  const submitButton = form.querySelector('button[type="submit"]')
  if (submitButton) {
    submitButton.disabled = true
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...'
  }

  const formData = new FormData(form)

  // Department handling removed

  // Add a unique token to prevent duplicate submissions
  const token = Date.now().toString() + Math.random().toString(36).substr(2, 5)
  formData.append("submission_token", token)

  // Show loading state
  Swal.fire({
    title: "Saving...",
    html: "Please wait while we update the teacher information.",
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading()
    },
  })

  // Log form data for debugging
  console.log("Submitting form data:", Object.fromEntries(formData))

  // Send AJAX request
  fetch("save_teacher_edit.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) {
        return response.text().then((text) => {
          throw new Error("Server error: " + (text || response.statusText))
        })
      }
      return response.json()
    })
    .then((data) => {
      if (data.success) {
        Swal.fire({
          title: "Success!",
          text: data.message,
          icon: "success",
          confirmButtonColor: "#3085d6",
        }).then(() => {
          closeEditModal()
          window.location.reload() // Force page reload to show updated data
        })
      } else {
        Swal.fire({
          title: "Error!",
          text: data.message || "An unknown error occurred.",
          icon: "error",
          confirmButtonColor: "#3085d6",
        })
        // Reset submission state
        form.dataset.submitting = "false"
        if (submitButton) {
          submitButton.disabled = false
          submitButton.innerHTML = "Save Changes"
        }
      }
    })
    .catch((error) => {
      console.error("Error:", error)
      Swal.fire({
        title: "Error!",
        text: "An error occurred while saving: " + error.message,
        icon: "error",
        confirmButtonColor: "#3085d6",
      })
      // Reset submission state
      form.dataset.submitting = "false"
      if (submitButton) {
        submitButton.disabled = false
        submitButton.innerHTML = "Save Changes"
      }
    })

  return false // Prevent form submission
}

document.addEventListener("DOMContentLoaded", () => {
  // Select all checkbox functionality
  const selectAllCheckbox = document.getElementById("selectAll")
  if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener("change", function () {
      const checkboxes = document.querySelectorAll(".teacher-checkbox")
      checkboxes.forEach((checkbox) => {
        checkbox.checked = this.checked
      })
    })
  }
})

// Confirm delete function
function confirmDelete(teacherId) {
  Swal.fire({
    title: "Are you sure?",
    text: "You won't be able to revert this!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Yes, delete it!",
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = "teachers.php?delete=" + teacherId
    }
  })
}

// Show add teacher modal
function showAddTeacherModal() {
  document.getElementById("addTeacherModal").classList.remove("hidden")
}

// Close add teacher modal
function closeAddTeacherModal() {
  document.getElementById("addTeacherModal").classList.add("hidden")
  // Reset the form submission state
  const form = document.getElementById("addTeacherForm")
  if (form) {
    form.dataset.submitting = "false"
    const submitButton = form.querySelector('button[type="submit"]')
    if (submitButton) {
      submitButton.disabled = false
      submitButton.innerHTML = "Add Teacher"
    }
  }
}

// Show profile modal
function showProfileModal(teacherId) {
  document.getElementById("profileModal").classList.remove("hidden")
  const profileContent = document.getElementById("profileContent")
  profileContent.innerHTML =
    '<div class="flex justify-center"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div></div>'

  // Use 'id' parameter to match what get_teacher_profile.php expects
  fetch("get_teacher_profile.php?id=" + teacherId)
    .then((response) => response.text())
    .then((data) => {
      profileContent.innerHTML = data
    })
    .catch((error) => {
      profileContent.innerHTML =
        '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert"><p>Error loading profile: ' +
        error.message +
        "</p></div>"
    })
}

// Close profile modal
function closeProfileModal() {
  document.getElementById("profileModal").classList.add("hidden")
}

// Show edit modal
function showEditModal(teacherId) {
  document.getElementById("editModal").classList.remove("hidden")
  const editContent = document.getElementById("editContent")
  editContent.innerHTML =
    '<div class="flex justify-center"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div></div>'

  // Change parameter name from teacher_id to id to match what the PHP file expects
  fetch("get_teacher_edit_form.php?id=" + teacherId)
    .then((response) => response.text())
    .then((data) => {
      editContent.innerHTML = data
    })
    .catch((error) => {
      editContent.innerHTML =
        '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert"><p>Error loading edit form: ' +
        error.message +
        "</p></div>"
    })
}

// Close edit modal
function closeEditModal() {
  document.getElementById("editModal").classList.add("hidden")
  // Reset the form submission state
  const form = document.getElementById("editTeacherForm")
  if (form) {
    form.dataset.submitting = "false"
    const submitButton = form.querySelector('button[type="submit"]')
    if (submitButton) {
      submitButton.disabled = false
      submitButton.innerHTML = "Save Changes"
    }
  }
}

// Show subjects modal
function showSubjectsModal(teacherId) {
  document.getElementById("subjectsModal").classList.remove("hidden")
  const subjectsContent = document.getElementById("subjectsContent")
  subjectsContent.innerHTML =
    '<div class="flex justify-center"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div></div>'

  // Change parameter name from teacher_id to id to match what the PHP file expects
  fetch("get_teacher_subjects.php?id=" + teacherId)
    .then((response) => response.text())
    .then((data) => {
      subjectsContent.innerHTML = data
    })
    .catch((error) => {
      subjectsContent.innerHTML =
        '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert"><p>Error loading subjects: ' +
        error.message +
        "</p></div>"
    })
}

// Close subjects modal
function closeSubjectsModal() {
  document.getElementById("subjectsModal").classList.add("hidden")
}

// Print teacher list
function printTeacherList() {
  // Create a new window for printing
  const printWindow = window.open("", "_blank", "height=600,width=800")

  // Get the current date for the report header
  const currentDate = new Date().toLocaleDateString()

  // Get filter values
  const searchValue = document.getElementById("search")?.value || ""
  const statusValue =
    document.getElementById("status")?.options[document.getElementById("status")?.selectedIndex]?.text || "All Status"

  // Create the print content with styling
  let printContent = `
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Teachers List</title>
      <style>
        body {
          font-family: Arial, sans-serif;
          margin: 0;
          padding: 20px;
          color: #333;
        }
        .header {
          text-align: center;
          margin-bottom: 20px;
          padding-bottom: 10px;
          border-bottom: 2px solid #333;
        }
        .school-name {
          font-size: 24px;
          font-weight: bold;
          margin-bottom: 5px;
        }
        .report-title {
          font-size: 18px;
          margin-bottom: 5px;
        }
        .report-date {
          font-size: 14px;
          color: #666;
        }
        .filters {
          margin-bottom: 20px;
          padding: 10px;
          background-color: #f5f5f5;
          border-radius: 5px;
        }
        .filter-item {
          margin-bottom: 5px;
        }
        table {
          width: 100%;
          border-collapse: collapse;
          margin-bottom: 20px;
        }
        th, td {
          border: 1px solid #ddd;
          padding: 8px;
          text-align: left;
        }
        th {
          background-color: #f2f2f2;
          font-weight: bold;
        }
        tr:nth-child(even) {
          background-color: #f9f9f9;
        }
        .footer {
          margin-top: 30px;
          text-align: center;
          font-size: 12px;
          color: #666;
        }
        .status-active {
          color: green;
          font-weight: bold;
        }
        .status-inactive {
          color: red;
          font-weight: bold;
        }
        .status-pending {
          color: orange;
          font-weight: bold;
        }
        @media print {
          .no-print {
            display: none;
          }
          body {
            padding: 0;
            margin: 0;
          }
        }
      </style>
    </head>
    <body>
      <div class="header">
        <div class="school-name">Result Management System</div>
        <div class="report-title">Teachers List</div>
        <div class="report-date">Generated on: ${currentDate}</div>
      </div>
      
      <div class="filters">
        <div class="filter-item"><strong>Search:</strong> ${searchValue || "None"}</div>
        <div class="filter-item"><strong>Status:</strong> ${statusValue}</div>
      </div>
      
      <table>
        <thead>
          <tr>
            <th>Employee ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <!-- Department header removed -->
            <th>Qualification</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
  `

  // Get all rows from the table
  const tableRows = document.querySelectorAll("table tbody tr")

  // Add each row to the print content
  tableRows.forEach((row) => {
    // Skip if it's a "no teachers found" row
    if (row.cells.length === 1 && row.cells[0].colSpan > 1) {
      printContent += `<tr><td colspan="7" style="text-align: center;">No teachers found.</td></tr>`
      return
    }

    // Get the data from each cell
    const employeeId = row.cells[1]?.textContent.trim() || ""
    const name = row.cells[2]?.textContent.trim() || ""
    const email = row.cells[3]?.textContent.trim() || ""
    const department = row.cells[4]?.textContent.trim() || ""
    const qualification = row.cells[5]?.textContent.trim() || ""

    // Get status and apply appropriate class
    let status = ""
    let statusClass = ""
    if (row.cells[7]) {
      status = row.cells[7].textContent.trim()
      if (status.includes("Active")) {
        statusClass = "status-active"
        status = "Active"
      } else if (status.includes("Inactive")) {
        statusClass = "status-inactive"
        status = "Inactive"
      } else if (status.includes("Pending")) {
        statusClass = "status-pending"
        status = "Pending"
      }
    }

    // Get phone number from data attribute or cell content
    const phone = row.getAttribute("data-phone") || ""

    printContent += `
      <tr>
        <td>${employeeId}</td>
        <td>${name}</td>
        <td>${email}</td>
        <td>${phone}</td>
        <!-- Department cell removed -->
        <td>${qualification}</td>
        <td class="${statusClass}">${status}</td>
      </tr>
    `
  })

  // Complete the HTML structure
  printContent += `
        </tbody>
      </table>
      
      <div class="footer">
        <p>This is a computer-generated document. No signature is required.</p>
        <p>© ${new Date().getFullYear()} Result Management System</p>
      </div>
      
      <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print();" style="padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">Print Report</button>
        <button onclick="window.close();" style="padding: 10px 20px; background-color: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">Close</button>
      </div>
    </body>
    </html>
  `

  // Write the content to the new window and print
  printWindow.document.open()
  printWindow.document.write(printContent)
  printWindow.document.close()
}

// Assign new subject
function assignNewSubject() {
  const form = document.getElementById("assignSubjectForm")

  // Prevent multiple submissions
  if (form.dataset.submitting === "true") {
    console.log("Form already being submitted, preventing duplicate submission")
    return false
  }

  // Mark form as being submitted
  form.dataset.submitting = "true"

  // Disable submit button
  const submitButton = form.querySelector('button[type="submit"]')
  if (submitButton) {
    submitButton.disabled = true
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...'
  }

  const formData = new FormData(form)

  // Add a unique token to prevent duplicate submissions
  const token = Date.now().toString() + Math.random().toString(36).substr(2, 5)
  formData.append("submission_token", token)

  // Show loading indicator
  Swal.fire({
    title: "Processing...",
    text: "Assigning subject to teacher",
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading()
    },
  })

  // Log the form data for debugging
  console.log("Form data:", {
    teacher_id: formData.get("teacher_id"),
    subject_id: formData.get("subject_id"),
    class_id: formData.get("class_id"),
  })

  fetch("save_teacher_subjects.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      console.log("Response status:", response.status)
      return response.text().then((text) => {
        console.log("Raw response:", text)
        try {
          return JSON.parse(text)
        } catch (e) {
          console.error("Error parsing JSON:", e)
          throw new Error("Invalid JSON response: " + text)
        }
      })
    })
    .then((data) => {
      console.log("Parsed response:", data)
      if (data.success) {
        Swal.fire({
          title: "Success!",
          text: data.message,
          icon: "success",
          confirmButtonColor: "#3085d6",
        }).then(() => {
          // Get the teacher ID from the form
          const teacherId = formData.get("teacher_id")
          // Refresh the subjects modal
          showSubjectsModal(teacherId)
        })
      } else {
        Swal.fire({
          title: "Error!",
          text: data.message || "An unknown error occurred",
          icon: "error",
          confirmButtonColor: "#3085d6",
        })
        // Reset submission state
        form.dataset.submitting = "false"
        if (submitButton) {
          submitButton.disabled = false
          submitButton.innerHTML = "Assign Subject"
        }
      }
    })
    .catch((error) => {
      console.error("Error:", error)
      Swal.fire({
        title: "Error!",
        text: "An error occurred: " + error.message,
        icon: "error",
        confirmButtonColor: "#3085d6",
      })
      // Reset submission state
      form.dataset.submitting = "false"
      if (submitButton) {
        submitButton.disabled = false
        submitButton.innerHTML = "Assign Subject"
      }
    })

  return false // Prevent form submission
}

// Toggle subject status (activate/deactivate)
function toggleSubjectStatus(assignmentId, newStatus) {
  // Show confirmation dialog
  Swal.fire({
    title: newStatus ? "Activate Subject?" : "Deactivate Subject?",
    text: newStatus
      ? "This will make the subject active for this teacher."
      : "This will make the subject inactive for this teacher.",
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: newStatus ? "#3085d6" : "#d33",
    cancelButtonColor: "#6c757d",
    confirmButtonText: newStatus ? "Yes, activate it!" : "Yes, deactivate it!",
  }).then((result) => {
    if (result.isConfirmed) {
      // Show loading indicator
      Swal.fire({
        title: "Processing...",
        text: newStatus ? "Activating subject" : "Deactivating subject",
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading()
        },
      })

      // Get the teacher ID from the hidden input in the form
      const teacherId = document.querySelector('#assignSubjectForm input[name="teacher_id"]').value

      const formData = new FormData()
      formData.append("action", "toggle_status")
      formData.append("assignment_id", assignmentId)
      formData.append("status", newStatus)
      formData.append("teacher_id", teacherId)

      // Add a unique token to prevent duplicate submissions
      const token = Date.now().toString() + Math.random().toString(36).substr(2, 5)
      formData.append("submission_token", token)

      fetch("save_teacher_subjects.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.text())
        .then((text) => {
          try {
            return JSON.parse(text)
          } catch (e) {
            console.error("Error parsing JSON:", e)
            throw new Error("Invalid JSON response: " + text)
          }
        })
        .then((data) => {
          if (data.success) {
            Swal.fire({
              title: "Success!",
              text: data.message,
              icon: "success",
              confirmButtonColor: "#3085d6",
            }).then(() => {
              // Refresh the subjects modal
              showSubjectsModal(teacherId)
            })
          } else {
            Swal.fire({
              title: "Error!",
              text: data.message || "An unknown error occurred",
              icon: "error",
              confirmButtonColor: "#3085d6",
            })
          }
        })
        .catch((error) => {
          console.error("Error:", error)
          Swal.fire({
            title: "Error!",
            text: "An error occurred: " + error.message,
            icon: "error",
            confirmButtonColor: "#3085d6",
          })
        })
    }
  })
}

// Remove subject assignment
function removeSubject(assignmentId) {
  Swal.fire({
    title: "Remove Subject Assignment?",
    text: "Are you sure you want to remove this subject assignment? This action cannot be undone.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Yes, remove it!",
  }).then((result) => {
    if (result.isConfirmed) {
      // Show loading indicator
      Swal.fire({
        title: "Processing...",
        text: "Removing subject assignment",
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading()
        },
      })

      // Get the teacher ID from the hidden input in the form
      const teacherId = document.querySelector('#assignSubjectForm input[name="teacher_id"]').value

      const formData = new FormData()
      formData.append("action", "remove")
      formData.append("assignment_id", assignmentId)
      formData.append("teacher_id", teacherId)

      // Add a unique token to prevent duplicate submissions
      const token = Date.now().toString() + Math.random().toString(36).substr(2, 5)
      formData.append("submission_token", token)

      fetch("save_teacher_subjects.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.text())
        .then((text) => {
          try {
            return JSON.parse(text)
          } catch (e) {
            console.error("Error parsing JSON:", e)
            throw new Error("Invalid JSON response: " + text)
          }
        })
        .then((data) => {
          if (data.success) {
            Swal.fire({
              title: "Removed!",
              text: data.message,
              icon: "success",
              confirmButtonColor: "#3085d6",
            }).then(() => {
              // Refresh the subjects modal
              showSubjectsModal(teacherId)
            })
          } else {
            Swal.fire({
              title: "Error!",
              text: data.message || "An unknown error occurred",
              icon: "error",
              confirmButtonColor: "#3085d6",
            })
          }
        })
        .catch((error) => {
          console.error("Error:", error)
          Swal.fire({
            title: "Error!",
            text: "An error occurred: " + error.message,
            icon: "error",
            confirmButtonColor: "#3085d6",
          })
        })
    }
  })
}

// Add this function to handle student form submission
function saveNewStudent() {
  const form = document.getElementById("addStudentForm")

  // Prevent multiple submissions
  if (form.dataset.submitting === "true") {
    console.log("Form already being submitted, preventing duplicate submission")
    return false
  }

  // Mark form as being submitted
  form.dataset.submitting = "true"

  // Disable submit button
  const submitButton = form.querySelector('button[type="submit"]')
  if (submitButton) {
    submitButton.disabled = true
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...'
  }

  const formData = new FormData(form)

  // Validate passwords match
  const password = formData.get("password")
  const confirmPassword = formData.get("confirm_password")

  if (password !== confirmPassword) {
    Swal.fire({
      title: "Error!",
      text: "Passwords do not match.",
      icon: "error",
      confirmButtonColor: "#3085d6",
    })
    // Reset submission state
    form.dataset.submitting = "false"
    if (submitButton) {
      submitButton.disabled = false
      submitButton.innerHTML = "Add Student"
    }
    return false
  }

  // Add a unique token to prevent duplicate submissions
  const token = Date.now().toString() + Math.random().toString(36).substr(2, 5)
  formData.append("submission_token", token)

  // Show loading state
  Swal.fire({
    title: "Saving...",
    html: "Please wait while we add the new student.",
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading()
    },
  })

  // Send AJAX request
  fetch("save_new_student.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) {
        return response.text().then((text) => {
          throw new Error("Server error: " + (text || response.statusText))
        })
      }
      return response.json()
    })
    .then((data) => {
      if (data.success) {
        Swal.fire({
          title: "Success!",
          text: data.message,
          icon: "success",
          confirmButtonColor: "#3085d6",
        }).then(() => {
          closeAddStudentModal()
          window.location.reload()
        })
      } else {
        Swal.fire({
          title: "Error!",
          text: data.message || "An unknown error occurred.",
          icon: "error",
          confirmButtonColor: "#3085d6",
        })
        // Reset submission state
        form.dataset.submitting = "false"
        if (submitButton) {
          submitButton.disabled = false
          submitButton.innerHTML = "Add Student"
        }
      }
    })
    .catch((error) => {
      console.error("Error:", error)
      Swal.fire({
        title: "Error!",
        text: "An error occurred while saving: " + error.message,
        icon: "error",
        confirmButtonColor: "#3085d6",
      })
      // Reset submission state
      form.dataset.submitting = "false"
      if (submitButton) {
        submitButton.disabled = false
        submitButton.innerHTML = "Add Student"
      }
    })

  return false
}

// Add these functions for student modal handling
function showAddStudentModal() {
  document.getElementById("addStudentModal").classList.remove("hidden")
}

function closeAddStudentModal() {
  document.getElementById("addStudentModal").classList.add("hidden")
  // Reset the form submission state
  const form = document.getElementById("addStudentForm")
  if (form) {
    form.dataset.submitting = "false"
    const submitButton = form.querySelector('button[type="submit"]')
    if (submitButton) {
      submitButton.disabled = false
      submitButton.innerHTML = "Add Student"
    }
  }
}
