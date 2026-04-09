function toggleDarkMode() {
    document.documentElement.classList.toggle('dark');
    localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
}
if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark');
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const toast = document.createElement('div');
    const typeClasses = {
        success: 'bg-green-100 border-green-500 text-green-800 dark:bg-green-900/50 dark:text-green-200 dark:border-green-500',
        error: 'bg-red-100 border-red-500 text-red-800 dark:bg-red-900/50 dark:text-red-200 dark:border-red-500',
        info: 'bg-blue-100 border-blue-500 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200 dark:border-blue-500'
    };
    toast.className = `p-4 rounded-lg shadow-lg text-sm font-medium animate-entrance border-l-4 mb-3 ${typeClasses[type] || typeClasses.info} backdrop-blur-md`;
    toast.innerHTML = message; 
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-x-full', 'transition-all', 'duration-300');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

async function handlePost(e, url, onSuccessCallback) {
    if(e && e.preventDefault) e.preventDefault();
    const form = e.target;
    const btn = form.querySelector('button[type="submit"]') || form.querySelector('button');
    const originalText = btn ? btn.innerHTML : '';

    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...'; }

    try {
        const formData = new FormData(form);
        const jsonData = JSON.stringify(Object.fromEntries(formData));
        
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: jsonData
        });
        const text = await res.text();
        let data;
        
        try {
            data = JSON.parse(text);
        } catch (err) {
            throw new Error("Server Error: " + text.substring(0, 150) + "...");
        }

        if (res.ok && data.success) {
            showToast(data.message || 'Operation successful!', 'success');
            if(form && form.reset) form.reset();
            if (typeof onSuccessCallback === 'function') onSuccessCallback(data);
        } else {
            throw new Error(data.message || data.error || `Error (${res.status})`);
        }
    } catch (err) {
        console.error("Submission Error:", err);
        showToast(err.message, 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = originalText; }
    }
}

async function handleAuth(e, action) {
    await handlePost(e, `api/auth.php?action=${action}`, (data) => {
        if (action === 'login') {
            if (data.redirect) {
                window.location.href = data.redirect; 
            } else {
                window.location.href = 'index.html'; 
            }
        } 
        else if (action === 'register' && typeof toggleView === 'function') {
            toggleView('login');
        }
    });
}

async function loadAdminData() {
    try {
        const res = await fetch('api/admin.php?action=dashboard_data');
        const data = await res.json();
        if (!data.success) throw new Error(data.message);

        const deptListEl = document.getElementById('dept-list');
        if (deptListEl) deptListEl.innerHTML = data.departments.map(d => `<span class="inline-block px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-sm font-medium mr-2 mb-2">${d.name}</span>`).join('');

        document.querySelectorAll('select[name="department"]').forEach(select => {
            select.innerHTML = '<option value="">Select Department</option>' + data.departments.map(d => `<option value="${d.name}">${d.name}</option>`).join('');
        });

        const subjectListEl = document.getElementById('subject-list');
        if (subjectListEl) subjectListEl.innerHTML = data.subjects.map(s => `
            <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                <td class="p-3 font-mono font-bold text-blue-600 dark:text-blue-400">${s.code}</td>
                <td class="p-3 font-medium dark:text-gray-200">${s.name}</td>
                <td class="p-3"><span class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded text-xs">${s.department}</span></td>
                <td class="p-3 text-gray-600 dark:text-gray-400">Sem-${s.semester}</td>
            </tr>`).join('');
    } catch (error) {}
}

function openAnnouncementModal(data = null) {
    const modal = document.getElementById('announcement-modal');
    const form = document.getElementById('announcement-form');
    const titleEl = document.getElementById('announcement-modal-title');
    const btn = document.getElementById('ann-btn');

    if (data) {
        titleEl.innerText = "Edit Announcement";
        btn.innerText = "Save Changes";
        document.getElementById('ann-id').value = data.id;
        document.getElementById('ann-title').value = data.title;
        document.getElementById('ann-message').value = data.message;
        document.getElementById('ann-dept').value = data.target_dept;
        document.getElementById('ann-sem').value = data.target_sem;
        document.getElementById('ann-expiry').value = data.expires_at || '';
    } else {
        titleEl.innerText = "Post Announcement";
        btn.innerText = "Post Now";
        form.reset();
        document.getElementById('ann-id').value = "";
    }
    modal.classList.remove('hidden');
}

