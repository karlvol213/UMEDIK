<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// AJAX handler for checking available time slots
if (isset($_GET['check_time_slots'])) {
    header('Content-Type: application/json');
    $selected_date = $_GET['date'] ?? null;
    $selected_service = $_GET['service'] ?? null;
    
    if (!$selected_date) {
        echo json_encode(['error' => 'No date selected']);
        exit;
    }
    
    // Validate date is today or in the future
    $selected_datetime = new DateTime($selected_date);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    if ($selected_datetime < $today) {
        echo json_encode(['error' => 'Selected date is in the past']);
        exit;
    }
    
    // Define all available time slots
    $time_slots = [
        '09:00:00' => '9:00 AM - 9:30 AM',
        '09:30:00' => '9:30 AM - 10:00 AM',
        '10:00:00' => '10:00 AM - 10:30 AM',
        '10:30:00' => '10:30 AM - 11:00 AM',
        '11:00:00' => '11:00 AM - 11:30 AM',
        '11:30:00' => '11:30 AM - 12:00 PM',
        '12:00:00' => '12:00 PM - 12:30 PM',
        '12:30:00' => '12:30 PM - 1:00 PM',
        '13:00:00' => '1:00 PM - 1:30 PM',
        '13:30:00' => '1:30 PM - 2:00 PM',
        '14:00:00' => '2:00 PM - 2:30 PM',
        '14:30:00' => '2:30 PM - 3:00 PM',
        '15:00:00' => '3:00 PM - 3:30 PM',
        '15:30:00' => '3:30 PM - 4:00 PM',
        '16:00:00' => '4:00 PM - 4:30 PM',
        '16:30:00' => '4:30 PM - 5:00 PM'
    ];
    
    // Afternoon-only slots (for non-dental services)
    $afternoon_only_slots = ['15:00:00', '15:30:00', '16:00:00', '16:30:00'];
    
    // Get booked appointments for the selected date
    $booked_sql = "SELECT DISTINCT appointment_time FROM appointments 
                   WHERE appointment_date = ? AND status <> 'cancelled'";
    $booked_stmt = $conn->prepare($booked_sql);
    $booked_stmt->bind_param("s", $selected_date);
    $booked_stmt->execute();
    $booked_result = $booked_stmt->get_result();
    
    $booked_times = [];
    while ($row = $booked_result->fetch_assoc()) {
        $booked_times[] = $row['appointment_time'];
    }
    
    $available_slots = [];
    // Get current time from the browser via hidden input (to match user's local timezone)
    $client_time = $_GET['client_time'] ?? null;
    if ($client_time) {
        // Use client's time (passed from JavaScript)
        $current_time = DateTime::createFromFormat('Y-m-d H:i:s', $client_time);
    } else {
        // Fallback to server time
        $current_time = new DateTime();
    }
    $today_date = $current_time->format('Y-m-d');
    $is_today = ($selected_date === $today_date);
    
    // Determine if dental service is selected
    $is_dental = false;
    if ($selected_service) {
        $service_id = (int)$selected_service;
        $service_sql = "SELECT category FROM services WHERE id = ? LIMIT 1";
        $service_stmt = $conn->prepare($service_sql);
        $service_stmt->bind_param("i", $service_id);
        $service_stmt->execute();
        $service_result = $service_stmt->get_result();
        if ($service_row = $service_result->fetch_assoc()) {
            $is_dental = strpos($service_row['category'], 'Dental') !== false;
        }
    }
    
    foreach ($time_slots as $time => $label) {
        // Hide afternoon-only slots for dental services
        if ($is_dental && in_array($time, $afternoon_only_slots)) {
            continue;
        }
        
        $is_booked = in_array($time, $booked_times);
        $is_past = false;
        
        // Check if time is in the past (if selected date is today)
        if ($is_today) {
            try {
                $slot_datetime = new DateTime($selected_date . ' ' . $time);
                // Mark as past if slot end time is before or at current time
                // Each slot is 30 minutes, so add 30 minutes to start time to get end time
                $slot_end_time = clone $slot_datetime;
                $slot_end_time->modify('+30 minutes');
                // A slot is past if it has already ended
                $is_past = ($slot_end_time <= $current_time);
            } catch (Exception $e) {
                $is_past = false;
            }
        }
        
        $available_slots[] = [
            'time' => $time,
            'label' => $label,
            'booked' => $is_booked,
            'past' => $is_past,
            'disabled' => ($is_booked || $is_past),
            'disabled_reason' => $is_booked ? 'booked' : ($is_past ? 'past' : '')
        ];
    }
    
    echo json_encode(['slots' => $available_slots]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $date = trim($_POST['schedule_date'] ?? '');
    $time = trim($_POST['schedule_time'] ?? '09:00:00');
    

    $appointment_datetime = new DateTime($date . ' ' . $time);
  // Validate appointment datetime parse and that it's in the future
  $now = new DateTime();
  if (!($appointment_datetime instanceof DateTime)) {
    $_SESSION['error_message'] = "Invalid date/time provided.";
    header("Location: schedule.php");
    exit();
  }
  if ($appointment_datetime <= $now) {
    $_SESSION['error_message'] = "Please select a future date and time for your appointment.";
    header("Location: schedule.php");
    exit();
  }
    
  if (!isset($_POST['service'])) {
    $_SESSION['error_message'] = "Please select a service.";
    header("Location: schedule.php");
    exit();
  }

  // Time is fixed to 09:00, no time validation needed

  $selected_services = [];
  $comment = trim($_POST['comment']);

  // Handle selected service (expect numeric service id)
  $service_post = $_POST['service'];
  $service_id = (int)$service_post;
  if ($service_id <= 0) {
      $_SESSION['error_message'] = "Please select a valid service.";
      header("Location: schedule.php");
      exit();
  }

  // Check if the selected service is the 'Other' row in DB (by name)
  $check_sql = "SELECT name FROM services WHERE id = ? LIMIT 1";
  $check_stmt = $conn->prepare($check_sql);
  if ($check_stmt) {
      $check_stmt->bind_param("i", $service_id);
      $check_stmt->execute();
      $res = $check_stmt->get_result();
      $row = $res->fetch_assoc();
      $selected_name = $row['name'] ?? '';
  } else {
      $selected_name = '';
  }

  if (strtolower(trim($selected_name)) === 'other' || strtolower(trim($selected_name)) === 'others/more') {
   
    if ($comment === '') {
      $_SESSION['error_message'] = "Please describe your service need in the Additional Information box.";
      header("Location: schedule.php");
      exit();
    }
   
  }

  $selected_services = [$service_id];

  
  $dup_sql = "SELECT a.id FROM appointments a\n                JOIN appointment_services as_link ON a.id = as_link.appointment_id\n                WHERE a.user_id = ? AND a.appointment_date = ? AND as_link.service_id = ? AND a.status <> 'cancelled' LIMIT 1";
  $dup_stmt = $conn->prepare($dup_sql);
  if ($dup_stmt) {
      $dup_stmt->bind_param("isi", $user_id, $date, $service_id);
      $dup_stmt->execute();
      $dup_res = $dup_stmt->get_result();
      if ($dup_res && $dup_res->num_rows > 0) {
          $_SESSION['error_message'] = "You already have an appointment on that date for the selected service. Please choose another date or service.";
          header("Location: schedule.php");
          exit();
      }
  }

    try {

        $conn->begin_transaction();

       
        $sql = "INSERT INTO appointments (user_id, appointment_date, appointment_time, comment, status, created_at) 
                VALUES (?, ?, ?, ?, 'requested', NOW())";
                
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("isss", $user_id, $date, $time, $comment);
        
        if ($stmt->execute()) {
            $appointment_id = $conn->insert_id;
            
            
            $service_sql = "INSERT INTO appointment_services (appointment_id, service_id) VALUES (?, ?)";
            $service_stmt = $conn->prepare($service_sql);
            
            if (!$service_stmt) {
                throw new Exception("Prepare service statement failed: " . $conn->error);
            }
            
           
            foreach ($selected_services as $service_id) {
                $service_id = (int)$service_id;
                $service_stmt->bind_param("ii", $appointment_id, $service_id);
                if (!$service_stmt->execute()) {
                    throw new Exception("Error adding service: " . $service_stmt->error);
                }
            }
            
            
            $conn->commit();
            
        
            log_action($user_id, "Appointment Created", "New appointment scheduled for $date");
            $_SESSION['success_message'] = "Appointment scheduled successfully!";
            header("Location: appointments.php");
            exit();
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        error_log("Error in schedule.php: " . $e->getMessage());
        $_SESSION['error_message'] = "Error scheduling appointment. Please try again. Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Appointment - UMak Medical Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <!-- nav.css removed: using Tailwind navbar include -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(to bottom right, #ffffff, #cce0ff);
            background-attachment: fixed;
            background-repeat: no-repeat;
            min-height: 100vh;
            padding-top: 60px;
        }

  .top-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background-color: #003366;
    padding: 12px 24px;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
  }
        


 
  body {
    font-family: 'Inter', sans-serif;
    margin: 0;
    padding: 0;
    background: linear-gradient(to bottom right, #ffffff, #cce0ff);
    background-attachment: fixed;
    background-repeat: no-repeat;
    min-height: 100vh;
    color: #333;
  }
  .container {
    max-width: 1000px;
    margin: 100px auto 30px;
    background: #fff;
    padding: 40px;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
  }
  h1 {
    margin: 0 0 30px 0;
    color: #003366;
    font-size: 2.2em;
    text-align: center;
    position: relative;
    padding-bottom: 15px;
  }
  h1:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 4px;
    background: linear-gradient(to right, #003366, #4a90e2);
    border-radius: 2px;
  }
  .form-group {
    margin-bottom: 25px;
  }
  .schedule-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
  }
  label {
    font-weight: 600;
    display: block;
    margin-bottom: 8px;
    color: #2c3e50;
    font-size: 1.1em;
  }
  input[type="text"], 
  input[type="date"],
  input[type="time"], 
  textarea {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e1e8f0;
    border-radius: 10px;
    box-sizing: border-box;
    font-size: 1em;
    transition: all 0.3s ease;
  }
  input[type="text"]:focus, 
  input[type="date"]:focus,
  input[type="time"]:focus, 
  textarea:focus {
    border-color: #4a90e2;
    box-shadow: 0 0 0 3px rgba(74,144,226,0.1);
    outline: none;
  }
  textarea {
    min-height: 100px;
    resize: vertical;
  }
  .category {
    margin-bottom: 30px;
    padding: 20px;
    border-radius: 15px;
    background: #f8fafd;
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
    border: 1px solid #e1e8f0;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  .category:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
  }
  .category h3 {
    margin: 0 0 20px 0;
    color: #003366;
    font-size: 1.3em;
    padding-bottom: 12px;
    border-bottom: 2px solid #e1e8f0;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .checkbox-group {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 12px;
  }
  .checkbox-group label {
    display: flex;
    align-items: center;
    padding: 10px 15px;
    margin: 0;
    font-weight: normal;
    cursor: pointer;
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #e1e8f0;
    transition: all 0.2s ease;
  }
  .checkbox-group label:hover {
    background: #f0f4f8;
    border-color: #4a90e2;
    transform: translateY(-1px);
  }
  .checkbox-group input[type="checkbox"] {
    margin-right: 10px;
    width: 18px;
    height: 18px;
    cursor: pointer;
  }
  .alert {
    padding: 15px 20px;
    margin-bottom: 25px;
    border: none;
    border-radius: 10px;
    font-weight: 500;
  }
  .success {
    color: #0a5c36;
    background-color: #e3f6ec;
  }
  .error {
    color: #a42834;
    background-color: #fde8ea;
  }
  .buttons {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 30px;
  }
  .btn {
    padding: 12px 25px;
    border: none;
    border-radius: 8px;
    font-size: 1em;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
  }
  .btn-cancel {
    background: #e1e8f0;
    color: #2c3e50;
    text-decoration: none;
  }
  .btn-cancel:hover {
    background: #d1dae6;
  }
  .btn-save {
    background: #003366;
    color: white;
  }
  .btn-save:hover {
    background: #004480;
    transform: translateY(-2px);
  }

 
</style>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen">
  <?php include 'includes/tailwind_nav.php'; ?>
    <div class="container">
    <h1>Schedule Appointment</h1>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert success">
            <?php 
                echo $_SESSION['success_message']; 
                unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert error">
            <?php 
                echo $_SESSION['error_message']; 
                unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>

    <form method="post" action="schedule.php">
      <div class="schedule-info">
        <div class="form-group">
          <label for="schedule_date">Select Date:</label>
          <input type="date" name="schedule_date" id="schedule_date" required>
        </div>
        <div class="form-group">
          <label for="schedule_time">Select Time Slot:</label>
          <select name="schedule_time" id="schedule_time" required style="width: 100%; padding: 12px 15px; border: 2px solid #e1e8f0; border-radius: 10px; box-sizing: border-box; font-size: 1em; transition: all 0.3s ease;">
            <option value="">-- Select a time slot --</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Select One Service / Symptom:</label>
        <p class="text-sm text-gray-600 mb-2">Please choose only one service that best describes your need.</p>
        <?php
        // Fetch services from database
        $sql = "SELECT * FROM services ORDER BY category, name";
        $result = $conn->query($sql);
        
        // Group services by category
        $services_by_category = [];
        while ($service = $result->fetch_assoc()) {
            $services_by_category[$service['category']][] = $service;
        }
        
        // Display services grouped by category
        $category_icons = [
            'Dental Consultation' => '🦷',
            'Medical Consultation' => '🏥',
            'Check-up Consultation' => '🩺'
        ];
        
    foreach ($services_by_category as $category => $services) {
            echo '<div class="category">';
            echo '<h3>' . ($category_icons[$category] ?? '') . ' ' . htmlspecialchars($category) . '</h3>';
            echo '<div class="checkbox-group">';
      foreach ($services as $service) {
        $service_id = (int)$service['id'];
        if ($service_id > 0) {  // Only show valid services
          // Check if this is the Others/More option
          $is_other = (strtolower(trim($service['name'])) === 'other' || strtolower(trim($service['name'])) === 'others/more');
          echo '<label>';
          echo '<input type="radio" name="service" value="' . $service_id . '" required' . ($is_other ? ' data-other="1"' : '') . '> ' . 
             ($is_other ? 'Others/More' : htmlspecialchars($service['name'])) . '</label>';
      if ($is_other) {
            // mark with data-other; user will describe details in Additional Information below
          }
        }
      }
      echo '</div></div>';
    }
        ?>
        </div>
        <div id="other-helper" style="display:none; color:#b83232; margin-top:8px;">Please describe the service you need in the Additional Information box below.</div>
      </div>

          <div class="category">
        <h3>📝 Additional Information</h3>
        <div class="form-group" style="margin-bottom: 25px;">
          <label for="comment">Comments / Description (Max 700 characters):</label>
          <textarea 
            name="comment" 
            id="comment" 
            placeholder="Please describe the service you need (for Others/More) or provide additional details..."
            aria-describedby="other-helper"
            class="comment-box"
            maxlength="700"
          ></textarea>
          <div style="font-size: 0.9em; color: #666; margin-top: 6px;">
            <span id="char-count">0</span>/700 characters
          </div>
        </div>

        <div class="buttons">
          <a href="appointments.php" class="btn btn-cancel">Cancel</a>
          <button type="submit" class="btn btn-save">Schedule Appointment</button>
        </div>
      </div>
    </form>
  </div>
  <script>
    (function(){
      // Character counter for comment box
      var commentBox = document.getElementById('comment');
      var charCount = document.getElementById('char-count');
      
      if (commentBox && charCount) {
        commentBox.addEventListener('input', function() {
          charCount.textContent = this.value.length;
        });
        
        // Initialize on page load
        charCount.textContent = commentBox.value.length;
      }
      
      // Load available time slots based on selected date and service
      function loadTimeSlots() {
        var dateInput = document.getElementById('schedule_date');
        var timeSelect = document.getElementById('schedule_time');
        var serviceRadio = document.querySelector('input[name="service"]:checked');
        
        var selectedDate = dateInput.value;
        var selectedService = serviceRadio ? serviceRadio.value : null;
        
        if (!selectedDate) {
          // Reset time slots if no date selected
          resetTimeSlots();
          return;
        }
        
        // Get current time from client's browser and format it for server
        var now = new Date();
        var year = now.getFullYear();
        var month = String(now.getMonth() + 1).padStart(2, '0');
        var day = String(now.getDate()).padStart(2, '0');
        var hours = String(now.getHours()).padStart(2, '0');
        var minutes = String(now.getMinutes()).padStart(2, '0');
        var seconds = String(now.getSeconds()).padStart(2, '0');
        var clientTime = year + '-' + month + '-' + day + ' ' + hours + ':' + minutes + ':' + seconds;
        
        // Fetch available time slots from server with cache-busting parameter and client time
        var url = 'schedule.php?check_time_slots=1&date=' + encodeURIComponent(selectedDate) + 
                  '&client_time=' + encodeURIComponent(clientTime) + 
                  '&t=' + new Date().getTime();
        if (selectedService) {
          url += '&service=' + encodeURIComponent(selectedService);
        }
        
        fetch(url)
          .then(response => response.json())
          .then(data => {
            if (data.error) {
              console.error('Error loading time slots:', data.error);
              resetTimeSlots();
              return;
            }
            
            console.log('Loaded slots for date:', selectedDate, 'Client time:', clientTime, 'Slots:', data.slots);
            populateTimeSlots(data.slots);
          })
          .catch(error => {
            console.error('Error fetching time slots:', error);
            resetTimeSlots();
          });
      }
      
      function populateTimeSlots(slots) {
        var timeSelect = document.getElementById('schedule_time');
        
        // Clear ALL options first
        timeSelect.innerHTML = '<option value="">-- Select a time slot --</option>';
        
        // Group slots by period
        var morningSlots = slots.filter(s => s.time < '12:00:00');
        var afternoonSlots = slots.filter(s => s.time >= '12:00:00');
        
        // Add Morning optgroup
        if (morningSlots.length > 0) {
          var morningGroup = document.createElement('optgroup');
          morningGroup.label = 'Morning';
          
          morningSlots.forEach(slot => {
            var option = document.createElement('option');
            option.value = slot.time;
            
            if (slot.disabled) {
              option.disabled = true;
              if (slot.disabled_reason === 'booked') {
                option.textContent = slot.label + ' (BOOKED)';
                option.style.color = '#999';
              } else if (slot.disabled_reason === 'past') {
                option.textContent = slot.label + ' (PAST)';
                option.style.color = '#ccc';
              }
            } else {
              option.textContent = slot.label + ' (Available)';
              option.style.color = '#008000';
            }
            
            morningGroup.appendChild(option);
          });
          
          timeSelect.appendChild(morningGroup);
        }
        
        // Add Afternoon optgroup
        if (afternoonSlots.length > 0) {
          var afternoonGroup = document.createElement('optgroup');
          afternoonGroup.label = 'Afternoon';
          
          afternoonSlots.forEach(slot => {
            var option = document.createElement('option');
            option.value = slot.time;
            
            if (slot.disabled) {
              option.disabled = true;
              if (slot.disabled_reason === 'booked') {
                option.textContent = slot.label + ' (BOOKED)';
                option.style.color = '#999';
              } else if (slot.disabled_reason === 'past') {
                option.textContent = slot.label + ' (PAST)';
                option.style.color = '#ccc';
              }
            } else {
              option.textContent = slot.label + ' (Available)';
              option.style.color = '#008000';
            }
            
            afternoonGroup.appendChild(option);
          });
          
          timeSelect.appendChild(afternoonGroup);
        }
      }
      
      function resetTimeSlots() {
        var timeSelect = document.getElementById('schedule_time');
        timeSelect.innerHTML = '<option value="">-- Select a time slot --</option>';
        timeSelect.value = '';
      }

      function toggleOtherInput(){
        var otherRadio = document.querySelector('input[name="service"][data-other="1"]');
        var helper = document.getElementById('other-helper');
        var comment = document.getElementById('comment');
        if(!helper || !comment) return;
        if (otherRadio && otherRadio.checked) {
          helper.style.display = 'block';
          comment.required = true;
        } else {
          helper.style.display = 'none';
          comment.required = false;
        }
      }

      function filterTimeSlots() {
        // Reload time slots when service changes
        loadTimeSlots();
      }

      // Attach change listeners
      var dateInput = document.getElementById('schedule_date');
      if (dateInput) {
        dateInput.addEventListener('change', loadTimeSlots);
      }
      
      var serviceRadios = document.querySelectorAll('input[name="service"]');
      serviceRadios.forEach(function(r){ 
        r.addEventListener('change', toggleOtherInput);
        r.addEventListener('change', filterTimeSlots);
      });

      // Ensure state on load
      document.addEventListener('DOMContentLoaded', function(){ 
        toggleOtherInput();
      });

      // On submit, if Other selected ensure description exists
      var schedForm = document.querySelector('form[method="post"][action="schedule.php"]');
      if (schedForm) {
        schedForm.addEventListener('submit', function(e){
          var checked = document.querySelector('input[name="service"]:checked');
          if (!checked) return;

          var isOther = checked.dataset && checked.dataset.other === '1';
          if (isOther) {
            var commentEl = document.getElementById('comment');
            var cur = (commentEl || {value:''}).value.trim();
            if (!cur) {
              e.preventDefault();
              alert('Please describe the service you need in the Additional Information box before submitting.');
              commentEl && commentEl.focus();
              return false;
            }
          }
        });
      }
    })();
  </script>
</body>
</html>
