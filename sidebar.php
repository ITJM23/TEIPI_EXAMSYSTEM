<?php
include "includes/sessions.php";
include "includes/db.php";

// Get employee name
$EMP_ID = $_COOKIE['EIMS_emp_Id'] ?? '';
$fullname = "No Employee ID found";

if (!empty($EMP_ID)) {
    $sql = "SELECT Fname, Lname FROM teipi_emp3.teipi_emp3.emp_info WHERE Emp_Id = ?";
    $params = [$EMP_ID];

    if (isset($con3) && $con3) {
        $stmt = sqlsrv_query($con3, $sql, $params);
        if ($stmt && sqlsrv_has_rows($stmt)) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $fullname = trim($row['Fname'] . ' ' . $row['Lname']);
        } else {
            $fullname = "Unknown User";
        }
        if ($stmt) sqlsrv_free_stmt($stmt);
    } else {
        $fullname = "Database error";
    }
}
?>

<!-- Sidebar Layout -->
<div style="
    width: 250px;
    background-color: #1e293b;
    height: 100vh;
    color: #f1f5f9;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    font-family: Arial, sans-serif;
">
    <!-- Top Section -->
    <div style="padding: 20px;">
        <h2 style="margin-bottom: 10px; font-size: 18px; color: #93c5fd;">Welcome</h2>
        <p style="font-size: 16px; margin-bottom: 30px; font-weight: bold;">
            <?php echo htmlspecialchars($fullname); ?>
        </p>

        <ul style="list-style: none; padding: 0; margin: 0;">
            <li style="margin-bottom: 12px;">
                <a href="index.php" style="display: block; color: #f1f5f9; text-decoration: none; padding: 10px; border-radius: 6px;"
                   onmouseover="this.style.background='#334155'" onmouseout="this.style.background='transparent'">🏠 Dashboard</a>
            </li>

            <li style="margin-bottom: 12px;">
                <a href="exam_list.php" style="display: block; color: #f1f5f9; text-decoration: none; padding: 10px; border-radius: 6px;"
                   onmouseover="this.style.background='#334155'" onmouseout="this.style.background='transparent'">📝 Available Exams</a>
            </li>

            <li style="margin-bottom: 12px;">
                <a href="exam_taken.php" style="display: block; color: #f1f5f9; text-decoration: none; padding: 10px; border-radius: 6px;"
                   onmouseover="this.style.background='#334155'" onmouseout="this.style.background='transparent'">📜 My Results</a>
            </li>
            </li>
        </ul>
    </div>

    <!-- Bottom Section -->
    <div style="padding: 20px;">
        <a href="logout.php" style="text-decoration: none;">
            <button style="
                width: 100%;
                padding: 10px;
                background-color: #ef4444;
                border: none;
                color: white;
                border-radius: 6px;
                font-weight: bold;
                cursor: pointer;
            " onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'">
                🚪 Logout
            </button>
        </a>
    </div>
</div>