async function handlePostAnnouncement(e) {
    const id = document.getElementById('ann-id').value;
    const action = id ? 'update' : 'create';
    await handlePost(e, `api/announcement.php?action=${action}`, () => {
        document.getElementById('announcement-modal').classList.add('hidden');
        loadAnnouncements();
    });
}

async function deleteAnnouncement(id) {
    if(!confirm("Delete this announcement?")) return;
    try {
        const res = await fetch('api/announcement.php?action=delete', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id})
        });
        const data = await res.json();
        if(data.success) { showToast('Deleted.', 'success'); loadAnnouncements(); }
    } catch(e) { showToast('Error deleting.', 'error'); }
}

async function loadAnnouncements() {
    const list = document.getElementById('announcement-list');
    if (!list) return;
    
    try {
        const res = await fetch('api/announcement.php?action=list');
        const data = await res.json();
        
        if (!data.announcements || data.announcements.length === 0) {
            list.innerHTML = '<div class="col-span-full bg-white/50 dark:bg-gray-800/50 p-4 rounded-lg text-center text-gray-500 text-sm">No new announcements.</div>';
            return;
        }

        list.innerHTML = data.announcements.map(a => {
            const isExpired = a.expires_at && new Date(a.expires_at) < new Date();
            const expiryBadge = a.expires_at ? 
                `<span class="text-[10px] px-2 py-0.5 rounded ml-2 ${isExpired ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'}">
                    ${isExpired ? 'Expired' : 'Ends: ' + new Date(a.expires_at).toLocaleDateString()}
                 </span>` : '';
            
            const safeData = JSON.stringify(a).replace(/"/g, '&quot;');
            const deptDisplay = a.author_dept ? ` | <span class="font-bold text-blue-600 dark:text-blue-400">${a.author_dept}</span>` : '';

            return `
            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm border-l-4 ${isExpired ? 'border-gray-400 opacity-70' : 'border-orange-500 dark:border-orange-600'} flex flex-col justify-between relative group">
                <div>
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="font-bold text-gray-800 dark:text-white flex items-center">
                            ${a.title} ${expiryBadge}
                        </h4>
                        <span class="text-[10px] bg-gray-100 dark:bg-gray-700 text-gray-500 px-2 py-1 rounded">${new Date(a.created_at).toLocaleDateString()}</span>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">${a.message}</p>
                </div>
                <div class="flex justify-between items-center mt-2 text-xs text-gray-400 border-t dark:border-gray-700 pt-2">
                    <span class="flex items-center gap-1"><i class="fas fa-user-tie"></i> ${a.author}${deptDisplay}</span>
                    ${a.is_mine ? `
                        <div class="flex gap-2">
                            <button onclick="openAnnouncementModal(${safeData})" class="text-blue-500 hover:text-blue-700"><i class="fas fa-edit"></i></button>
                            <button onclick="deleteAnnouncement(${a.id})" class="text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button>
                        </div>
                    ` : ''}
                </div>
            </div>
        `}).join('');
    } catch(e) { console.error(e); }
}

function closeAnalytics() {
    document.getElementById('analytics-modal').classList.add('hidden');
    document.getElementById('analytics-modal').classList.remove('flex');
}

async function showLeaderboard(qid, title) {
    document.getElementById('analytics-title').textContent = `Leaderboard: ${title}`;
    const contentEl = document.getElementById('analytics-content');
    contentEl.innerHTML = '<div class="flex justify-center p-10"><i class="fas fa-spinner fa-spin fa-2x text-blue-500"></i></div>';
    document.getElementById('analytics-modal').classList.remove('hidden');
    document.getElementById('analytics-modal').classList.add('flex');

    const res = await fetch(`api/analytics.php?action=leaderboard&quiz_id=${qid}`);
    const data = await res.json();
    contentEl.innerHTML = `
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <table class="w-full text-left dark:text-white text-sm">
                <thead class="bg-gray-100 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 uppercase tracking-wider text-xs">
                    <tr><th class="p-4 font-semibold">Rank</th><th class="p-4 font-semibold">Student</th><th class="p-4 font-semibold">Score</th><th class="p-4 font-semibold">Date</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    ${data.leaderboard.length ? data.leaderboard.map((r, i) => `
                        <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="p-4">${i < 3 ? `<span class="text-xl drop-shadow-sm">${['🥇','🥈','🥉'][i]}</span>` : `<span class="font-bold text-gray-500 ml-2">#${i+1}</span>`}</td>
                            <td class="p-4 font-medium">${r.name}</td>
                            <td class="p-4"><span class="px-2.5 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded-md font-bold font-mono">${r.score}/${r.total_marks}</span></td>
                            <td class="p-4 text-gray-500 dark:text-gray-400 text-xs">${new Date(r.completed_at).toLocaleDateString()}</td>
                        </tr>`).join('') : '<tr><td colspan="4" class="p-8 text-center text-gray-500 dark:text-gray-400 italic">No attempts yet.</td></tr>'}
                </tbody>
            </table>
        </div>`;
}

let statsChart = null;
async function showTeacherStats(qid, title) {
    document.getElementById('analytics-title').textContent = `Analytics: ${title}`;
    const modal = document.getElementById('analytics-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    const contentEl = document.getElementById('analytics-content');
    contentEl.innerHTML = '<p class="text-center p-4">Loading stats...</p>';
    
    try {
        const resStats = await fetch(`api/analytics.php?action=quiz_stats&quiz_id=${qid}`);
        const dataStats = await resStats.json();
        const resFeed = await fetch(`api/feedback.php?action=list&quiz_id=${qid}`);
        const dataFeed = await resFeed.json();

        const passCount = dataStats.scores.filter(s => (s.score / s.total_marks) >= 0.5).length;
        const failCount = dataStats.scores.length - passCount;

        contentEl.innerHTML = `
            <div class="flex justify-between items-center mb-6">
                <h4 class="text-xl font-bold dark:text-white">Performance Overview</h4>
                <button onclick="window.location.href='api/analytics.php?action=export_csv&quiz_id=${qid}'" 
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-bold shadow-sm transition-all flex items-center">
                    <i class="fas fa-file-csv mr-2"></i> Export CSV
                </button>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-blue-50 dark:bg-blue-900/20 p-5 rounded-2xl text-center"><h4 class="text-blue-600 dark:text-blue-400 text-sm font-semibold uppercase">Attempts</h4><p class="text-3xl font-bold dark:text-white">${dataStats.stats.attempts}</p></div>
                <div class="bg-purple-50 dark:bg-purple-900/20 p-5 rounded-2xl text-center"><h4 class="text-purple-600 dark:text-purple-400 text-sm font-semibold uppercase">Avg Score</h4><p class="text-3xl font-bold dark:text-white">${parseFloat(dataStats.stats.avg_score||0).toFixed(1)}</p></div>
                <div class="bg-green-50 dark:bg-green-900/20 p-5 rounded-2xl text-center"><h4 class="text-green-600 dark:text-green-400 text-sm font-semibold uppercase">High</h4><p class="text-3xl font-bold dark:text-white">${dataStats.stats.max_score||0}</p></div>
                <div class="bg-red-50 dark:bg-red-900/20 p-5 rounded-2xl text-center"><h4 class="text-red-600 dark:text-red-400 text-sm font-semibold uppercase">Low</h4><p class="text-3xl font-bold dark:text-white">${dataStats.stats.min_score||0}</p></div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-800/50 p-6 rounded-2xl mb-8 border dark:border-gray-700">
                <h4 class="text-center font-semibold text-gray-700 dark:text-gray-300 mb-6">Performance</h4>
                <div class="h-64 flex justify-center"><canvas id="statsChart"></canvas></div>
            </div>
            <h4 class="font-bold text-xl dark:text-white mb-4">Student Feedback</h4>
            <div class="space-y-3 max-h-60 overflow-y-auto mb-8 pr-2 custom-scrollbar">
                ${dataFeed.feedback.length ? dataFeed.feedback.map(f => `
                    <div class="bg-white dark:bg-gray-700 p-4 rounded-lg border border-gray-100 dark:border-gray-600 shadow-sm">
                        <div class="flex justify-between mb-1">
                            <span class="font-bold text-sm dark:text-white">${f.student_name}</span>
                            <span class="text-yellow-400 text-xs tracking-widest">${'★'.repeat(f.rating)}${'☆'.repeat(5-f.rating)}</span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-300 italic">"${f.message || 'No comment'}"</p>
                    </div>
                `).join('') : '<p class="text-gray-500 text-sm italic text-center py-2">No feedback yet.</p>'}
            </div>
            <h4 class="font-bold text-xl dark:text-white mb-4">Detailed Results</h4>
            <div id="student-results-table" class="max-h-80 overflow-y-auto rounded-xl border border-gray-200 dark:border-gray-700"></div>
        `;
        
        setTimeout(() => {
            if (statsChart) statsChart.destroy();
            const ctx = document.getElementById('statsChart').getContext('2d');
            statsChart = new Chart(ctx, {
                type: 'doughnut',
                data: { labels: ['Passed', 'Failed'], datasets: [{ data: [passCount, failCount], backgroundColor: ['#10b981', '#ef4444'], borderWidth: 0 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: document.documentElement.classList.contains('dark') ? '#fff' : '#333' } } } }
            });
        }, 100);

        loadStudentResults(qid); 
    } catch (e) { contentEl.innerHTML = `<p class="text-red-500 text-center p-4">Error: ${e.message}</p>`; }
}

async function loadStudentResults(qid) {
    const res = await fetch(`api/analytics.php?action=student_results&quiz_id=${qid}`);
    const data = await res.json();
    document.getElementById('student-results-table').innerHTML = `<table class="w-full text-sm text-left dark:text-white"><thead class="bg-gray-100 dark:bg-gray-700/80 sticky top-0 backdrop-blur-md"><tr><th class="p-3">Name</th><th class="p-3">Score</th><th class="p-3">Time</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-700">${data.results.map(r=>`<tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50"><td class="p-3 font-medium">${r.name}<div class="text-xs text-gray-400">${r.email}</div></td><td class="p-3"><span class="px-2 py-1 rounded-md font-mono font-bold ${ (r.score/r.total_marks) >= 0.5 ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'}">${r.score}/${r.total_marks}</span></td><td class="p-3 text-gray-500 text-xs">${new Date(r.completed_at).toLocaleString()}</td></tr>`).join('')}</tbody></table>`;
}

async function loadQuizzes() {
    try {
        const res = await fetch('api/quiz.php?action=list');
        const data = await res.json();
        
        const subjSelect = document.getElementById('quiz-subject-select');
        if (data.subjects && subjSelect && subjSelect.options.length <= 1) {
            subjSelect.innerHTML = '<option value="">Select Subject</option>' + 
                data.subjects.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
        }

        const list = document.getElementById('quiz-list');
        if (!list) return;

        if (!data.quizzes || data.quizzes.length === 0) {
            list.innerHTML = '<p class="text-gray-500 p-6 text-center italic">No quizzes found.</p>';
            return;
        }

        list.innerHTML = data.quizzes.map(q => {
            const formatD = (d) => d ? new Date(d).toLocaleString([], {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'}) : 'No Date';
            
            if (q.q_count !== undefined) {
                const qSafe = JSON.stringify(q).replace(/"/g, '&quot;');
                
                return `
                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-md transition-all group">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-[10px] font-bold text-blue-600 bg-blue-50 dark:bg-blue-900/30 px-2 py-1 rounded uppercase tracking-wide">${q.subject_name}</span>
                        <div class="flex gap-2 opacity-100 lg:opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="editQuiz(${qSafe})" class="text-gray-400 hover:text-blue-500 transition-colors" title="Edit"><i class="fas fa-edit"></i></button>
                            <button onclick="deleteQuiz(${q.id})" class="text-gray-400 hover:text-red-500 transition-colors" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    
                    <h3 class="font-bold text-lg dark:text-white truncate">${q.title}</h3>
                    
                    <div class="mt-3 space-y-1">
                        <div class="flex items-center text-xs text-green-600 dark:text-green-400">
                            <i class="far fa-calendar-check w-5"></i> <span>Start: ${formatD(q.start_time)}</span>
                        </div>
                        <div class="flex items-center text-xs text-red-600 dark:text-red-400">
                            <i class="far fa-calendar-times w-5"></i> <span>End: ${formatD(q.end_time)}</span>
                        </div>
                        <div class="flex items-center text-xs text-gray-500 dark:text-gray-400">
                            <i class="far fa-clock w-5"></i> <span>${q.duration_minutes} mins • ${q.q_count} Questions</span>
                        </div>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <button onclick="openQuestionModal(${q.id})" class="flex-1 px-3 py-1.5 bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 rounded text-sm font-semibold hover:bg-purple-100 dark:hover:bg-purple-800/50 transition-colors">Manage Q's</button>
                        <button onclick="showTeacherStats(${q.id}, '${q.title.replace(/'/g, "\\'")}')" class="px-3 py-1.5 bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 rounded hover:bg-blue-100 dark:hover:bg-blue-800/50 transition-colors"><i class="fas fa-chart-pie"></i></button>
                    </div>
                </div>`;
            } 
            
            const now = new Date();
            const start = q.start_time ? new Date(q.start_time) : null;
            const end = q.end_time ? new Date(q.end_time) : null;
            
            let statusBadge = '';
            let btnState = `onclick="startQuiz(${q.id})" class="bg-blue-600 hover:bg-blue-700 text-white"`;
            let btnText = 'Start Quiz';

            if (start && now < start) {
                statusBadge = `<span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded">Starts: ${formatD(q.start_time)}</span>`;
                btnState = `disabled class="bg-gray-300 dark:bg-gray-700 text-gray-500 cursor-not-allowed"`;
                btnText = 'Not Started';
            } else if (end && now > end) {
                statusBadge = `<span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded">Expired: ${formatD(q.end_time)}</span>`;
                btnState = `disabled class="bg-gray-300 dark:bg-gray-700 text-gray-500 cursor-not-allowed"`;
                btnText = 'Expired';
            } else {
                statusBadge = `<span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">Active until ${formatD(q.end_time)}</span>`;
            }

            return `
            <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-lg transition-all">
                <div class="flex justify-between items-start mb-2">
                    <span class="text-[10px] font-bold text-blue-600 bg-blue-50 dark:bg-blue-900/30 px-2 py-1 rounded uppercase">${q.subject_name}</span>
                    ${q.author ? `<button onclick="showLeaderboard(${q.id}, '${q.title.replace(/'/g, "\\'")}')" class="text-yellow-400 hover:text-yellow-500 transition-colors transform hover:scale-110"><i class="fas fa-trophy fa-lg"></i></button>` : ''}
                </div>
                <h3 class="font-bold text-lg dark:text-white mb-2 line-clamp-1">${q.title}</h3>
                <div class="mb-4">${statusBadge}</div>
                <div class="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-gray-700">
                    <div class="text-sm text-gray-500 dark:text-gray-400"><i class="far fa-clock mr-2"></i> ${q.duration_minutes} mins</div>
                    <button ${btnState} class="px-4 py-2 rounded-lg font-semibold text-sm transition-all shadow-sm">${btnText}</button>
                </div>
            </div>`;
        }).join('');

    } catch (error) { console.error(error); }
}

async function handleQuizSave(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    try {
        const res = await fetch('api/quiz.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const text = await res.text();
        let result;
        try { result = JSON.parse(text); } catch(e) { throw new Error("Server Error: " + text.substring(0,100)); }
        
        if (result.success) {
            showToast(result.message, 'success');
            resetQuizForm();
            loadQuizzes();
        } else {
            showToast(result.message, 'error');
        }
    } catch (e) { showToast(e.message, 'error'); }
}

function editQuiz(q) {
    document.getElementById('form-quiz-id').value = q.id;
    document.getElementById('quiz-subject-select').value = q.subject_id;
    document.getElementById('form-title').value = q.title;
    document.getElementById('form-desc').value = q.description;
    document.getElementById('form-duration').value = q.duration_minutes;
    document.getElementById('form-start').value = q.start_time ? q.start_time.replace(' ', 'T') : '';
    document.getElementById('form-end').value = q.end_time ? q.end_time.replace(' ', 'T') : '';

    document.getElementById('quiz-form-title').textContent = "Edit Quiz";
    const btn = document.getElementById('btn-save-quiz');
    btn.textContent = "Update Quiz";
    btn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
    btn.classList.add('bg-orange-600', 'hover:bg-orange-700');
    
    document.getElementById('btn-cancel-edit').classList.remove('hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetQuizForm() {
    document.getElementById('quiz-form').reset();
    document.getElementById('form-quiz-id').value = '';
    
    document.getElementById('quiz-form-title').textContent = "Create Quiz";
    const btn = document.getElementById('btn-save-quiz');
    btn.textContent = "Create Quiz";
    btn.classList.add('bg-purple-600', 'hover:bg-purple-700');
    btn.classList.remove('bg-orange-600', 'hover:bg-orange-700');
    
    document.getElementById('btn-cancel-edit').classList.add('hidden');
}

async function deleteQuiz(id) {
    if (!confirm("Are you sure you want to delete this quiz? This cannot be undone.")) return;
    try {
        const res = await fetch('api/quiz.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ quiz_id: id })
        });
        const result = await res.json();
        if (result.success) {
            showToast('Quiz deleted.', 'success');
            loadQuizzes();
        } else {
            showToast(result.message, 'error');
        }
    } catch (e) { showToast('Error deleting quiz.', 'error'); }
}

let currentSubmittedQuizId = null;
let quizTimerInterval = null;

// Only ONE startQuiz function here
async function startQuiz(qid) {
    try {
        const res = await fetch(`api/attempt.php?action=start&quiz_id=${qid}`);
        
        const text = await res.text();
        let data;
        try { data = JSON.parse(text); } catch (e) { throw new Error("Server Error: " + text.substring(0, 100)); }

        if (!data.success) { 
            alert(data.message); 
            return; 
        }

        document.getElementById('main-dashboard').classList.add('hidden');
        document.getElementById('quiz-taker').classList.remove('hidden');
        document.getElementById('qt-title').textContent = data.quiz.title;
        document.getElementById('qt-quiz-id').value = qid;

        // Force log the data to the console so we can see what the database sent
        console.log("🔥 QUESTIONS LOADED FROM DATABASE:", data.questions);

        renderStudentQuiz(data.questions);
        
        startTimer(data.quiz.duration_minutes);
    } catch (e) { alert(e.message); }
}

function renderStudentQuiz(questions) {
    const container = document.getElementById('qt-questions');
    container.innerHTML = "";

    questions.forEach((q, index) => {
        // Log each question's type to see what we are dealing with
        console.log(`Question ${index + 1} Type Raw Data:`, q.type, q.question_type);

        // Normalize the type (Handle nulls, spaces, and case issues)
        const rawType = String(q.type || q.question_type || '').trim().toUpperCase();
        const qId = q.id || q.question_id;
        
        let inputHtml = '';
        let displayBadge = rawType;

        // 1. MCQ
        if (rawType === 'MCQ') {
            displayBadge = 'Single Choice';
            inputHtml = ['a', 'b', 'c', 'd'].map(opt => `
                <label class="flex items-center p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg cursor-pointer hover:border-blue-500 transition-all">
                    <input type="radio" name="answers[${qId}]" value="${opt}" class="w-4 h-4 text-blue-600">
                    <span class="ml-3 text-gray-700 dark:text-gray-300">${q['option_' + opt] || q['option_'+opt.toUpperCase()] || ''}</span>
                </label>`).join('');
        } 
        // 2. MSQ
        else if (rawType === 'MSQ') {
            displayBadge = 'Select All That Apply';
            inputHtml = ['a', 'b', 'c', 'd'].map(opt => `
                <label class="flex items-center p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg cursor-pointer hover:border-purple-500 transition-all">
                    <input type="checkbox" name="answers[${qId}][]" value="${opt}" class="w-4 h-4 text-purple-600 rounded">
                    <span class="ml-3 text-gray-700 dark:text-gray-300">${q['option_' + opt] || q['option_'+opt.toUpperCase()] || ''}</span>
                </label>`).join('');
        } 
        // 3. CATCH-ALL FOR DESCRIPTIVE (If it's NOT MCQ or MSQ, force a text area)
        else {
            displayBadge = 'Descriptive Answer';
            inputHtml = `
                <textarea name="answers[${qId}]" rows="4" 
                class="w-full p-4 mt-2 rounded-lg border-2 border-orange-300 dark:bg-gray-900 dark:border-gray-600 dark:text-white focus:ring-4 focus:ring-orange-200 outline-none transition-all shadow-inner" 
                placeholder="Type your detailed descriptive answer here..."></textarea>`;
            
            console.log(`✅ Rendering Textarea for Question ${qId}`);
        }

        let html = `
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
        
        container.innerHTML += html;
    });
}

function startTimer(minutes) {
    let timeLeft = parseInt(minutes) * 60;
    const timerEl = document.getElementById('qt-timer');
    if (quizTimerInterval) clearInterval(quizTimerInterval);
    quizTimerInterval = setInterval(() => {
        timeLeft--;
        const m = Math.floor(timeLeft / 60).toString().padStart(2, '0');
        const s = (timeLeft % 60).toString().padStart(2, '0');
        timerEl.textContent = `${m}:${s}`;
        if (timeLeft <= 0) { clearInterval(quizTimerInterval); submitQuiz(); }
    }, 1000);
}

async function submitQuiz(e) {
    if (e) e.preventDefault();
    if (quizTimerInterval) clearInterval(quizTimerInterval);
    const form = document.getElementById('qt-form');
    const formDataObj = new FormData(form);
    const answers = {};
    for (const [key, value] of formDataObj.entries()) {
        const match = key.match(/answers\[(\d+)\]/);
        if (match) {
            const qId = match[1];
            if (key.includes('[]')) { if (!answers[qId]) answers[qId] = []; answers[qId].push(value); }
            else { answers[qId] = value; }
        }
    }
    try {
        const res = await fetch('api/attempt.php?action=submit', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ quiz_id: document.getElementById('qt-quiz-id').value, answers: answers })
        });
        const result = await res.json();
        if (result.success) { 
            alert(`🎉 Quiz Submitted!\n\nScore: ${result.score}/${result.total}`);
            currentSubmittedQuizId = document.getElementById('qt-quiz-id').value;
            if (document.getElementById('fb-quiz-id')) {
                document.getElementById('fb-quiz-id').value = currentSubmittedQuizId;
                document.getElementById('quiz-taker').classList.add('hidden');
                document.getElementById('feedback-modal').classList.remove('hidden');
                document.getElementById('feedback-modal').classList.add('flex');
            } else { window.location.reload(); }
        } else { alert('Error: ' + result.message); }
    } catch (err) { console.error(err); }
}

async function submitFeedback(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const rating = formData.get('rating');
    const message = formData.get('message');
    if (!rating) { alert("Please select a star rating."); return; }
    try {
        const res = await fetch('api/feedback.php?action=submit', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ quiz_id: currentSubmittedQuizId, rating: rating, message: message })
        });
        const result = await res.json();
        if(result.success) { showToast('Thanks!', 'success'); setTimeout(() => window.location.reload(), 1000); }
        else { alert(result.message); setTimeout(() => window.location.reload(), 500); }
    } catch(err) { window.location.reload(); }
}

