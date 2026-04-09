<?php
require_once 'api/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.html"); exit; }
$role = $_SESSION['role'];
$accents = ['student' => 'from-blue-600 to-cyan-500', 'teacher' => 'from-purple-600 to-pink-500', 'admin' => 'from-orange-500 to-red-500'];
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script> tailwind.config = { darkMode: 'class', theme: { extend: { animation: { blob: "blob 7s infinite" }, keyframes: { blob: { "0%": { transform: "translate(0px, 0px) scale(1)" }, "33%": { transform: "translate(30px, -50px) scale(1.1)" }, "66%": { transform: "translate(-20px, 20px) scale(0.9)" }, "100%": { transform: "translate(0px, 0px) scale(1)" } } } } } } </script>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .star-rating { direction: rtl; display: inline-flex; }
        .star-rating input { display: none; }
        .star-rating label { color: #ddd; font-size: 2rem; cursor: pointer; transition: color 0.2s; }
        .star-rating input:checked ~ label, .star-rating label:hover, .star-rating label:hover ~ label { color: #fbbf24; }
        #teacher-float-btn {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #6366f1, #9333ea);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
            z-index: 9999;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        #teacher-float-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 15px 30px rgba(0,0,0,0.35);
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-500 relative">
    
    <?php if ($role === 'teacher'): ?>
    <div id="teacher-float-btn" onclick="openTeacherAI()">
        <i class="fas fa-robot"></i>
    </div>
    <?php endif; ?>
    
    <div id="toast-container"></div>

    <div class="fixed top-0 left-0 w-full h-full overflow-hidden -z-10 pointer-events-none">
        <div class="absolute top-0 left-0 w-96 h-96 bg-purple-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob dark:bg-purple-900 dark:opacity-20"></div>
        <div class="absolute top-0 right-0 w-96 h-96 bg-yellow-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-2000 dark:bg-yellow-900 dark:opacity-20"></div>
        <div class="absolute -bottom-32 left-20 w-96 h-96 bg-pink-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-4000 dark:bg-pink-900 dark:opacity-20"></div>
    </div>

    <div id="analytics-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 w-full max-w-4xl rounded-2xl p-6 max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="flex justify-between mb-6 border-b dark:border-gray-700 pb-4">
                <h3 id="analytics-title" class="text-2xl font-bold dark:text-white">Analytics</h3>
                <button onclick="closeAnalytics()" class="text-gray-500 hover:text-red-500"><i class="fas fa-times fa-lg"></i></button>
            </div>
            <div id="analytics-content" class="space-y-6"></div>
        </div>
    </div>

    <div id="feedback-modal" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-[60] p-4 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 w-full max-w-md rounded-2xl p-8 shadow-2xl text-center animate-entrance">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">Great Job! 🎉</h2>
            <p class="text-gray-500 dark:text-gray-400 mb-6">How would you rate this quiz?</p>
            <form onsubmit="submitFeedback(event)">
                <input type="hidden" id="fb-quiz-id">
                <div class="star-rating mb-6 justify-center">
                    <input type="radio" id="star5" name="rating" value="5"><label for="star5"><i class="fas fa-star"></i></label>
                    <input type="radio" id="star4" name="rating" value="4"><label for="star4"><i class="fas fa-star"></i></label>
                    <input type="radio" id="star3" name="rating" value="3"><label for="star3"><i class="fas fa-star"></i></label>
                    <input type="radio" id="star2" name="rating" value="2"><label for="star2"><i class="fas fa-star"></i></label>
                    <input type="radio" id="star1" name="rating" value="1"><label for="star1"><i class="fas fa-star"></i></label>
                </div>
                <textarea name="message" placeholder="Any comments? (Optional)" class="w-full p-3 rounded-lg border dark:bg-gray-900 dark:border-gray-700 dark:text-white mb-6 focus:ring-2 focus:ring-blue-500 outline-none" rows="3"></textarea>
                <div class="flex gap-3">
                    <button type="button" onclick="window.location.reload()" class="flex-1 py-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">Skip</button>
                    <button type="submit" class="flex-1 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <div id="quiz-taker" class="fixed inset-0 bg-white dark:bg-gray-900 z-50 hidden overflow-y-auto">
         <div class="max-w-3xl mx-auto py-8 px-4">
            <div class="flex justify-between items-center mb-8 sticky top-0 bg-white dark:bg-gray-900 py-4 border-b dark:border-gray-800 z-10">
                <h2 id="qt-title" class="text-2xl font-bold dark:text-white">Quiz Title</h2>
                <div class="text-xl font-mono font-bold text-red-600 bg-red-50 dark:bg-red-900/20 px-3 py-1 rounded-lg" id="qt-timer">00:00</div>
            </div>
            <form id="qt-form" onsubmit="submitQuiz(event)">
                <input type="hidden" name="quiz_id" id="qt-quiz-id">
                <div id="qt-questions" class="space-y-8"></div>
                <button type="submit" class="w-full py-4 mt-8 bg-green-600 hover:bg-green-700 text-white rounded-xl font-bold text-lg shadow-lg transform hover:scale-[1.01] transition-all">Submit Quiz</button>
            </form>
        </div>
    </div>

    <div id="question-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 w-full max-w-2xl rounded-2xl p-6 max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="flex justify-between mb-4 border-b dark:border-gray-700 pb-2">
                <h3 class="text-xl font-bold dark:text-white">Manage Questions</h3>
                <button onclick="closeModal()" class="text-gray-500 hover:text-red-500"><i class="fas fa-times fa-lg"></i></button>
            </div>
            <form id="add-question-form" onsubmit="handleAddQuestion(event)" class="bg-gray-50 dark:bg-gray-900 p-4 rounded-xl mb-6 space-y-3 border dark:border-gray-700">
                <input type="hidden" name="quiz_id" id="modal-quiz-id">
                <select name="type" id="q-type" onchange="toggleQuestionType()" class="w-full p-2 rounded border font-bold text-blue-800 dark:bg-gray-800 dark:border-gray-700 dark:text-blue-400 outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="MCQ">Multiple Choice (MCQ)</option>
                    <option value="MSQ">Multiple Select (MSQ)</option>
                    <option value="DESCRIPTIVE">Descriptive (Keyword Match)</option>
                </select>
                <textarea name="question_text" required placeholder="Question Text" class="w-full p-2 rounded border dark:bg-gray-800 dark:border-gray-700 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                <div id="options-container" class="grid grid-cols-2 gap-2">
                    <input type="text" name="option_a" placeholder="Option A" class="p-2 rounded border dark:bg-gray-800 dark:border-gray-700 dark:text-white">
                    <input type="text" name="option_b" placeholder="Option B" class="p-2 rounded border dark:bg-gray-800 dark:border-gray-700 dark:text-white">
                    <input type="text" name="option_c" placeholder="Option C" class="p-2 rounded border dark:bg-gray-800 dark:border-gray-700 dark:text-white">
                    <input type="text" name="option_d" placeholder="Option D" class="p-2 rounded border dark:bg-gray-800 dark:border-gray-700 dark:text-white">
                </div>
                <div class="flex gap-4 items-center">
                    <div class="flex-1" id="correct-answer-container"></div>
                    <input type="number" name="marks" value="1" min="1" class="w-20 p-2 rounded border dark:bg-gray-800 dark:border-gray-700 dark:text-white" placeholder="Marks">
                </div>
                <button class="w-full py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition-colors">Add Question</button>
            </form>
            <div id="modal-questions-list" class="space-y-2"></div>
        </div>
    </div>

    <div id="announcement-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 w-full max-w-lg rounded-2xl p-6 shadow-2xl">
            <div class="flex justify-between mb-4 border-b dark:border-gray-700 pb-2">
                <h3 id="announcement-modal-title" class="text-xl font-bold dark:text-white">Post Announcement</h3>
                <button onclick="document.getElementById('announcement-modal').classList.add('hidden')" class="text-gray-500 hover:text-red-500"><i class="fas fa-times fa-lg"></i></button>
            </div>
            <form id="announcement-form" onsubmit="handlePostAnnouncement(event)" class="space-y-4">
                <input type="hidden" name="id" id="ann-id">
                <input type="text" name="title" id="ann-title" required placeholder="Title" class="w-full p-3 rounded-lg border dark:bg-gray-900 dark:border-gray-700 dark:text-white focus:ring-2 focus:ring-orange-500 outline-none">
                <textarea name="message" id="ann-message" required placeholder="Message..." rows="4" class="w-full p-3 rounded-lg border dark:bg-gray-900 dark:border-gray-700 dark:text-white focus:ring-2 focus:ring-orange-500 outline-none"></textarea>
                <div class="grid grid-cols-2 gap-2">
                    <select name="target_dept" id="ann-dept" required class="p-3 rounded-lg border dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                        <option value="">Select Dept</option><option value="CSE">CSE</option><option value="AIML">AIML</option><option value="ECE">ECE</option><option value="MECH">MECH</option><option value="CIVIL">CIVIL</option>
                    </select>
                    <select name="target_sem" id="ann-sem" required class="p-3 rounded-lg border dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                        <option value="">Select Sem</option><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option>
                    </select>
                </div>
                <div class="relative">
                    <label class="text-xs text-gray-500 dark:text-gray-400 ml-1">Expiration (Optional)</label>
                    <input type="text" id="ann-expiry" name="expires_at" class="w-full p-3 rounded-lg border dark:bg-gray-900 dark:border-gray-700 dark:text-white text-sm bg-white dark:bg-gray-900" placeholder="Select Date & Time">
                </div>
                <button id="ann-btn" class="w-full py-3 bg-orange-600 hover:bg-orange-700 text-white font-bold rounded-lg">Post Now</button>
            </form>
        </div>
    </div>

    <div id="main-dashboard">
        <header class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-md shadow-sm border-b border-gray-200 dark:border-gray-700 sticky top-0 z-30">
             <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
                <h1 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-gradient-to-tr <?php echo $accents[$role]; ?> shadow-lg"></span>
                    QuizPortal | <?php echo ucfirst($role); ?>
                </h1>
                <div class="flex items-center gap-4">
                    <button onclick="toggleDarkMode()" class="text-gray-500 hover:text-gray-900 dark:hover:text-white transition-colors"><i class="fas fa-adjust"></i></button>
                    <a href="change_password.php" class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                        <i class="fas fa-user-circle fa-lg"></i> <?php echo htmlspecialchars($_SESSION['name']); ?>
                    </a>
                    <a href="api/auth.php?action=logout" class="text-red-500 hover:text-red-700 ml-2"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-8">
            
            <div class="mb-8" id="announcement-section">
                <h2 class="text-xl font-bold dark:text-white mb-4 flex items-center">
                    <i class="fas fa-bullhorn text-orange-500 mr-2"></i> Notice Board
                    <?php if($role === 'teacher') echo '<button onclick="openAnnouncementModal()" class="ml-auto text-sm bg-orange-100 text-orange-700 px-3 py-1 rounded-lg hover:bg-orange-200 transition shadow-sm">+ Post New</button>'; ?>
                </h2>
                <div id="announcement-list" class="grid md:grid-cols-2 gap-4"></div>
            </div>

            <?php if ($role === 'admin'): ?>
            <div class="grid lg:grid-cols-3 gap-8">
                </div>

            <?php elseif ($role === 'teacher'): ?>
            <div class="grid lg:grid-cols-3 gap-8">
                <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur p-6 rounded-xl shadow-sm border dark:border-gray-700 h-fit lg:sticky lg:top-24">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold dark:text-white" id="quiz-form-title">Create Quiz</h3>
                        <button type="button" onclick="resetQuizForm()" id="btn-cancel-edit" class="hidden text-xs text-red-500 hover:text-red-700 font-bold uppercase transition-colors">Cancel Edit</button>
                    </div>

                    <form id="quiz-form" onsubmit="handleQuizSave(event)" class="space-y-4">
                        <input type="hidden" name="quiz_id" id="form-quiz-id">
                        
                        <select name="subject_id" id="quiz-subject-select" required class="w-full px-4 py-2 rounded-lg border dark:bg-gray-900 dark:border-gray-700 dark:text-white bg-white dark:bg-gray-900 focus:ring-2 focus:ring-purple-500 outline-none">
                            <option value="">Loading Subjects...</option>
                        </select>
                        
                        <input type="text" name="title" id="form-title" required placeholder="Quiz Title" class="w-full px-4 py-2 rounded-lg border dark:bg-gray-900 dark:border-gray-700 dark:text-white bg-white dark:bg-gray-900 focus:ring-2 focus:ring-purple-500 outline-none">
                        
                        <textarea name="description" id="form-desc" placeholder="Description" rows="2" class="w-full px-4 py-2 rounded-lg border dark:bg-gray-900 dark:border-gray-700 dark:text-white bg-white dark:bg-gray-900 focus:ring-2 focus:ring-purple-500 outline-none"></textarea>
                        
                        <div class="space-y-3">
                            <div>
                                <label class="text-xs text-gray-500 font-bold dark:text-gray-400 ml-1">Duration (Mins)</label>
                                <input type="number" name="duration" id="form-duration" value="30" required placeholder="Mins" class="w-full px-4 py-2 rounded-lg border dark:bg-gray-900 dark:border-gray-700 dark:text-white bg-white dark:bg-gray-900 focus:ring-2 focus:ring-purple-500 outline-none">
                            </div>
                            
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-xs text-gray-500 font-bold dark:text-gray-400 ml-1">Start Time</label>
                                    <input type="datetime-local" name="start_time" id="form-start" required class="w-full px-2 py-2 rounded-lg border dark:bg-gray-900 dark:border-gray-700 dark:text-white text-xs bg-white dark:bg-gray-900 focus:ring-2 focus:ring-purple-500 outline-none">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 font-bold dark:text-gray-400 ml-1">End Time</label>
                                    <input type="datetime-local" name="end_time" id="form-end" required class="w-full px-2 py-2 rounded-lg border dark:bg-gray-900 dark:border-gray-700 dark:text-white text-xs bg-white dark:bg-gray-900 focus:ring-2 focus:ring-purple-500 outline-none">
                                </div>
                            </div>
                        </div>

                        <button type="submit" id="btn-save-quiz" class="w-full py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-all shadow-md font-semibold">Create Quiz</button>
                    </form>
                </div>
                <div class="lg:col-span-2 space-y-6" id="quiz-list">Loading...</div>
            </div>

            <?php else: ?>
            <h2 class="text-2xl font-bold dark:text-white mb-6">Quizzes for <?php echo ($_SESSION['dept'] ?? 'N/A') . ' Sem-' . ($_SESSION['sem'] ?? 'N/A'); ?></h2>
            <div id="quiz-list" class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">Loading...</div>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="assets/js/app.js?v=13"></script>
    
    <script>
        // 1. Cleaned up initialization
        document.addEventListener('DOMContentLoaded', () => {
            const role = "<?php echo $role; ?>";
            console.log("Current Role Loaded:", role);

            if (role === 'admin' && typeof loadAdminData === 'function') {
                loadAdminData();
            } else if ((role === 'teacher' || role === 'student') && typeof loadQuizzes === 'function') {
                loadQuizzes();
                loadAnnouncements();
            }

            const annPicker = document.getElementById('ann-expiry');
            if (annPicker && window.flatpickr) {
                flatpickr(annPicker, { enableTime: true, dateFormat: "Y-m-d H:i:S", time_24hr: false, minDate: "today", disableMobile: "true" });
            }
        });
       
        function openTeacherAI() {
            window.location.href = 'teacher_dashboard.html';
        }

        // 2. HARDCODED QUIZ START LOGIC (Overrides app.js)
        async function startQuiz(qid) {
            try {
                const res = await fetch(`api/attempt.php?action=start&quiz_id=${qid}`);
                const text = await res.text();
                let data;
                try { data = JSON.parse(text); } catch (e) { throw new Error("Server Error"); }

                if (!data.success) { 
                    alert(data.message); 
                    return; 
                }

                document.getElementById('main-dashboard').classList.add('hidden');
                document.getElementById('quiz-taker').classList.remove('hidden');
                document.getElementById('qt-title').textContent = data.quiz.title;
                document.getElementById('qt-quiz-id').value = qid;

                // Render questions using our hardcoded bulletproof function below
                renderStudentQuiz(data.questions);
                
                startTimer(data.quiz.duration_minutes);
            } catch (e) { alert(e.message); }
        }

        // 3. HARDCODED RENDERING LOGIC (Guaranteed Textarea Display)
        function renderStudentQuiz(questions) {
            const container = document.getElementById('qt-questions');
            container.innerHTML = "";

            questions.forEach((q, index) => {
                // Force to string, trim spaces, convert to uppercase
                const rawType = String(q.type || q.question_type || '').trim().toUpperCase();
                const qId = q.id || q.question_id;
                
                let inputHtml = '';
                let displayBadge = rawType;

                // IF MCQ
                if (rawType === 'MCQ') {
                    displayBadge = 'Single Choice';
                    inputHtml = ['a', 'b', 'c', 'd'].map(opt => `
                        <label class="flex items-center p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg cursor-pointer hover:border-blue-500 transition-all">
                            <input type="radio" name="answers[${qId}]" value="${opt}" class="w-4 h-4 text-blue-600">
                            <span class="ml-3 text-gray-700 dark:text-gray-300">${q['option_' + opt] || q['option_'+opt.toUpperCase()] || ''}</span>
                        </label>`).join('');
                } 
                // IF MSQ
                else if (rawType === 'MSQ') {
                    displayBadge = 'Select All That Apply';
                    inputHtml = ['a', 'b', 'c', 'd'].map(opt => `
                        <label class="flex items-center p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg cursor-pointer hover:border-purple-500 transition-all">
                            <input type="checkbox" name="answers[${qId}][]" value="${opt}" class="w-4 h-4 text-purple-600 rounded">
                            <span class="ml-3 text-gray-700 dark:text-gray-300">${q['option_' + opt] || q['option_'+opt.toUpperCase()] || ''}</span>
                        </label>`).join('');
                } 
                // ANYTHING ELSE (Draws the Textarea)
                else {
                    displayBadge = 'Descriptive Answer';
                    inputHtml = `
                        <textarea name="answers[${qId}]" rows="4" 
                        class="w-full p-4 mt-2 rounded-lg border-2 border-orange-300 dark:bg-gray-900 dark:border-gray-600 dark:text-white focus:ring-4 focus:ring-orange-200 outline-none transition-all shadow-inner" 
                        placeholder="Type your detailed descriptive answer here..."></textarea>`;
                }

                // Inject into HTML
                container.innerHTML += `
                <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm mb-4">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <span class="inline-block px-2 py-0.5 rounded text-xs font-bold mb-2 ${rawType==='MCQ'?'bg-blue-100 text-blue-700':(rawType==='MSQ'?'bg-purple-100 text-purple-700':'bg-orange-100 text-orange-700')}">
                                ${displayBadge}
                            </span>
                            <h3 class="text-lg font-medium dark:text-white">
                                <span class="font-bold text-gray-400 mr-2">Q${index+1}.</span> ${q.question_text}
                            </h3>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md whitespace-nowrap">
                            ${q.marks || 1} Marks
                        </span>
                    </div>
                    <div class="space-y-3 md:ml-8">
                        ${inputHtml}
                    </div>
                </div>`;
            });
        }
    </script>
</body>
</html>