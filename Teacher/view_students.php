<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
   header("Location: ../login.php");
   exit();
}

include '../includes/db_connetc.php';

// Get teacher information
$teacher_id = $_SESSION['user_id'];
$teacher_record_id = '';

$stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
   $teacher_record_id = $row['teacher_id'];
}
$stmt->close();

// Get filter values
$class_filter = isset($_GET['class_id']) ? $_GET['class_id'] : '';
$subject_filter = isset($_GET['subject_id']) ? $_GET['subject_id'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$show_all = isset($_GET['show_all']) ? $_GET['show_all'] : 'no'; // Default to showing only teacher's students

// Get all classes for filter dropdown
$all_classes = [];
$classes_query = "SELECT class_id, class_name, section FROM classes ORDER BY class_name, section";
$classes_result = $conn->query($classes_query);
while ($class = $classes_result->fetch_assoc()) {
   $all_classes[] = [
       'class_id' => $class['class_id'],
       'class_name' => $class['class_name'] . ' ' . $class['section']
   ];
}

// Get classes taught by this teacher
$teacher_classes = [];
$stmt = $conn->prepare("
   SELECT DISTINCT c.class_id, CONCAT(c.class_name, ' ', c.section) as class_name
   FROM classes c
   JOIN teachersubjects ts ON c.class_id = ts.class_id
   WHERE ts.teacher_id = ?
   ORDER BY c.class_name
");

if ($stmt === false) {
   // If the query fails, try a simpler approach
   $teacher_classes = $all_classes; // Fallback to all classes
} else {
   $stmt->bind_param("s", $teacher_record_id);
   $stmt->execute();
   $result = $stmt->get_result();
   while ($row = $result->fetch_assoc()) {
       $teacher_classes[] = $row;
   }
   $stmt->close();
}

// Get subjects taught by this teacher
$teacher_subjects = [];
$subjects_query = "
   SELECT DISTINCT s.subject_id, s.subject_name
   FROM subjects s
   JOIN teachersubjects ts ON s.subject_id = ts.subject_id
   WHERE ts.teacher_id = ?
   ORDER BY s.subject_name
";

$subjects_stmt = $conn->prepare($subjects_query);
if ($subjects_stmt) {
   $subjects_stmt->bind_param("s", $teacher_record_id);
   $subjects_stmt->execute();
   $subjects_result = $subjects_stmt->get_result();
   while ($subject = $subjects_result->fetch_assoc()) {
       $teacher_subjects[] = $subject;
   }
   $subjects_stmt->close();
}

// Build query based on filters
if ($show_all == 'yes') {
   // Show all students
   $query = "
       SELECT u.user_id, u.full_name, u.email, u.status, u.phone,
              s.student_id, s.roll_number, s.batch_year,
              c.class_id, CONCAT(c.class_name, ' ', c.section) as class_name
       FROM users u
       JOIN students s ON u.user_id = s.user_id
       LEFT JOIN classes c ON s.class_id = c.class_id
       WHERE u.role = 'student'
   ";
   
   $params = [];
   $types = "";
   
   if (!empty($class_filter)) {
       $query .= " AND c.class_id = ?";
       $params[] = $class_filter;
       $types .= "s";
   }
} else {
   // Show only students in classes taught by this teacher
   $query = "
       SELECT DISTINCT u.user_id, u.full_name, u.email, u.status, u.phone,
              s.student_id, s.roll_number, s.batch_year,
              c.class_id, CONCAT(c.class_name, ' ', c.section) as class_name
       FROM users u
       JOIN students s ON u.user_id = s.user_id
       JOIN classes c ON s.class_id = c.class_id
       JOIN teachersubjects ts ON c.class_id = ts.class_id
       WHERE u.role = 'student' AND ts.teacher_id = ?
   ";
   
   $params = [$teacher_record_id];
   $types = "s";
   
   if (!empty($class_filter)) {
       $query .= " AND c.class_id = ?";
       $params[] = $class_filter;
       $types .= "s";
   }
   
   if (!empty($subject_filter)) {
       $query .= " AND ts.subject_id = ?";
       $params[] = $subject_filter;
       $types .= "s";
   }
}

if (!empty($search)) {
   $search_term = "%$search%";
   $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR s.student_id LIKE ? OR s.roll_number LIKE ?)";
   $params[] = $search_term;
   $params[] = $search_term;
   $params[] = $search_term;
   $params[] = $search_term;
   $types .= "ssss";
}

$query .= " ORDER BY c.class_name, s.roll_number";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if ($stmt === false) {
   die("Error preparing statement: " . $conn->error);
}

if (!empty($types)) {
   $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Fetch all students
$students = [];
while ($row = $result->fetch_assoc()) {
   // Get subjects taught by this teacher to this student
   $student_subjects_query = "
       SELECT DISTINCT s.subject_id, s.subject_name
       FROM subjects s
       JOIN teachersubjects ts ON s.subject_id = ts.subject_id
       WHERE ts.teacher_id = ? AND ts.class_id = ?
       ORDER BY s.subject_name
   ";
   
   $student_subjects_stmt = $conn->prepare($student_subjects_query);
   $student_subjects = [];
   
   if ($student_subjects_stmt) {
       $student_subjects_stmt->bind_param("ss", $teacher_record_id, $row['class_id']);
       $student_subjects_stmt->execute();
       $student_subjects_result = $student_subjects_stmt->get_result();
       
       while ($subject = $student_subjects_result->fetch_assoc()) {
           $student_subjects[] = $subject;
       }
       $student_subjects_stmt->close();
   }
   
   $row['subjects'] = $student_subjects;
   $students[] = $row;
}
$stmt->close();

// Get counts
$total_students = count($students);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>View Students | Teacher Dashboard</title>
   <link href="https://cdn.tailwindcss.com" rel="stylesheet">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <style>
       .student-card {
           transition: all 0.3s ease;
       }
       .student-card:hover {
           transform: translateY(-5px);
           box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
       }
       /* Sidebar styles */
       .sidebar-link {
           transition: all 0.2s ease;
       }
       .sidebar-link:hover {
           background-color: rgba(255, 255, 255, 0.1);
       }
       .sidebar-link.active {
           background-color: rgba(255, 255, 255, 0.2);
           border-left: 4px solid #fff;
       }
       /* Mobile sidebar */
       .mobile-sidebar {
           transition: transform 0.3s ease-in-out;
       }
   </style>
</head>
<body class="bg-gray-50">
   <div class="flex h-screen overflow-hidden">
       <!-- Sidebar - Desktop -->
        <!-- Sidebar -->
        <?php include 'includes/teacher_sidebar.php'; ?>

        <!-- Main Content -->
        <!-- <div class="flex flex-col flex-1 w-0 overflow-hidden"> -->
            <!-- Top Navigation -->

       <!-- Main Content -->
       <div class="flex flex-col flex-1 w-0 overflow-hidden">
           <!-- Top Navigation -->
           <div class="relative z-10 flex-shrink-0 flex h-16 bg-white shadow">
             
               
            
           </div>

           <!-- Main Content -->
           <main class="flex-1 relative overflow-y-auto focus:outline-none">
               <div class="py-6">
                   <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                       <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                           <h1 class="text-2xl font-semibold text-gray-900">Students</h1>
                           <div class="mt-4 md:mt-0">
                               <span class="bg-blue-100 text-blue-800 text-xs font-medium px-3 py-1.5 rounded-full">
                                   Total: <?php echo $total_students; ?> Students
                               </span>
                           </div>
                       </div>
                       
                       <!-- Notification Messages -->
                       <?php if(isset($_SESSION['success'])): ?>
                       <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
                           <div class="flex">
                               <div class="flex-shrink-0">
                                   <i class="fas fa-check-circle text-green-500"></i>
                               </div>
                               <div class="ml-3">
                                   <p class="text-sm text-green-700">
                                       <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                                   </p>
                               </div>
                           </div>
                       </div>
                       <?php endif; ?>

                       <?php if(isset($_SESSION['error'])): ?>
                       <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
                           <div class="flex">
                               <div class="flex-shrink-0">
                                   <i class="fas fa-exclamation-circle text-red-500"></i>
                               </div>
                               <div class="ml-3">
                                   <p class="text-sm text-red-700">
                                       <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                                   </p>
                               </div>
                           </div>
                       </div>
                       <?php endif; ?>

                       <!-- Filter and Search -->
                       <div class="bg-white shadow rounded-lg p-6 mb-6">
                           <form action="view_students.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                               <div>
                                   <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">Filter by Class</label>
                                   <select id="class_id" name="class_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                       <option value="">All Classes</option>
                                       <?php foreach ($teacher_classes as $class): ?>
                                       <option value="<?php echo $class['class_id']; ?>" <?php echo $class_filter == $class['class_id'] ? 'selected' : ''; ?>>
                                           <?php echo $class['class_name']; ?>
                                       </option>
                                       <?php endforeach; ?>
                                   </select>
                               </div>
                               
                               <div>
                                   <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">Filter by Subject</label>
                                   <select id="subject_id" name="subject_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                       <option value="">All Subjects</option>
                                       <?php foreach ($teacher_subjects as $subject): ?>
                                       <option value="<?php echo $subject['subject_id']; ?>" <?php echo $subject_filter == $subject['subject_id'] ? 'selected' : ''; ?>>
                                           <?php echo $subject['subject_name']; ?>
                                       </option>
                                       <?php endforeach; ?>
                                   </select>
                               </div>
                               
                               <div>
                                   <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search Students</label>
                                   <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, ID, or Roll Number" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                               </div>
                               
                               <div class="flex items-end">
                                   <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                       <i class="fas fa-filter mr-2"></i> Apply Filters
                                   </button>
                                   <a href="view_students.php" class="ml-2 inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                       <i class="fas fa-times mr-2"></i> Clear
                                   </a>
                               </div>
                           </form>
                       </div>

                       <?php if (empty($students)): ?>
                           <div class="bg-white shadow rounded-lg p-8 text-center">
                               <div class="text-gray-500 mb-4">
                                   <i class="fas fa-user-graduate text-5xl"></i>
                               </div>
                               <h3 class="text-lg font-medium text-gray-900 mb-2">No Students Found</h3>
                               <p class="text-gray-500">There are no students matching your search criteria.</p>
                           </div>
                       <?php else: ?>
                           <!-- Students Grid View -->
                           <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                               <?php foreach ($students as $student): ?>
                                   <div class="bg-white shadow rounded-lg overflow-hidden transition-all duration-300 student-card">
                                       <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-white">
                                           <h3 class="font-medium truncate">
                                               <?php echo !empty($student['class_name']) ? $student['class_name'] : 'No Class Assigned'; ?>
                                           </h3>
                                       </div>
                                       <div class="p-5">
                                           <div class="flex items-center mb-4">
                                               <div class="flex-shrink-0 h-12 w-12">
                                                   <span class="inline-flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 text-blue-800">
                                                       <span class="text-lg font-medium"><?php echo substr($student['full_name'], 0, 1); ?></span>
                                                   </span>
                                               </div>
                                               <div class="ml-4">
                                                   <h4 class="text-lg font-medium text-gray-900"><?php echo $student['full_name']; ?></h4>
                                                   <p class="text-sm text-gray-500"><?php echo $student['email']; ?></p>
                                               </div>
                                           </div>
                                           
                                           <div class="grid grid-cols-2 gap-4 mb-4">
                                               <div>
                                                   <p class="text-xs text-gray-500">Student ID</p>
                                                   <p class="text-sm font-medium"><?php echo $student['student_id']; ?></p>
                                               </div>
                                               <div>
                                                   <p class="text-xs text-gray-500">Roll Number</p>
                                                   <p class="text-sm font-medium"><?php echo $student['roll_number']; ?></p>
                                               </div>
                                               <div>
                                                   <p class="text-xs text-gray-500">Batch Year</p>
                                                   <p class="text-sm font-medium"><?php echo $student['batch_year']; ?></p>
                                               </div>
                                               <div>
                                                   <p class="text-xs text-gray-500">Status</p>
                                                   <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                       <?php echo $student['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                       <?php echo ucfirst($student['status']); ?>
                                                   </span>
                                               </div>
                                           </div>
                                           
                                           <div class="border-t border-gray-200 pt-4">
                                               <p class="text-sm font-medium text-gray-700 mb-2">Subjects:</p>
                                               <?php if (!empty($student['subjects'])): ?>
                                                   <div class="flex flex-wrap gap-2 mb-3">
                                                       <?php foreach ($student['subjects'] as $subject): ?>
                                                           <a href="student_results.php?student_id=<?php echo $student['student_id']; ?>&subject_id=<?php echo $subject['subject_id']; ?>" 
                                                              class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full hover:bg-blue-200">
                                                               <?php echo $subject['subject_name']; ?>
                                                           </a>
                                                       <?php endforeach; ?>
                                                   </div>
                                               <?php else: ?>
                                                   <p class="text-sm text-gray-500 mb-3">No subjects assigned</p>
                                               <?php endif; ?>
                                               
                                               <div class="flex justify-between">
                                                   <a href="student_profile.php?student_id=<?php echo $student['student_id']; ?>" class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                                                       <i class="fas fa-user mr-1"></i> View Profile
                                                   </a>
                                                   <?php if (!empty($student['subjects']) && count($student['subjects']) === 1): ?>
                                                       <a href="student_results.php?student_id=<?php echo $student['student_id']; ?>&subject_id=<?php echo $student['subjects'][0]['subject_id']; ?>" class="text-green-600 hover:text-green-900 text-sm font-medium">
                                                           <i class="fas fa-chart-bar mr-1"></i> View Results
                                                       </a>
                                                   <?php endif; ?>
                                               </div>
                                           </div>
                                       </div>
                                   </div>
                               <?php endforeach; ?>
                           </div>
                       <?php endif; ?>
                   </div>
               </div>
           </main>
       </div>
   </div>

   <script>
       // Mobile sidebar toggle
       document.addEventListener('DOMContentLoaded', function() {
           const sidebarToggle = document.getElementById('sidebar-toggle');
           const closeSidebar = document.getElementById('close-sidebar');
           const sidebarBackdrop = document.getElementById('sidebar-backdrop');
           const mobileSidebar = document.getElementById('mobile-sidebar');
           
           if (sidebarToggle) {
               sidebarToggle.addEventListener('click', function() {
                   mobileSidebar.classList.remove('-translate-x-full');
               });
           }
           
           if (closeSidebar) {
               closeSidebar.addEventListener('click', function() {
                   mobileSidebar.classList.add('-translate-x-full');
               });
           }
           
           if (sidebarBackdrop) {
               sidebarBackdrop.addEventListener('click', function() {
                   mobileSidebar.classList.add('-translate-x-full');
               });
           }
           
           // User menu toggle
           const userMenuButton = document.getElementById('user-menu-button');
           const userMenu = document.getElementById('user-menu');
           
           if (userMenuButton && userMenu) {
               userMenuButton.addEventListener('click', function() {
                   userMenu.classList.toggle('hidden');
               });
               
               // Close user menu when clicking outside
               document.addEventListener('click', function(event) {
                   if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                       userMenu.classList.add('hidden');
                   }
               });
           }
       });
   </script>
</body>
</html>
