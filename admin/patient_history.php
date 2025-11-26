<?php 
// patient_history.php - vertical card layout clean implementation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/functions.php';
require_once '../config/admin_access.php';

if (!isset($_SESSION['loggedin']) || !isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
    header('Location: ../index.php');
    exit();
}

// Check if this is a doctor or nurse trying to access authorized page
if (isset($_SESSION['role']) && $_SESSION['role'] === 'doctor') {
    require_doctor_allowed_page();
} elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'nurse') {
    require_nurse_allowed_page();
}

require_once '../models/Patient.php';

// Use Patient model to fetch patient list
$patients = Patient::getAll();

// For compatibility with the rest of the page we will still provide $fk_col and a prepared statement
require_once '../config/database.php';
$fk_col = get_history_fk_column();
// prepare lastHistoryStmt when patient_history_records exists
$lastHistoryStmt = null;
$tableExists = false;
$check = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ? AND table_name = 'patient_history_records'");
if ($check) {
  mysqli_stmt_bind_param($check, 's', $dbname);
  mysqli_stmt_execute($check);
  $cres = mysqli_stmt_get_result($check);
  $row = mysqli_fetch_assoc($cres);
  $tableExists = !empty($row['c']);
  mysqli_stmt_close($check);
}
if ($tableExists) {
  $lastHistoryStmt = mysqli_prepare($conn, "SELECT * FROM patient_history_records WHERE {$fk_col} = ? ORDER BY created_at DESC LIMIT 1");
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Patient History</title>
  <?php require_once '../includes/header.php'; outputHeader('Patient History'); ?>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap');
  </style>
</head>
<body>
  <?php require '../includes/tailwind_nav.php'; ?> 

  <div class="max-w-6xl mx-auto px-4">
    <div class="bg-white rounded-lg shadow-sm border border-slate-100 p-6 mb-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-2xl md:text-3xl font-semibold text-slate-800">Patient History</h1>
          <p class="text-sm text-slate-500 mt-1">Overview of registered patients and quick access to their records.</p>
        </div>

        <div class="flex items-center gap-3">
          <div class="hidden md:block text-sm text-slate-600">Total patients: <span class="font-semibold text-slate-800"><?= count($patients) ?></span></div>
          <a href="#" class="inline-flex items-center gap-2 bg-slate-50 text-slate-700 px-3 py-2 rounded-md border border-slate-200 text-sm hover:bg-slate-100">
            <i class="fas fa-file-export"></i>
            Export
          </a>
        </div>
      </div>

      <div class="mt-4">
        <input id="patientSearch" type="search" placeholder="Search patients by name, email, phone, or ID" class="w-full md:w-2/3 lg:w-1/2 px-4 py-2 rounded-md border border-slate-200 text-sm text-slate-600 focus:outline-none focus:ring-2 focus:ring-sky-100" />
      </div>
    </div>
  </div>

  <?php if (empty($patients)): ?>
    <div style="text-align:center;padding:24px;color:#666">No patients found.</div>
  <?php else: ?>
    <div class="grid grid-cols-3 md:grid-cols-2 sm:grid-cols-1 gap-8 max-w-6xl mx-auto px-4" id="patientGrid">
      <?php foreach ($patients as $pObj): ?>
        <?php
          $uid = (int)$pObj->getId();
          $last = null;
          if ($lastHistoryStmt) {
            mysqli_stmt_bind_param($lastHistoryStmt, 'i', $uid);
            mysqli_stmt_execute($lastHistoryStmt);
            $lhres = mysqli_stmt_get_result($lastHistoryStmt);
            $last = mysqli_fetch_assoc($lhres) ?: null;
          }

          $fullNameRaw = trim(($pObj->getFullName() ?? ''));
          $search_name = htmlspecialchars($fullNameRaw);
          $search_email = htmlspecialchars($pObj->getEmail() ?? '');
          $search_phone = '';
          $studentNum = method_exists($pObj, 'getStudentNumber') ? $pObj->getStudentNumber() : '';
          $dept = '';
          $search_student = htmlspecialchars($studentNum ?: '');
        ?>
        <div class="patient-card bg-white rounded-xl p-6 shadow-sm hover:shadow-md transition-transform transform hover:-translate-y-1 flex flex-col gap-4 min-h-[260px]" data-name="<?= $search_name ?>" data-email="<?= $search_email ?>" data-phone="<?= $search_phone ?>" data-student="<?= $search_student ?>">
          <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-lg bg-teal-600 text-white flex items-center justify-center font-semibold text-xl">
              <?= strtoupper(substr(trim(($pObj->getFullName() ?? '')),0,1)) ?>
            </div>
            <div class="flex-1">
              <div class="text-lg font-semibold text-slate-800 leading-tight"><?= htmlspecialchars(trim(($pObj->getFullName() ?? ''))) ?></div>
              <div class="text-sm text-slate-500 mt-1"><?= htmlspecialchars($pObj->getEmail() ?? '') ?></div>
              <div class="mt-2 flex flex-wrap gap-2">
                <?php if (!empty($studentNum)): ?>
                  <span class="text-xs bg-slate-50 border border-slate-100 text-slate-600 px-2 py-1 rounded">ID: <?= htmlspecialchars($studentNum) ?></span>
                <?php endif; ?>
                <?php if (!empty($dept)): ?>
                  <span class="text-xs bg-slate-50 border border-slate-100 text-slate-600 px-2 py-1 rounded">Dept: <?= htmlspecialchars($dept) ?></span>
                <?php endif; ?>
                <?php if (!empty($last) && isset($last['created_at'])): ?>
                  <span class="text-xs bg-slate-50 border border-slate-100 text-slate-600 px-2 py-1 rounded">Last visit: <?= date('M j, Y', strtotime($last['created_at'])) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="mt-2 text-sm text-slate-600">
            <!-- reserved for short clinical preview -->
          </div>

          <div class="mt-auto flex justify-end items-center gap-3">
            <a class="inline-flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-md text-sm text-slate-700 hover:bg-slate-50" href="patient_history_details.php?user_id=<?= $uid ?>">
              <i class="fas fa-file-medical-alt"></i>
              View Record
            </a>

            <!-- Download PDF now points to export_record_pdf.php and opens in a new tab -->
            <a
              class="inline-flex items-center gap-2 px-3 py-2 bg-teal-600 text-white rounded-md text-sm hover:bg-teal-500"
              href="export_record_pdf.php?user_id=<?= $uid ?>"
              title="Download PDF"
              target="_blank"
              rel="noopener"
            >
              <i class="fas fa-download"></i>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <script>
    (function(){
      const input = document.getElementById('patientSearch');
      const grid = document.getElementById('patientGrid');
      if (!input || !grid) return;
      const cards = Array.from(grid.querySelectorAll('.patient-card'));
      let t = null;
      function normalize(s){ return (s||'').toString().toLowerCase(); }
      function filter(q){
        q = normalize(q);
        cards.forEach(card => {
          const name = normalize(card.dataset.name);
          const email = normalize(card.dataset.email);
          const phone = normalize(card.dataset.phone);
          const student = normalize(card.dataset.student);
          const match = [name,email,phone,student].some(v => v.includes(q));
          card.style.display = (q === '' || match) ? '' : 'none';
        });
      }
      input.addEventListener('input', function(){ clearTimeout(t); t = setTimeout(()=> filter(this.value), 180); });
      input.addEventListener('keydown', function(e){ if (e.key === 'Escape'){ this.value=''; filter(''); } });
    })();
  </script>

</body>
</html>