function toggleQuestionType() {
    const type = document.getElementById('q-type').value;
    const optsContainer = document.getElementById('options-container');
    const correctContainer = document.getElementById('correct-answer-container');
    
    if (type === 'DESCRIPTIVE' || type === 'DESC') optsContainer.classList.add('hidden');
    else optsContainer.classList.remove('hidden');

    let html = '';
    if (type === 'MCQ') {
        html = `<select name="correct_option" required class="w-full p-2 rounded border dark:bg-gray-800 dark:border-gray-700 dark:text-white font-medium"><option value="">Select Correct Option</option><option value="a">Option A</option><option value="b">Option B</option><option value="c">Option C</option><option value="d">Option D</option></select>`;
    } else if (type === 'MSQ') {
        html = `<div class="flex gap-4 p-2 bg-white dark:bg-gray-800 rounded border dark:border-gray-700 items-center"><span class="text-sm font-bold text-gray-500 dark:text-gray-400">Correct:</span><label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" name="correct_msq" value="a" class="w-4 h-4 text-purple-600"> A</label><label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" name="correct_msq" value="b" class="w-4 h-4 text-purple-600"> B</label><label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" name="correct_msq" value="c" class="w-4 h-4 text-purple-600"> C</label><label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" name="correct_msq" value="d" class="w-4 h-4 text-purple-600"> D</label></div>`;
    } else {
        html = `<input type="text" name="correct_option" required placeholder="Type required keyword" class="w-full p-2 rounded border dark:bg-gray-800 dark:border-gray-700 dark:text-white font-mono bg-yellow-50 dark:bg-yellow-900/10"><p class="text-xs text-gray-500 mt-1">Answer MUST contain this word/phrase.</p>`;
    }
    correctContainer.innerHTML = html;
}

