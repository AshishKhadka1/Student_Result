<?php
/**
 * Grade Calculator based on NEB (National Examination Board) Guidelines
 * This file contains functions to calculate grades and GPA based on marks
 */

/**
 * Calculate grade based on marks according to NEB guidelines
 * 
 * @param float $marks The marks obtained
 * @param float $total_marks The total marks for the subject
 * @return string The letter grade
 */
function calculateGrade($marks, $total_marks = 100) {
    // Return empty string if marks is null or empty
    if ($marks === null || $marks === '') {
        return '';
    }
    
    // Convert marks to percentage if total marks is not 100
    if ($total_marks != 100) {
        $percentage = ($marks / $total_marks) * 100;
    } else {
        $percentage = $marks;
    }
    
    // NEB Grade System
    if ($percentage >= 90) {
        return 'A+';
    } elseif ($percentage >= 80) {
        return 'A';
    } elseif ($percentage >= 70) {
        return 'B+';
    } elseif ($percentage >= 60) {
        return 'B';
    } elseif ($percentage >= 50) {
        return 'C+';
    } elseif ($percentage >= 40) {
        return 'C';
    } elseif ($percentage >= 30) {
        return 'D+';
    } elseif ($percentage >= 20) {
        return 'D';
    } else {
        return 'E';
    }
}

/**
 * Calculate grade point based on letter grade
 * 
 * @param string $grade The letter grade
 * @return float The grade point
 */
function calculateGradePoint($grade) {
    switch ($grade) {
        case 'A+': return 4.0;
        case 'A': return 3.6;
        case 'B+': return 3.2;
        case 'B': return 2.8;
        case 'C+': return 2.4;
        case 'C': return 2.0;
        case 'D+': return 1.6;
        case 'D': return 1.2;
        case 'E': return 0.8;
        default: return 0.0;
    }
}

/**
 * Calculate GPA based on grade points and credit hours
 * 
 * @param array $subjects Array of subjects with grade points and credit hours
 * @return float The calculated GPA
 */
function calculateGPA($subjects) {
    $totalPoints = 0;
    $totalCredits = 0;
    
    foreach ($subjects as $subject) {
        $totalPoints += $subject['grade_point'] * $subject['credit_hours'];
        $totalCredits += $subject['credit_hours'];
    }
    
    if ($totalCredits == 0) {
        return 0;
    }
    
    $gpa = $totalPoints / $totalCredits;
    return round($gpa, 2);
}

/**
 * Get remarks based on grade
 * 
 * @param string $grade The letter grade
 * @return string The remarks
 */
function getRemarks($grade) {
    switch ($grade) {
        case 'A+': return 'Outstanding';
        case 'A': return 'Excellent';
        case 'B+': return 'Very Good';
        case 'B': return 'Good';
        case 'C+': return 'Satisfactory';
        case 'C': return 'Acceptable';
        case 'D+': return 'Partially Acceptable';
        case 'D': return 'Insufficient';
        case 'E': return 'Very Insufficient';
        default: return '';
    }
}

/**
* Calculate final grade based on theory and practical marks
* 
* @param float $theory_marks Theory marks
* @param float $practical_marks Practical marks
* @param float $theory_total Total theory marks
* @param float $practical_total Total practical marks
* @return array Array containing total marks, percentage, grade, grade point and remarks
*/
function calculateFinalGrade($theory_marks, $practical_marks, $theory_total, $practical_total) {
   $total_marks = $theory_marks + $practical_marks;
   $total_possible = $theory_total + $practical_total;
   $percentage = ($total_marks / $total_possible) * 100;
   
   $grade = calculateGrade($total_marks, $total_possible);
   $grade_point = calculateGradePoint($grade);
   $remarks = getRemarks($grade);
   
   return [
       'total_marks' => $total_marks,
       'percentage' => round($percentage, 2),
       'grade' => $grade,
       'grade_point' => $grade_point,
       'remarks' => $remarks
   ];
}

/**
 * Get division based on GPA
 * 
 * @param float $gpa The GPA
 * @return string The division
 */
function getDivision($gpa) {
    if ($gpa >= 3.6) {
        return 'Distinction';
    } elseif ($gpa >= 3.2) {
        return 'First Division';
    } elseif ($gpa >= 2.8) {
        return 'Second Division';
    } elseif ($gpa >= 2.0) {
        return 'Third Division';
    } else {
        return 'Fail';
    }
}

/**
 * Convert percentage to grade
 * 
 * @param float $percentage The percentage
 * @return string The letter grade
 */
function percentageToGrade($percentage) {
    if ($percentage >= 90) {
        return 'A+';
    } elseif ($percentage >= 80) {
        return 'A';
    } elseif ($percentage >= 70) {
        return 'B+';
    } elseif ($percentage >= 60) {
        return 'B';
    } elseif ($percentage >= 50) {
        return 'C+';
    } elseif ($percentage >= 40) {
        return 'C';
    } elseif ($percentage >= 30) {
        return 'D+';
    } elseif ($percentage >= 20) {
        return 'D';
    } else {
        return 'E';
    }
}

/**
 * Convert grade to percentage range
 * 
 * @param string $grade The letter grade
 * @return string The percentage range
 */
function gradeToPercentageRange($grade) {
    switch ($grade) {
        case 'A+': return '90-100%';
        case 'A': return '80-89%';
        case 'B+': return '70-79%';
        case 'B': return '60-69%';
        case 'C+': return '50-59%';
        case 'C': return '40-49%';
        case 'D+': return '30-39%';
        case 'D': return '20-29%';
        case 'E': return 'Below 20%';
        default: return '';
    }
}
?>

