<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  .admin-navbar {
    background-color: #2c3e50;
    padding: 15px 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
  }
  .admin-navbar .brand {
    color: white;
    font-weight: bold;
    font-size: 20px;
    text-decoration: none;
    margin-right: 30px;
  }
  .admin-navbar .nav-links {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    align-items: center;
    flex-grow: 1;
  }
  .admin-navbar a {
    color: rgba(255,255,255,0.9);
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 4px;
    transition: background-color 0.2s;
    white-space: nowrap;
  }
  .admin-navbar a:hover {
    background-color: rgba(255,255,255,0.1);
    color: white;
  }
  .admin-navbar .logout {
    background-color: #e74c3c;
    color: white;
    padding: 8px 20px;
    border-radius: 4px;
    margin-left: 15px;
  }
  .admin-navbar .logout:hover {
    background-color: #c0392b;
  }
  .admin-navbar .separator {
    color: rgba(255,255,255,0.3);
    margin: 0 5px;
  }
</style>

<div class="admin-navbar">
  <a href="adminindex.php" class="brand">🔧 Admin Panel</a>
  <div class="nav-links">
    <a href="adminindex.php">Dashboard</a>
    <span class="separator">|</span>
    <a href="add_exam.php">Add Exam</a>
    <span class="separator">|</span>
    <a href="add_question.php">Add Question</a>
    <span class="separator">|</span>
    <a href="edit_exam.php">Edit Exam</a>
    <span class="separator">|</span>
    <a href="patches.php">View Patches</a>
    <span class="separator">|</span>
    <a href="manage_patches.php">Manage Patches</a>
    <span class="separator">|</span>
    <a href="certificates.php">View Certificates</a>
    <span class="separator">|</span>
    <a href="manage_certificates.php">Manage Certificates</a>
    <span class="separator">|</span>
    <a href="employee_scores.php">Scores</a>
    <span class="separator">|</span>
    <a href="employee_progress.php">Progress</a>
    <span class="separator">|</span>
    <a href="recalculate_expirations.php">Recalculate</a>
  </div>
  <a href="../logout.php" class="logout">Logout</a>
</div>