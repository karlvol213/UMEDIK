<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $department = trim($_POST["department"] ?? 'Not specified');
    $first_name = trim($_POST["first_name"]);
    $last_name = trim($_POST["last_name"]);
    $middle_name = trim($_POST["middle_name"] ?? '');
    $birthday = trim($_POST["birthday"] ?? '');
    $age = trim($_POST["age"] ?? '');
    $sex = trim($_POST["sex"] ?? 'Not specified');
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $password_verify = trim($_POST["password_verify"] ?? '');
    $phone = trim($_POST["phone"] ?? '');
    $student_id = trim($_POST["student_id"] ?? '');
    $address = trim($_POST["address"] ?? 'Not specified');
    $special_status = trim($_POST["special_status"] ?? 'none');

    $full_name = $first_name . ' ' . $middle_name . ' ' . $last_name;

    // Validate age - must be at least 17 years old
    $age_validation_error = null;
    if (!empty($birthday)) {
        $birthDate = new DateTime($birthday);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        
        if ($age < 17) {
            $age_validation_error = "You must be at least 17 years old to register.";
        }
    }

    // Validate student ID - must be 1 letter and 8 numbers
    $student_id_validation_error = null;
    if (!empty($student_id)) {
        if (!preg_match('/^[a-zA-Z]\d{8}$/', $student_id)) {
            $student_id_validation_error = "Student ID must be 1 letter followed by 8 numbers.";
        }
    }

    // Validate names - must be letters only (no numbers, no symbols)
    $name_validation_error = null;
    if (!preg_match('/^[a-zA-Z\s\-\']+$/', $first_name)) {
        $name_validation_error = "First Name must contain letters only (no numbers or symbols).";
    } elseif (!preg_match('/^[a-zA-Z\s\-\']*$/', $middle_name) && !empty($middle_name)) {
        $name_validation_error = "Middle Name must contain letters only (no numbers or symbols).";
    } elseif (!preg_match('/^[a-zA-Z\s\-\']+$/', $last_name)) {
        $name_validation_error = "Last Name must contain letters only (no numbers or symbols).";
    }

    // Validate email - must be @umak.edu.ph domain only
    $email_validation_error = null;
    if (!empty($email)) {
        if (!preg_match('/@umak\.edu\.ph$/', $email)) {
            $email_validation_error = "Email must be a valid UMAK email address (@umak.edu.ph).";
        }
    }

    // basta security sa password
    if ($name_validation_error) {
        $errorMessage = $name_validation_error;
    } elseif ($email_validation_error) {
        $errorMessage = $email_validation_error;
    } elseif ($student_id_validation_error) {
        $errorMessage = $student_id_validation_error;
    } elseif ($age_validation_error) {
        $errorMessage = $age_validation_error;
    } elseif ($phone_validation_error) {
        $errorMessage = $phone_validation_error;
    } elseif (strlen($password) < 8) {
        $errorMessage = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/^[A-Z]/', $password)) {
        $errorMessage = "Password must start with an uppercase letter.";
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errorMessage = "Password must contain at least one symbol.";
    } elseif ($password !== $password_verify) {
        $errorMessage = "Passwords do not match. Please try again.";
    } elseif (!empty($first_name) && !empty($last_name) && !empty($email) && !empty($password) && !empty($department)) {

        $existing_user = get_user_by_email($email);
        
        if (!$existing_user) {
            // Format the birthday to MySQL date format
            $formatted_birthday = !empty($birthday) ? date('Y-m-d', strtotime($birthday)) : null;

            if (create_user($email, $password, $first_name, $last_name, $middle_name, $phone, 
                          $department, $formatted_birthday, $age, $sex, $address, $special_status)) {
                $successMessage = " Registration successful! Please go back to <a href='index.php'>Log-in Page</a>.";
                
                $new_user = get_user_by_email($email);
                if ($new_user) {
                    log_action($new_user['id'], "User registered", "New patient account created");
                }
                
                header("refresh:2;url=index.php");
            } else {
                $errorMessage = " Registration failed. Please try again.";
            }
        } else {
            // Gmail checker
            $domain = '';
            if (strpos($email, '@') !== false) {
                $parts = explode('@', $email);
                $domain = strtolower(array_pop($parts));
            }
            if ($domain === 'gmail.com') {
                $errorMessage = "This Gmail address is already registered.";
            } else {
                $errorMessage = "An account with this email already exists.";
            }
        }
    } else {
        $errorMessage = " Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Medical Appointment System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        }
        
        .form-container {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            width: 100%;
            max-width: 28rem;
            margin: 100px auto 30px;
        }
        .brand-logo { width:72px; height:auto; display:block; margin:0 auto }
    </style>
</head>
<body>
    <?php include 'includes/tailwind_nav.php'; ?>

    <div class="form-container">
        <div class="text-center">
            <img src="./assets/images/umak3.ico" alt="UMAK" style="height: 80px; width: auto; margin: 0 auto; display: block;">
            <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                Create your account
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                create account to book an appointment
            </p>
        </div>

        <?php if (!empty($successMessage)) : ?>
            <div class="mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                <span class="block sm:inline"><?= $successMessage; ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMessage)) : ?>
            <div class="mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                <span class="block sm:inline"><?= $errorMessage; ?></span>
            </div>
        <?php endif; ?>

        <form class="mt-8 space-y-6" method="post">
            <div class="rounded-md shadow-sm space-y-4">
                <div>
                    <label for="department" class="block text-sm font-medium text-gray-700">Department/College/Institute</label>
                    <input id="department" name="department" type="text" required class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                    <input id="first_name" name="first_name" type="text" required class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="First Name" >
                    <div id="first_name_validation_message" class="mt-2 text-sm hidden"></div>
                </div>

                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                    <input id="last_name" name="last_name" type="text" required class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="Last Name" >
                    <div id="last_name_validation_message" class="mt-2 text-sm hidden"></div>
                </div>

                <div>
                    <label for="middle_name" class="block text-sm font-medium text-gray-700">Middle Name</label>
                    <input id="middle_name" name="middle_name" type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="(optional)">
                    <div id="middle_name_validation_message" class="mt-2 text-sm hidden"></div>
                </div>

                <div>
                    <label for="birthday" class="block text-sm font-medium text-gray-700">Birthday</label>
                    <input id="birthday" name="birthday" type="date" required class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="age" class="block text-sm font-medium text-gray-700">Age</label>
                    <input id="age" name="age" type="number" required class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label for="sex" class="block text-sm font-medium text-gray-700">Sex</label>
                    <select id="sex" name="sex" required class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Select sex</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                    <input id="email" name="email" type="email" required 
                           class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" 
                           placeholder="  ">
                    <small class="text-gray-500 mt-1 block">UMAK email only (@umak.edu.ph)</small>
                    <div id="email-validation-message" class="mt-2 text-sm hidden"></div>
                </div>
                
                <div>
                    <label for="student_id" class="block text-sm font-medium text-gray-700">Student ID</label>
                    <input id="student_id" name="student_id" type="text" 
                           class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" 
                           placeholder="student ID">
                    <small class="text-gray-500 mt-1 block">Student ID must be 1 letter followed by 8 numbers (e.g., A12345678)</small>
                    <div id="student-id-validation-message" class="mt-2 text-sm hidden"></div>
                </div>
                
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                    <input id="phone" name="phone" type="tel" 
                           class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" 
                           placeholder="phone number">
                    <small class="text-gray-500 mt-1 block">Phone number must be exactly 11 digits</small>
                    <div id="phone-validation-message" class="mt-2 text-sm hidden"></div>
                </div>

                <div>
                    <label for="special_status" class="block text-sm font-medium text-gray-700">Special Status</label>
                    <select id="special_status" name="special_status" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="none">None</option>
                        <option value="pwd">PWD</option>
                        <option value="senior">Senior Citizen</option>
                    </select>
                </div>

                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                    <textarea id="address" name="address" rows="3"
                            class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                            placeholder="Enter your complete address"></textarea>
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <div class="relative">
                        <input id="password" name="password" type="password" required 
                               class="appearance-none relative block w-full px-3 py-2 pr-10 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" 
                               placeholder="Min. 8 chars, start with uppercase, include symbol">
                        <button type="button" class="absolute right-3 top-2 text-gray-500 hover:text-gray-700" 
                                onclick="togglePassword('password', 'toggleIcon1')">
                            <i id="toggleIcon1" class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small class="text-gray-500 mt-1 block">Password must be at least 8 characters, start with uppercase letter, and include a symbol</small>
                </div>

                <div>
                    <label for="password_verify" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                    <div class="relative">
                        <input id="password_verify" name="password_verify" type="password" required 
                               class="appearance-none relative block w-full px-3 py-2 pr-10 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" 
                               placeholder="Re-enter your password">
                        <button type="button" class="absolute right-3 top-2 text-gray-500 hover:text-gray-700" 
                                onclick="togglePassword('password_verify', 'toggleIcon2')">
                            <i id="toggleIcon2" class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small class="text-gray-500 mt-1 block">Passwords must match</small>
                    <div id="password-match-message" class="mt-2 text-sm hidden"></div>
                </div>
            </div>

            <div>
        <button type="submit" 
            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200 primary-cta">
                    Create Account
                </button>
            </div>

            <div class="text-center">
                <a href="index.php" class="text-sm text-indigo-600 hover:text-indigo-500 transition-colors duration-200">
                    Already have an account? Sign in
                </a>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const firstNameInput = document.getElementById('first_name');
        const lastNameInput = document.getElementById('last_name');
        const middleNameInput = document.getElementById('middle_name');
        const emailInput = document.getElementById('email');
        const studentIdInput = document.getElementById('student_id');
        const birthdayInput = document.getElementById('birthday');
        const ageInput = document.getElementById('age');
        const specialStatusSelect = document.getElementById('special_status');
        const phoneInput = document.getElementById('phone');
        const phoneValidationMessage = document.getElementById('phone-validation-message');
        const emailValidationMessage = document.getElementById('email-validation-message');
        const studentIdValidationMessage = document.getElementById('student-id-validation-message');
        const firstNameValidationMessage = document.getElementById('first_name_validation_message');
        const lastNameValidationMessage = document.getElementById('last_name_validation_message');
        const middleNameValidationMessage = document.getElementById('middle_name_validation_message');
        const passwordInput = document.getElementById('password');
        const passwordVerifyInput = document.getElementById('password_verify');
        const passwordMatchMessage = document.getElementById('password-match-message');
        const form = document.querySelector('form');

        // Validate name - letters only (no numbers, symbols)
        function validateName(name) {
            return /^[a-zA-Z\s\-']+$/.test(name);
        }

        // Real-time name validation
        function checkNameValidation(input, messageElement) {
            const value = input.value.trim();

            if (!value) {
                messageElement.classList.add('hidden');
                input.classList.remove('border-red-500', 'border-green-500');
                return true;
            }

            if (validateName(value)) {
                messageElement.textContent = ' Valid name (letters only)';
                messageElement.classList.remove('hidden', 'text-red-600');
                messageElement.classList.add('text-green-600');
                input.classList.add('border-green-500');
                input.classList.remove('border-red-500');
                return true;
            } else {
                messageElement.textContent = ' Invalid name (letters only, no numbers or symbols)';
                messageElement.classList.remove('hidden', 'text-green-600');
                messageElement.classList.add('text-red-600');
                input.classList.add('border-red-500');
                input.classList.remove('border-green-500');
                return false;
            }
        }

        // Add name validation listeners
        firstNameInput.addEventListener('input', () => checkNameValidation(firstNameInput, firstNameValidationMessage));
        lastNameInput.addEventListener('input', () => checkNameValidation(lastNameInput, lastNameValidationMessage));
        middleNameInput.addEventListener('input', () => {
            if (middleNameInput.value.trim()) {
                checkNameValidation(middleNameInput, middleNameValidationMessage);
            } else {
                middleNameValidationMessage.classList.add('hidden');
                middleNameInput.classList.remove('border-red-500', 'border-green-500');
            }
        });

        // Validate email - must be @umak.edu.ph domain
        function validateEmail(email) {
            return /@umak\.edu\.ph$/.test(email);
        }

        // Real-time email validation
        function checkEmailValidation() {
            const email = emailInput.value.trim();

            if (!email) {
                emailValidationMessage.classList.add('hidden');
                emailInput.classList.remove('border-red-500', 'border-green-500');
                return;
            }

            if (validateEmail(email)) {
                emailValidationMessage.textContent = '✓ Valid UMAK email';
                emailValidationMessage.classList.remove('hidden', 'text-red-600');
                emailValidationMessage.classList.add('text-green-600');
                emailInput.classList.add('border-green-500');
                emailInput.classList.remove('border-red-500');
            } else {
                emailValidationMessage.textContent = '✗ Email must be @umak.edu.ph';
                emailValidationMessage.classList.remove('hidden', 'text-green-600');
                emailValidationMessage.classList.add('text-red-600');
                emailInput.classList.add('border-red-500');
                emailInput.classList.remove('border-green-500');
            }
        }

        emailInput.addEventListener('input', checkEmailValidation);
        emailInput.addEventListener('change', checkEmailValidation);

        // Validate student ID - must be 1 letter followed by 8 numbers
        function validateStudentId(id) {
            return /^[a-zA-Z]\d{8}$/.test(id);
        }

        // Real-time student ID validation
        function checkStudentIdValidation() {
            const id = studentIdInput.value.trim();

            if (!id) {
                studentIdValidationMessage.classList.add('hidden');
                studentIdInput.classList.remove('border-red-500', 'border-green-500');
                return;
            }

            if (validateStudentId(id)) {
                studentIdValidationMessage.textContent = '✓ Valid student ID (1 letter + 8 numbers)';
                studentIdValidationMessage.classList.remove('hidden', 'text-red-600');
                studentIdValidationMessage.classList.add('text-green-600');
                studentIdInput.classList.add('border-green-500');
                studentIdInput.classList.remove('border-red-500');
            } else {
                studentIdValidationMessage.textContent = '✗ Invalid student ID (must be 1 letter + 8 numbers, e.g., A12345678)';
                studentIdValidationMessage.classList.remove('hidden', 'text-green-600');
                studentIdValidationMessage.classList.add('text-red-600');
                studentIdInput.classList.add('border-red-500');
                studentIdInput.classList.remove('border-green-500');
            }
        }

        studentIdInput.addEventListener('input', checkStudentIdValidation);

        // Validate phone number - must be exactly 11 digits
        function validatePhone(phone) {
            const phoneDigitsOnly = phone.replace(/[^0-9]/g, '');
            return phoneDigitsOnly.length === 11;
        }

        // Real-time phone validation
        function checkPhoneValidation() {
            const phone = phoneInput.value;

            if (!phone) {
                phoneValidationMessage.classList.add('hidden');
                phoneInput.classList.remove('border-red-500', 'border-green-500');
                return;
            }

            if (validatePhone(phone)) {
                phoneValidationMessage.textContent = '✓ Valid phone number (11 digits)';
                phoneValidationMessage.classList.remove('hidden', 'text-red-600');
                phoneValidationMessage.classList.add('text-green-600');
                phoneInput.classList.add('border-green-500');
                phoneInput.classList.remove('border-red-500');
            } else {
                const phoneDigitsOnly = phone.replace(/[^0-9]/g, '');
                phoneValidationMessage.textContent = `✗ Invalid phone number (${phoneDigitsOnly.length} digits, need 11)`;
                phoneValidationMessage.classList.remove('hidden', 'text-green-600');
                phoneValidationMessage.classList.add('text-red-600');
                phoneInput.classList.add('border-red-500');
                phoneInput.classList.remove('border-green-500');
            }
        }

        // Add event listener for phone input
        phoneInput.addEventListener('input', checkPhoneValidation);

        // Set maximum date to 17 years ago (minimum age requirement)
        function setMaxBirthdayDate() {
            const today = new Date();
            const minAge = new Date(today.getFullYear() - 17, today.getMonth(), today.getDate());
            const maxDate = minAge.toISOString().split('T')[0];
            birthdayInput.setAttribute('max', maxDate);
        }

        // Calculate age from birthday
        function calculateAge(birthday) {
            const today = new Date();
            const birthDate = new Date(birthday);
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            return age;
        }

        // Validate age and show error if under 17
        function validateAge(birthday) {
            if (!birthday) {
                ageInput.classList.remove('border-red-500', 'border-green-500');
                return true;
            }

            const age = calculateAge(birthday);

            if (age < 17) {
                ageInput.value = age;
                ageInput.classList.add('border-red-500');
                ageInput.classList.remove('border-green-500');
                birthdayInput.classList.add('border-red-500');
                birthdayInput.classList.remove('border-green-500');
                return false;
            } else {
                ageInput.value = age;
                ageInput.classList.add('border-green-500');
                ageInput.classList.remove('border-red-500');
                birthdayInput.classList.add('border-green-500');
                birthdayInput.classList.remove('border-red-500');
                return true;
            }
        }

        // Update age when birthday is selected
        birthdayInput.addEventListener('change', function() {
            if (this.value) {
                validateAge(this.value);
                
                const age = calculateAge(this.value);
                // Automatically set special status to senior if age >= 60
                if (age >= 60) {
                    specialStatusSelect.value = 'senior';
                }
            }
        });

        // Password validation
        function validatePassword(password) {
            // Check minimum length
            if (password.length < 8) {
                return 'Password must be at least 8 characters long';
            }
            // Check if first letter is uppercase
            if (!/^[A-Z]/.test(password)) {
                return 'Password must start with an uppercase letter';
            }
            // Check if contains at least one symbol
            if (!/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
                return 'Password must contain at least one symbol (!@#$%^&*(),.?":{}|<>)';
            }
            return '';
        }

        // Real-time password match validation
        function checkPasswordMatch() {
            const password = passwordInput.value;
            const passwordVerify = passwordVerifyInput.value;

            if (!passwordVerify) {
                passwordMatchMessage.classList.add('hidden');
                passwordVerifyInput.classList.remove('border-red-500', 'border-green-500');
                return;
            }

            if (password === passwordVerify) {
                passwordMatchMessage.textContent = '✓ Passwords match';
                passwordMatchMessage.classList.remove('hidden', 'text-red-600');
                passwordMatchMessage.classList.add('text-green-600');
                passwordVerifyInput.classList.add('border-green-500');
                passwordVerifyInput.classList.remove('border-red-500');
            } else {
                passwordMatchMessage.textContent = '✗ Passwords do not match';
                passwordMatchMessage.classList.remove('hidden', 'text-green-600');
                passwordMatchMessage.classList.add('text-red-600');
                passwordVerifyInput.classList.add('border-red-500');
                passwordVerifyInput.classList.remove('border-green-500');
            }
        }

        // Add event listeners for password matching
        passwordInput.addEventListener('input', checkPasswordMatch);
        passwordVerifyInput.addEventListener('input', checkPasswordMatch);

        // Add password validation on form submit
        form.addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const passwordVerify = passwordVerifyInput.value;
            const errorMessage = validatePassword(password);
            const firstName = firstNameInput.value.trim();
            const lastName = lastNameInput.value.trim();
            const middleName = middleNameInput.value.trim();
            const email = emailInput.value.trim();
            const studentId = studentIdInput.value.trim();
            const birthday = birthdayInput.value;
            const phone = phoneInput.value;
            
            if (!validateName(firstName)) {
                e.preventDefault();
                alert('First Name must contain letters only (no numbers or symbols).');
                firstNameInput.focus();
                return;
            }

            if (!validateName(lastName)) {
                e.preventDefault();
                alert('Last Name must contain letters only (no numbers or symbols).');
                lastNameInput.focus();
                return;
            }

            if (middleName && !validateName(middleName)) {
                e.preventDefault();
                alert('Middle Name must contain letters only (no numbers or symbols).');
                middleNameInput.focus();
                return;
            }

            if (!validateEmail(email)) {
                e.preventDefault();
                alert('Email must be a valid UMAK email address (@umak.edu.ph).');
                emailInput.focus();
                return;
            }

            if (studentId && !validateStudentId(studentId)) {
                e.preventDefault();
                alert('Student ID must be 1 letter followed by 8 numbers (e.g., A12345678).');
                studentIdInput.focus();
                return;
            }

            if (birthday && !validateAge(birthday)) {
                e.preventDefault();
                alert('You must be at least 17 years old to register.');
                birthdayInput.focus();
                return;
            }

            if (phone && !validatePhone(phone)) {
                e.preventDefault();
                const phoneDigitsOnly = phone.replace(/[^0-9]/g, '');
                alert(`Phone number must be exactly 11 digits. You entered ${phoneDigitsOnly.length} digits.`);
                phoneInput.focus();
                return;
            }

            if (errorMessage) {
                e.preventDefault();
                alert(errorMessage);
                passwordInput.focus();
                return;
            }

            if (password !== passwordVerify) {
                e.preventDefault();
                alert('Passwords do not match. Please try again.');
                passwordVerifyInput.focus();
                return;
            }
        });

        // Real-time password validation feedback
        passwordInput.addEventListener('input', function() {
            const error = validatePassword(this.value);
            this.setCustomValidity(error);
            
            // Add visual feedback
            if (error) {
                this.classList.add('border-red-500');
                this.classList.remove('border-green-500');
            } else if (this.value) {
                this.classList.add('border-green-500');
                this.classList.remove('border-red-500');
            } else {
                this.classList.remove('border-red-500', 'border-green-500');
            }

            // Re-check password match when password changes
            checkPasswordMatch();
        });

        // Initialize max birthday date on page load
        setMaxBirthdayDate();
    });

    // Toggle password visibility
    function togglePassword(fieldId, iconId) {
        const field = document.getElementById(fieldId);
        const icon = document.getElementById(iconId);
        
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    </script>
</body>
</html>