<?php
include "../includes/sessions.php";
include "../includes/db.php";


// Get emp_id from cookie (only for display, not for filtering)
$emp_id = $_COOKIE['EIMS_emp_Id'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Scores</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800">
  <div class="max-w-7xl mx-auto px-6 py-8">
    <!-- Header -->
    <header class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Admin Dashboard</h1>
            <p class="text-sm text-slate-500">Manage exams and questions</p>
        </div>
        <nav class="flex items-center gap-2">
            <a href="adminindex.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">Dashboard</a>
            <a href="patches.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">Patches</a>
            <a href="certificates.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">Certificates</a>
            <a href="employee_progress.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">Employee Progress</a>
            <a href="employee_scores.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">Scores</a>
            <a href="../logout.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg">Logout</a>
        </nav>
    </header>

    <!-- Main Content -->
    <section class="bg-white rounded-2xl shadow overflow-hidden">
      <!-- Section Header with Controls -->
      <div class="px-6 py-4 border-b">
          <div class="flex items-center justify-between mb-4">
              <div>
                  <h2 class="text-xl font-semibold text-slate-800">🧾 All Exam Results</h2>
                  <p class="text-sm text-slate-500 mt-1">Complete list of employee exam submissions</p>
              </div>
              <div class="flex items-center gap-2">
                  <input type="text" id="searchInput" placeholder="Search by title, employee..." class="px-4 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                  <select id="statusFilter" class="px-4 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                      <option value="">All Status</option>
                      <option value="Passed">Passed</option>
                      <option value="Failed">Failed</option>
                  </select>
                  <button id="resetFilter" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg">Reset</button>
              </div>
          </div>
          <div class="text-xs text-slate-500">
              Showing <span id="recordCount">0</span> results
          </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table id="examTable" class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" data-sort="title">Exam Title</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" data-sort="emp_id">Employee ID</th>
              <th class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" data-sort="score">Score</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" data-sort="date">Date Taken</th>
              <th class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" data-sort="status">Status</th>
              <th class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">Action</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-slate-100" id="tableBody">

                <?php
                $sql = "
                    SELECT 
                        e.Exam_Title, 
                        r.Emp_ID,
                        r.Score, 
                        r.TotalQuestions,
                        r.Date_Completed,
                        r.Exam_ID,
                        r.Result_ID
                    FROM dbo.Results AS r
                    INNER JOIN dbo.Exams AS e ON e.Exam_ID = r.Exam_ID
                    ORDER BY r.Date_Completed DESC
                ";

                $stmt = sqlsrv_query($con3, $sql);
                $rows = [];

                if ($stmt === false) {
                    echo "<tr><td colspan='6' class='px-6 py-4 text-center text-red-600'>Error loading exam results.</td></tr>";
                } else {
                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        $examTitle = htmlspecialchars($row['Exam_Title']);
                        $Emp_ID = htmlspecialchars($row['Emp_ID']);
                        $score = (int)$row['Score'];
                        $total = (int)$row['TotalQuestions'];

                        $dateTaken = ($row['Date_Completed'] instanceof DateTime)
                            ? $row['Date_Completed']->format('Y-m-d H:i')
                            : 'N/A';

                        $percentage = $total > 0 ? ($score / $total) * 100 : 0;
                        $status = $percentage >= 75 ? "Passed" : "Failed";
                        $statusColor = $status === "Passed" ? "bg-emerald-100 text-emerald-700" : "bg-red-100 text-red-700";
                        $scorePercent = round($percentage, 1);

                        $viewUrl = "view_result.php?" . http_build_query([
                            'exam_id' => $row['Exam_ID'],
                            'result_id' => $row['Result_ID'],
                            'date_taken' => $dateTaken
                        ]);

                        $rows[] = [
                            'title' => $examTitle,
                            'emp_id' => $Emp_ID,
                            'score' => $score,
                            'total' => $total,
                            'percent' => $scorePercent,
                            'date' => $dateTaken,
                            'status' => $status,
                            'statusColor' => $statusColor,
                            'viewUrl' => $viewUrl
                        ];
                    }
                    sqlsrv_free_stmt($stmt);
                }
                ?>

            </tbody>
            <tfoot class="bg-slate-50 border-t">
                <tr>
                    <td colspan="6" class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="text-xs text-slate-500">
                                Page <span id="currentPage">1</span> of <span id="totalPages">1</span> | <span id="totalRecords"><?php echo count($rows); ?></span> total records
                            </div>
                            <div class="flex items-center gap-1" id="pagination"></div>
                        </div>
                    </td>
                </tr>
            </tfoot>
            </table>
          </div>
    </section>
  </div>