function openQuestionModal(qid) {
    currentQuizId = qid;
    document.getElementById('modal-quiz-id').value = qid;
    const modal = document.getElementById('question-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.getElementById('q-type').value = 'MCQ';
    toggleQuestionType(); 
    loadQuestions(qid);
}

function closeModal() {
    document.getElementById('question-modal').classList.add('hidden');
    document.getElementById('question-modal').classList.remove('flex');
    loadQuizzes();
}

async function loadQuestions(qid) {
    const listEl = document.getElementById('modal-questions-list');
    listEl.innerHTML = '<p class="text-center text-gray-500">Loading...</p>';
    try {
        const res = await fetch(`api/question.php?action=list&quiz_id=${qid}`);
        const data = await res.json();
        listEl.innerHTML = data.questions.length ? data.questions.map((q, i) => `
            <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg border border-gray-200 dark:border-gray-700 flex justify-between items-center group">
                <div><p class="font-medium dark:text-white"><span class="text-xs font-bold text-blue-600 bg-blue-100 px-1 rounded mr-2">${q.type}</span> ${q.question_text}</p><p class="text-xs font-semibold text-green-600 mt-1">Marks: ${q.marks}</p></div>
                 <button onclick="deleteQuestion(${q.id})" class="text-gray-400 hover:text-red-500 p-2"><i class="fas fa-trash-alt"></i></button>
            </div>`).join('') : '<p class="text-center text-gray-500 py-4">No questions added yet.</p>';
    } catch (e) { listEl.innerHTML = '<p class="text-red-500">Error loading questions.</p>'; }
}

async function handleAddQuestion(e) {
    e.preventDefault();
    const form = e.target;
    const type = form.querySelector('[name="type"]').value;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
data.quiz_id = currentQuizId;   // ✅ ADD THIS LINE
    if (type === 'MSQ') {
        const checkboxes = form.querySelectorAll('input[name="correct_msq"]:checked');
        if (checkboxes.length === 0) { alert("Select at least one correct option."); return; }
        data.correct_option = Array.from(checkboxes).map(cb => cb.value);
    }
    
    const btn = form.querySelector('button');
    btn.disabled = true; btn.innerHTML = 'Saving...';
    try {
        const res = await fetch('api/question.php?action=add', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) });
        const result = await res.json();
        if (result.success) { showToast('Saved!', 'success'); loadQuestions(currentQuizId); form.reset(); document.getElementById('modal-quiz-id').value = currentQuizId; document.getElementById('q-type').value = 'MCQ'; toggleQuestionType(); }
        else { showToast(result.message, 'error'); }
    } catch(err) { showToast('Network Error', 'error'); } 
    finally { btn.disabled = false; btn.innerHTML = 'Add Question'; }
}

async function deleteQuestion(qid) {
    if (!confirm('Delete this question?')) return;
    try {
         const res = await fetch('api/question.php?action=delete', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({question_id: qid}) });
         if ((await res.json()).success) { showToast('Deleted.', 'success'); loadQuestions(currentQuizId); }
    } catch(e) { showToast('Error.', 'error'); }
}