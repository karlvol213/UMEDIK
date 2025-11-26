<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';
require_once 'config/functions.php';


if (isset($_SESSION['user_id'])) {
    log_action($_SESSION['user_id'], "Visited Home Page");
}
?>
<!DOCTYPE html>
<html lang="en"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to UMak Medical Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/common.css">
    <style>
        .hero-illustration {
            background: linear-gradient(45deg, #f0f9ff, #e0f2fe);
            border-radius: 20px;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .btn-hover:hover {
            transform: translateY(-2px);
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            transition: all 0.3s ease;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
        }
    </style>
</head>
<body class="min-h-screen" style="background: linear-gradient(to bottom right, #ffffff, #cce0ff); background-attachment: fixed; background-repeat: no-repeat;">
    <?php include 'includes/tailwind_nav.php'; ?>
    <div class="min-h-screen" style="padding-top: 0;">
        <div class="pt-16">
            <div class="container mx-auto px-4 py-8 max-w-6xl">
                <header class="text-center mb-12">
                <h1 class="text-4xl md:text-5xl font-bold text-navy-900 mb-4" style="color: #1e3a8a;">
                    Welcome to UMak Medical Clinic
                </h1>
            </header>

           
            <?php if(isset($_SESSION['user_id'])): 
                $user_appointments = get_user_appointments($_SESSION['user_id']);
                $appointment_count = count($user_appointments);
                $upcoming_count = 0;
                
                foreach ($user_appointments as $appt) {
                    if ($appt['status'] === 'approved' && strtotime($appt['appointment_date']) >= time()) {
                        $upcoming_count++;
                    }
                }
                ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?= $appointment_count ?></div>
                        <div class="stat-label">Total Appointments</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $upcoming_count ?></div>
                        <div class="stat-label">Upcoming Appointments</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= count(get_all_services()) ?></div>
                        <div class="stat-label">Available Services</div>
                    </div>
                </div>
            <?php endif; ?>


            <div class="bg-[#003366] text-white rounded-2xl p-8 mb-12 card-shadow">
                <div class="grid md:grid-cols-2 gap-8">
                    
                    <div class="space-y-6">
                        <div class="text-center mb-8">
                            <img src="./assets/images/clinic_umak.ico" alt="Medical and Dental Clinic Logo" class="h-[150px] w-auto mx-auto mb-6">
                        </div>
                        <h3 class="text-2xl font-bold mb-4 text-center">Core Values</h3>
                        <div class="space-y-4">
                            <div class="bg-white/10 p-4 rounded-lg">
                                <h4 class="font-bold mb-2">Professionalism</h4>
                                <p>We commit ourselves to the highest standards of professional practice and ethical behavior in health care delivery.</p>
                            </div>
                            <div class="bg-white/10 p-4 rounded-lg">
                                <h4 class="font-bold mb-2">Integrity</h4>
                                <p>We maintain honesty, transparency, and ethical conduct in all our interactions and services.</p>
                            </div>
                            <div class="bg-white/10 p-4 rounded-lg">
                                <h4 class="font-bold mb-2">Excellence</h4>
                                <p>We strive for continuous improvement and excellence in providing quality medical and dental care.</p>
                            </div>
                        </div>
                    </div>
                    
                    
                    <div class="space-y-6">
                        <h2 class="text-3xl font-bold mb-4">Medical and Dental Office</h2>
                        <p class="mb-6">
                            The Medical and Dental Clinic intends to provide basic health care for students and/or refer them to the specialist/primary health center if necessary. It provides first aid and triage for illnesses and injuries, direct services to students with special needs, and health counseling and education to students, staff, and parents.
                        </p>

                        <div class="grid md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <h3 class="text-xl font-bold mb-2">Vision</h3>
                                <p>For the University of Makati (UMAK) community to be healthy in mind and body through competent and quality medical care.</p>
                            </div>

                            <div>
                                <h3 class="text-xl font-bold mb-2">Mission</h3>
                                <p>With its commitment in service, the clinic shall provide the medical and dental needs of the University of Makati community by doing preventive medicine and treatment of illness.</p>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-xl font-bold mb-2">Services</h3>
                            <ul class="list-inside space-y-2">
                                <li>• Medical and Dental Services: Provide Free Medical and Dental Consultation & Services to Employees and Students.</li>
                                <li>• Health Awareness: Provide programs to promote health and prevent illness to Employees and Students.</li>
                                <li>• First Aid: Provide First Aid for Medical Emergencies.</li>
                                <li>• Prescriptions: Prescription and Issuance of Medicines.</li>
                                <li>• Implement Policies: Formulate & implement policies & procedures for the physical & mental health of employees and students.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        
            <div class="bg-white rounded-2xl p-6 mb-12 card-shadow">
                <h2 class="text-2xl font-bold text-[#003366] mb-4">Services Offered</h2>
                <div class="grid md:grid-cols-3 gap-6">
                
                    <div class="p-4 border rounded-lg">
                        <h3 class="font-bold text-lg mb-2">Check-up Consultation</h3>
                        <ul class="list-disc list-inside text-sm space-y-2">
                            <li><strong>Allergy</strong> – A reaction of the body’s immune system to something (like dust, food, or pollen) causing itching, sneezing, rashes, or swelling.</li>
                            <li><strong>Cold</strong> – A common viral infection causing runny nose, sore throat, coughing, and sneezing.</li>
                            <li><strong>Cough</strong> – When your body tries to clear the airways of mucus or irritants; can be dry or with phlegm.</li>
                            <li><strong>Dizziness</strong> – A lightheaded or unsteady feeling that may be caused by low blood pressure, dehydration.</li>
                            <li><strong>Headache</strong> – Pain in any part of the head; can result from stress, lack of sleep, or other medical conditions.</li>
                            <li><strong>Muscle Pain</strong> – Soreness or aching in muscles, often caused by overuse, tension, or injury.</li>
                            <li><strong>Period Cramps</strong> – Pain in the lower abdomen that happens before or during menstruation.</li>
                            <li><strong>Stomach Pain</strong> – Discomfort in the belly area, possibly due to indigestion, infection, or other digestive problems.</li>
                            <li><strong>Vomiting</strong> – When your body forces stomach contents out through the mouth; often caused by infection or stomach irritation.</li>
                            <li><strong>Wounds / Animal Bite</strong> – Cuts, scrapes, or bites that may need cleaning, bandaging, or vaccines to prevent infection.</li>
                        </ul>
                    </div>

            
                    <div class="p-4 border rounded-lg">
                        <h3 class="font-bold text-lg mb-2">Dental Consultation</h3>
                        <ul class="list-disc list-inside text-sm space-y-2">
                            <li><strong>Tooth Cleaning</strong> – Professional cleaning to remove plaque, tartar, and stains from the teeth and gums.</li>
                            <li><strong>Tooth Extracting</strong> – Removing a damaged, decayed, or impacted tooth (like a wisdom tooth).</li>
                            <li><strong>Tooth Repair / Paste</strong> – Fixing cavities or damaged teeth using dental filling or bonding materials.</li>
                        </ul>
                    </div>

        
                    <div class="p-4 border rounded-lg">
                        <h3 class="font-bold text-lg mb-2">Medical Consultation</h3>
                        <ul class="list-disc list-inside text-sm space-y-2">
                            <li><strong>Annual Physical Exam</strong> – A routine check-up to evaluate overall health, often required for work or school.</li>
                            <li><strong>Inquiry / Others</strong> – For general questions, medical advice, or other concerns not listed.</li>
                            <li><strong>Medical Clearance</strong> – A document or evaluation stating that you are fit for work, school, sports, or surgery.</li>
                        </ul>
                    </div>
                </div>
                
             
                <div class="mt-6">
                    <h3 class="text-xl font-semibold text-[#003366] mb-3">UMak Clinic Informercial</h3>
                    <div class="rounded-lg overflow-hidden bg-gray-100">
                        <?php
                        $videoFile = null;
                        $mediaDir = __DIR__ . DIRECTORY_SEPARATOR . 'media';
                        if (is_dir($mediaDir)) {
                            $files = scandir($mediaDir);
                            foreach ($files as $f) {
                                if (preg_match('/\.(mp4|webm|ogg)$/i', $f)) {
                                    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                                    $mimeType = [
                                        'mp4' => 'video/mp4',
                                        'webm' => 'video/webm',
                                        'ogg' => 'video/ogg'
                                    ][$ext] ?? 'video/mp4';
                                    $videoFile = ['src' => './media/' . rawurlencode($f), 'type' => $mimeType];
                                    break;
                                }
                            }
                        }
                        if ($videoFile): ?>
                            <video controls width="100%" preload="metadata" style="width:100%; height:auto; display:block; background:#000;" aria-label="UMak Clinic Informercial">
                                <source src="<?= $videoFile['src'] ?>" type="<?= $videoFile['type'] ?>">
                                Your browser does not support the video tag. You can
                                <a href="<?= $videoFile['src'] ?>" download>download the video</a> instead.
                            </video>
                        <?php else: ?>
                            <div class="p-12 text-center bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg" style="min-height: 300px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                                <svg class="w-16 h-16 text-blue-300 mb-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5h3V9h4v3h3l-5 5z"/>
                                </svg>
                                <p class="text-gray-600 font-semibold mb-2">Video Coming Soon</p>
                                <p class="text-sm text-gray-500">Upload an MP4, WebM, or OGG video file to <code class="bg-white px-2 py-1 rounded">/media/</code> folder</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

          
            <footer class="bg-white/20 backdrop-blur-sm rounded-2xl py-8 px-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                 
                    <div class="text-left">
                        <h1 class="text-2xl font-bold text-[#003366] mb-4">University of Makati</h1>
                        <p class="text-gray-700">
                            The University of Makati (UMak) is a locally funded university of the local government of Makati 
                            and is recognized by Commission on Higher Education.
                        </p>
                    </div>

                
                    <div class="text-center">
                        <h2 class="text-xl font-semibold text-[#003366] mb-4">Contact Information</h2>
                        <div class="space-y-2">
                            <p class="font-semibold">DR. ALAN ANGELO N. RAYMUNDO</p>
                            <p>Department Head</p>
                            <p>Direct Line: 8883-1863</p>
                            <p>Cellphone Number: 0945 648 2351</p>
                            <p>Email: clinic@umak.edu.ph</p>
                        </div>
                    </div>

                
                    <div class="text-right">
                        <h2 class="text-xl font-semibold text-[#003366] mb-4">Location</h2>
                        <p class="text-gray-700">
                            Ground Level, Administration Building,<br>
                            UMak Campus, JP Rizal Extn.,<br>
                            West Rembo, Taguig City 1644
                        </p>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script>
        // Scroll animation for cards
        window.addEventListener('scroll', function() {
            const cards = document.querySelectorAll('.card-hover');
            cards.forEach(card => {
                const rect = card.getBoundingClientRect();
                if (rect.top < window.innerHeight && rect.bottom > 0) {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }
            });
        });
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>