<script>
// Data embedded in JavaScript for client-side filtering and sorting
const allData = <?php echo json_encode($rows); ?>;
let currentData = [...allData];
let currentPage = 1;
const pageSize = 10;
let sortColumn = 'date';
let sortOrder = 'desc';

function renderTable(){
    const tbody = document.getElementById('tableBody');
    tbody.innerHTML = '';
    
    const start = (currentPage - 1) * pageSize;
    const end = start + pageSize;
    const pageData = currentData.slice(start, end);
    
    if(pageData.length === 0){
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-6 text-center text-slate-500">No results found</td></tr>';
        return;
    }
    
    pageData.forEach(row => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50';
        tr.innerHTML = `
            <td class="px-6 py-4 text-sm font-medium text-slate-800">${row.title}</td>
            <td class="px-6 py-4 text-sm text-slate-600">${row.emp_id}</td>
            <td class="px-6 py-4 text-sm text-center">
              <span class="font-medium text-slate-700">${row.score}/${row.total}</span><br/>
              <span class="text-xs text-slate-500">${row.percent}%</span>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600">${row.date}</td>
            <td class="px-6 py-4 text-center">
              <span class="inline-block px-3 py-1 text-xs font-medium rounded-full ${row.statusColor}">${row.status}</span>
            </td>
            <td class="px-6 py-4 text-center">
              <a href="${row.viewUrl}" class="inline-flex items-center px-3 py-1 text-xs font-medium rounded bg-indigo-100 text-indigo-700 hover:bg-indigo-200">View</a>
            </td>
        `;
        tbody.appendChild(tr);
    });
    
    updatePagination();
    document.getElementById('recordCount').textContent = currentData.length;
}

function updatePagination(){
    const totalPages = Math.ceil(currentData.length / pageSize);
    document.getElementById('totalPages').textContent = totalPages;
    document.getElementById('currentPage').textContent = currentPage;
    
    const pagination = document.getElementById('pagination');
    pagination.innerHTML = '';
    
    if(totalPages <= 1) return;
    
    const prevBtn = document.createElement('button');
    prevBtn.className = 'px-2 py-1 text-xs border border-slate-300 rounded hover:bg-slate-100 disabled:opacity-50';
    prevBtn.disabled = currentPage === 1;
    prevBtn.textContent = '← Prev';
    prevBtn.addEventListener('click', ()=>{ if(currentPage > 1) { currentPage--; renderTable(); } });
    pagination.appendChild(prevBtn);
    
    for(let i = Math.max(1, currentPage-2); i <= Math.min(totalPages, currentPage+2); i++){
        const btn = document.createElement('button');
        btn.className = 'px-2 py-1 text-xs border rounded ' + (i === currentPage ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-300 hover:bg-slate-100');
        btn.textContent = i;
        btn.addEventListener('click', ()=>{ currentPage = i; renderTable(); });
        pagination.appendChild(btn);
    }
    
    const nextBtn = document.createElement('button');
    nextBtn.className = 'px-2 py-1 text-xs border border-slate-300 rounded hover:bg-slate-100 disabled:opacity-50';
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.textContent = 'Next →';
    nextBtn.addEventListener('click', ()=>{ if(currentPage < totalPages) { currentPage++; renderTable(); } });
    pagination.appendChild(nextBtn);
}

function filterAndSort(){
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    
    currentData = allData.filter(row => {
        const matchSearch = !searchTerm || 
            row.title.toLowerCase().includes(searchTerm) || 
            row.emp_id.toLowerCase().includes(searchTerm);
        const matchStatus = !statusFilter || row.status === statusFilter;
        return matchSearch && matchStatus;
    });
    
    // Sort
    currentData.sort((a, b) => {
        let aVal = a[sortColumn];
        let bVal = b[sortColumn];
        if(sortColumn === 'score'){
            aVal = a.percent;
            bVal = b.percent;
        }
        
        if(typeof aVal === 'string'){
            aVal = aVal.toLowerCase();
            bVal = bVal.toLowerCase();
        }
        
        const cmp = aVal < bVal ? -1 : aVal > bVal ? 1 : 0;
        return sortOrder === 'asc' ? cmp : -cmp;
    });
    
    currentPage = 1;
    renderTable();
}

// Event listeners
document.getElementById('searchInput').addEventListener('input', filterAndSort);
document.getElementById('statusFilter').addEventListener('change', filterAndSort);

document.getElementById('resetFilter').addEventListener('click', ()=>{
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = '';
    currentPage = 1;
    filterAndSort();
});

document.querySelectorAll('th[data-sort]').forEach(th => {
    th.addEventListener('click', ()=>{
        const col = th.dataset.sort;
        if(sortColumn === col){
            sortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
        } else {
            sortColumn = col;
            sortOrder = 'desc';
        }
        filterAndSort();
    });
});

// Initial render
renderTable();
</script>

</body>
</html>
