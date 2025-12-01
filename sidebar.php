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

<!-- Sidebar Layout (Tailwind-based) -->
<?php
// Determine current page for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$active_class = 'bg-indigo-600 text-white';
$inactive_class = 'text-slate-200 hover:bg-slate-700 hover:text-white';
?>

<aside class="w-64 bg-gradient-to-b from-slate-900 to-slate-800 text-slate-100 h-screen sticky top-0 flex flex-col justify-between p-6 shadow-lg hidden md:flex">
  <!-- Top Section -->
  <div>
    <!-- Branding / User Info -->
    <div class="mb-8">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-12 h-12 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold text-sm">TE</div>
        <div>
          <div class="text-xs text-indigo-200">Welcome</div>
          <div class="font-semibold text-slate-100 text-sm leading-tight"><?php echo htmlspecialchars($fullname); ?></div>
        </div>
      </div>
    </div>

    <!-- Navigation Links -->
    <nav class="space-y-2">
      <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors <?php echo $current_page === 'index.php' ? $active_class : $inactive_class; ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>
        <span>Dashboard</span>
      </a>

      <a href="exam_list.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors <?php echo $current_page === 'exam_list.php' ? $active_class : $inactive_class; ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <span>Available Exams</span>
      </a>

      <a href="exam_taken.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors <?php echo $current_page === 'exam_taken.php' ? $active_class : $inactive_class; ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7 12a5 5 0 1110 0A5 5 0 017 12z"/></svg>
        <span>My Results</span>
      </a>

      <a href="patches.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors <?php echo $current_page === 'patches.php' ? $active_class : $inactive_class; ?>">
        <!-- Wrench / Tools icon for Patches -->
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.7 9.3a6 6 0 11-8.4 8.4L3 21l3.3-3.3a6 6 0 0111.4-8.4z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 3l-6 6" />
        </svg>
        <span>Patches</span>
      </a>

      <a href="certificates.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors <?php echo $current_page === 'certificates.php' ? $active_class : $inactive_class; ?>">
        <!-- Certificate / Badge icon -->
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2l3 6 6 .5-4.5 3 1 6L12 15 5.5 19.5l1-6L2 8.5 8 8l4-6z" />
        </svg>
        <span>Certificates</span>
      </a>
      
    </nav>
  </div>

  <!-- Bottom Section -->
  <div>
    <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition-colors">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      <span>Logout</span>
    </a>
  </div>
</aside>

<!-- Mobile Header (hidden on md+) -->
<div class="md:hidden bg-gradient-to-r from-slate-900 to-slate-800 text-slate-100 flex items-center justify-between px-4 py-3">
  <button id="sidebarToggle" aria-label="Toggle sidebar" class="p-2 rounded-md bg-slate-700 hover:bg-slate-600">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 5a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V5zM3 10a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1v-2zM3 15a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1v-2z"/></svg>
  </button>
  <div class="text-sm font-semibold">TEIPI EXAMS</div>
  <a href="logout.php" class="text-sm text-red-400 hover:text-red-300">Logout</a>
</div>

<!-- Mobile Slide-out Sidebar -->
<aside id="mobileSidebar" class="fixed inset-y-0 left-0 w-64 bg-gradient-to-b from-slate-900 to-slate-800 text-slate-100 transform -translate-x-full transition-transform duration-200 md:hidden z-50 flex flex-col justify-between p-6">
  <div>
    <div class="mb-8">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-12 h-12 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold text-sm">TE</div>
        <div>
          <div class="text-xs text-indigo-200">Welcome</div>
          <div class="font-semibold text-slate-100 text-sm leading-tight"><?php echo htmlspecialchars($fullname); ?></div>
        </div>
      </div>
    </div>

    <nav class="space-y-2">
      <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors <?php echo $current_page === 'index.php' ? $active_class : $inactive_class; ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>
        <span>Dashboard</span>
      </a>

      <a href="exam_list.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors <?php echo $current_page === 'exam_list.php' ? $active_class : $inactive_class; ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <span>Available Exams</span>
      </a>

        <a href="exam_taken.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors <?php echo $current_page === 'exam_taken.php' ? $active_class : $inactive_class; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7 12a5 5 0 1110 0A5 5 0 017 12z"/></svg>
          <span>My Results</span>
        </a>

        <a href="patches.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors <?php echo $current_page === 'patches.php' ? $active_class : $inactive_class; ?>">
          <!-- Wrench / Tools icon for Patches (mobile) -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.7 9.3a6 6 0 11-8.4 8.4L3 21l3.3-3.3a6 6 0 0111.4-8.4z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 3l-6 6" />
          </svg>
          <span>Patches</span>
        </a>

        <a href="certificates.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors <?php echo $current_page === 'certificates.php' ? $active_class : $inactive_class; ?>">
          <!-- Certificate / Badge icon (mobile) -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2l3 6 6 .5-4.5 3 1 6L12 15 5.5 19.5l1-6L2 8.5 8 8l4-6z" />
          </svg>
          <span>Certificates</span>
        </a>

    </nav>
  </div>

  <div>
    <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition-colors">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      <span>Logout</span>
    </a>
  </div>
</aside>

<!-- Mobile Sidebar Toggle Script -->
<script>
  (function(){
    const btn = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('mobileSidebar');
    if(!btn || !sidebar) return;
    
    btn.addEventListener('click', ()=>{
      const hidden = sidebar.classList.contains('-translate-x-full');
      if(hidden) {
        sidebar.classList.remove('-translate-x-full');
      } else {
        sidebar.classList.add('-translate-x-full');
      }
    });
    
    // Close when clicking outside
    document.addEventListener('click', (e)=>{
      const isOpen = !sidebar.classList.contains('-translate-x-full');
      if(isOpen && !sidebar.contains(e.target) && !btn.contains(e.target)){
        sidebar.classList.add('-translate-x-full');
      }
    });

    // Close on escape key
    document.addEventListener('keydown', (e)=>{
      if(e.key === 'Escape'){
        sidebar.classList.add('-translate-x-full');
      }
    });
  })();
</script>
