<?php
$subjects = [
    ['code' => '002', 'name' => 'COMPULSORY NEPALI', 'credit' => 5],
    ['code' => '004', 'name' => 'COMPULSORY ENGLISH', 'credit' => 5],
    ['code' => '006', 'name' => 'MATHEMATICS', 'credit' => 5],
    ['code' => '008', 'name' => 'SCIENCE AND TECHNOLOGY', 'credit' => 5],
    ['code' => '010', 'name' => 'SOCIAL STUDIES', 'credit' => 4],
    ['code' => '102', 'name' => 'OPTIONAL I. MATHEMATICS', 'credit' => 4],
    ['code' => '202', 'name' => 'OPTIONAL II. SCIENCE', 'credit' => 4],
];

// Grade options for dropdowns
$grades = [
    'A+' => 4.0, 'A' => 3.6, 'B+' => 3.2, 
    'B' => 2.8, 'C+' => 2.4, 'C' => 2.0, 
    'D+' => 1.6, 'D' => 1.2, 'E' => 0.8
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Grades Table</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .grade-select {
            min-width: 80px;
        }
        .sticky-header {
            position: sticky;
            top: 0;
            background: white;
            z-index: 100;
            box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
        }
        @media (max-width: 768px) {
            .table th, .table td {
                padding: 0.5rem;
                font-size: 0.875rem;
            }
            .grade-select {
                min-width: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Student Grades</h4>
            </div>
            <div class="card-body">
                <form id="gradesForm">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-4">
                            <thead class="sticky-header">
                                <tr class="table-light">
                                    <th class="text-center">CODE</th>
                                    <th>SUBJECT</th>
                                    <th class="text-center">CREDIT</th>
                                    <th colspan="2" class="text-center">INTERNAL</th>
                                    <th colspan="2" class="text-center">EXTERNAL</th>
                                    <th class="text-center">FINAL</th>
                                    <th class="text-center">REMARKS</th>
                                </tr>
                                <tr class="table-light">
                                    <th colspan="3"></th>
                                    <th class="text-center">Point</th>
                                    <th class="text-center">Grade</th>
                                    <th class="text-center">Point</th>
                                    <th class="text-center">Grade</th>
                                    <th class="text-center">Grade</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjects as $subject): ?>
                                <tr>
                                    <td class="text-center"><?= htmlspecialchars($subject['code']) ?></td>
                                    <td><?= htmlspecialchars($subject['name']) ?></td>
                                    <td class="text-center"><?= $subject['credit'] ?></td>
                                    
                                    <!-- Internal Grade Point -->
                                    <td class="text-center">
                                        <input type="number" class="form-control form-control-sm grade-point" 
                                               name="internal_point[<?= $subject['code'] ?>]" 
                                               min="0" max="4" step="0.1" placeholder="0-4">
                                    </td>
                                    
                                    <!-- Internal Grade -->
                                    <td class="text-center">
                                        <select class="form-select form-select-sm grade-select internal-grade" 
                                                name="internal_grade[<?= $subject['code'] ?>]">
                                            <option value="">Select</option>
                                            <?php foreach ($grades as $grade => $point): ?>
                                                <option value="<?= $point ?>" data-grade="<?= $grade ?>">
                                                    <?= $grade ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    
                                    <!-- External Grade Point -->
                                    <td class="text-center">
                                        <input type="number" class="form-control form-control-sm grade-point" 
                                               name="external_point[<?= $subject['code'] ?>]" 
                                               min="0" max="4" step="0.1" placeholder="0-4">
                                    </td>
                                    
                                    <!-- External Grade -->
                                    <td class="text-center">
                                        <select class="form-select form-select-sm grade-select external-grade" 
                                                name="external_grade[<?= $subject['code'] ?>]">
                                            <option value="">Select</option>
                                            <?php foreach ($grades as $grade => $point): ?>
                                                <option value="<?= $point ?>" data-grade="<?= $grade ?>">
                                                    <?= $grade ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    
                                    <!-- Final Grade (auto-calculated) -->
                                    <td class="text-center final-grade">
                                        -
                                    </td>
                                    
                                    <!-- Remarks -->
                                    <td class="text-center remarks">
                                        -
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Grade Information</h5>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Grade</th>
                                                    <th>Point</th>
                                                    <th>Percentage</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr><td>A+</td><td>4.0</td><td>90% and above</td></tr>
                                                <tr><td>A</td><td>3.6</td><td>80-89%</td></tr>
                                                <tr><td>B+</td><td>3.2</td><td>70-79%</td></tr>
                                                <tr><td>B</td><td>2.8</td><td>60-69%</td></tr>
                                                <tr><td>C+</td><td>2.4</td><td>50-59%</td></tr>
                                                <tr><td>C</td><td>2.0</td><td>40-49%</td></tr>
                                                <tr><td>D+</td><td>1.6</td><td>30-39%</td></tr>
                                                <tr><td>D</td><td>1.2</td><td>20-29%</td></tr>
                                                <tr><td>E</td><td>0.8</td><td>Below 20%</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Results</h5>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="fw-bold">Grade Point Average (GPA):</span>
                                        <span class="badge bg-primary fs-6" id="gpaResult">0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold">Final Result:</span>
                                        <span class="badge bg-success fs-6" id="finalResult">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </button>
                        <button type="button" class="btn btn-primary" id="calculateBtn">
                            <i class="bi bi-calculator"></i> Calculate GPA
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sync grade points and grade letters
            const syncGrade = (selectElement, pointElement) => {
                if (selectElement.value) {
                    pointElement.value = selectElement.value;
                    const selectedOption = selectElement.options[selectElement.selectedIndex];
                    return selectedOption.getAttribute('data-grade');
                }
                return null;
            };

            // Calculate final grade
            const calculateFinalGrade = (internalPoint, externalPoint) => {
                if (internalPoint && externalPoint) {
                    const finalPoint = (parseFloat(internalPoint) * 0.4) + (parseFloat(externalPoint) * 0.6);
                    return finalPoint.toFixed(2);
                }
                return null;
            };

            // Determine remarks based on final grade
            const getRemarks = (finalPoint) => {
                if (!finalPoint) return '-';
                
                finalPoint = parseFloat(finalPoint);
                if (finalPoint >= 3.6) return 'Excellent';
                if (finalPoint >= 2.8) return 'Good';
                if (finalPoint >= 2.0) return 'Satisfactory';
                if (finalPoint >= 1.2) return 'Needs Improvement';
                return 'Poor';
            };

            // Grade letter from point
            const getGradeLetter = (point) => {
                point = parseFloat(point);
                if (point >= 3.6) return 'A+';
                if (point >= 3.2) return 'A';
                if (point >= 2.8) return 'B+';
                if (point >= 2.4) return 'B';
                if (point >= 2.0) return 'C+';
                if (point >= 1.6) return 'C';
                if (point >= 1.2) return 'D+';
                if (point >= 0.8) return 'D';
                return 'E';
            };

            // Event listeners for grade selects
            document.querySelectorAll('.internal-grade, .external-grade').forEach(select => {
                select.addEventListener('change', function() {
                    const row = this.closest('tr');
                    const isInternal = this.classList.contains('internal-grade');
                    const pointInput = isInternal 
                        ? row.querySelector('input[name^="internal_point"]')
                        : row.querySelector('input[name^="external_point"]');
                    
                    const gradeLetter = syncGrade(this, pointInput);
                    
                    // Calculate final grade when both are set
                    const internalPoint = row.querySelector('input[name^="internal_point"]').value;
                    const externalPoint = row.querySelector('input[name^="external_point"]').value;
                    
                    const finalPoint = calculateFinalGrade(internalPoint, externalPoint);
                    if (finalPoint) {
                        const finalGradeCell = row.querySelector('.final-grade');
                        const remarksCell = row.querySelector('.remarks');
                        
                        finalGradeCell.textContent = getGradeLetter(finalPoint);
                        finalGradeCell.dataset.point = finalPoint;
                        remarksCell.textContent = getRemarks(finalPoint);
                    }
                });
            });

            // Event listeners for grade points
            document.querySelectorAll('.grade-point').forEach(input => {
                input.addEventListener('input', function() {
                    const row = this.closest('tr');
                    const isInternal = this.name.startsWith('internal_point');
                    const gradeSelect = isInternal 
                        ? row.querySelector('select[name^="internal_grade"]')
                        : row.querySelector('select[name^="external_grade"]');
                    
                    // Find matching grade for the point value
                    if (this.value) {
                        const pointValue = parseFloat(this.value);
                        let closestGrade = '';
                        let closestPoint = -1;
                        
                        gradeSelect.querySelectorAll('option').forEach(option => {
                            if (option.value && Math.abs(parseFloat(option.value) - pointValue) < Math.abs(closestPoint - pointValue)) {
                                closestPoint = parseFloat(option.value);
                                closestGrade = option.getAttribute('data-grade');
                            }
                        });
                        
                        if (closestGrade) {
                            gradeSelect.value = closestPoint;
                        }
                    } else {
                        gradeSelect.value = '';
                    }
                    
                    // Calculate final grade when both are set
                    const internalPoint = row.querySelector('input[name^="internal_point"]').value;
                    const externalPoint = row.querySelector('input[name^="external_point"]').value;
                    
                    const finalPoint = calculateFinalGrade(internalPoint, externalPoint);
                    if (finalPoint) {
                        const finalGradeCell = row.querySelector('.final-grade');
                        const remarksCell = row.querySelector('.remarks');
                        
                        finalGradeCell.textContent = getGradeLetter(finalPoint);
                        finalGradeCell.dataset.point = finalPoint;
                        remarksCell.textContent = getRemarks(finalPoint);
                    }
                });
            });

            // Calculate GPA button
            document.getElementById('calculateBtn').addEventListener('click', function() {
                let totalPoints = 0;
                let totalCredits = 0;
                let allSubjectsPassed = true;
                
                document.querySelectorAll('tbody tr').forEach(row => {
                    const credit = parseFloat(row.querySelector('td:nth-child(3)').textContent);
                    const finalGradeCell = row.querySelector('.final-grade');
                    const finalPoint = parseFloat(finalGradeCell.dataset.point || 0);
                    
                    if (finalPoint > 0) {
                        totalPoints += finalPoint * credit;
                        totalCredits += credit;
                        
                        if (finalPoint < 2.0) {
                            allSubjectsPassed = false;
                        }
                    }
                });
                
                const gpa = totalCredits > 0 ? (totalPoints / totalCredits).toFixed(2) : 0;
                document.getElementById('gpaResult').textContent = gpa;
                
                const finalResult = document.getElementById('finalResult');
                if (totalCredits === 0) {
                    finalResult.textContent = 'Incomplete';
                    finalResult.className = 'badge bg-secondary fs-6';
                } else if (!allSubjectsPassed) {
                    finalResult.textContent = 'Fail';
                    finalResult.className = 'badge bg-danger fs-6';
                } else if (gpa >= 3.6) {
                    finalResult.textContent = 'Distinction';
                    finalResult.className = 'badge bg-success fs-6';
                } else if (gpa >= 3.2) {
                    finalResult.textContent = 'First Division';
                    finalResult.className = 'badge bg-success fs-6';
                } else if (gpa >= 2.8) {
                    finalResult.textContent = 'Second Division';
                    finalResult.className = 'badge bg-primary fs-6';
                } else if (gpa >= 2.0) {
                    finalResult.textContent = 'Pass';
                    finalResult.className = 'badge bg-info fs-6';
                } else {
                    finalResult.textContent = 'Fail';
                    finalResult.className = 'badge bg-danger fs-6';
                }
            });
        });
    </script>
</body>
</html>