<?php
// lifeplan/index.php - لایف‌پلن با قابلیت تبدیل به تسک
session_start();
date_default_timezone_set('Asia/Tehran');

// ==================== بررسی احراز هویت ====================
$usersFile = __DIR__ . '/../data/users.json';

function getUserById($id) {
    global $usersFile;
    if (!file_exists($usersFile)) return null;
    $users = json_decode(file_get_contents($usersFile), true);
    if (!is_array($users)) return null;
    foreach ($users as $user) {
        if ($user['id'] == $id) {
            return $user;
        }
    }
    return null;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$currentUser = getUserById($_SESSION['user_id']);
if (!$currentUser) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

$userId = $_SESSION['user_id'];

// ==================== فایل‌های دیتا ====================
$lifeplanFile = __DIR__ . '/lifeplan_data.json';
$tasksFile = __DIR__ . '/../data/tasks.json';
$categoriesFile = __DIR__ . '/../data/categories.json';
$projectsFile = __DIR__ . '/../data/projects.json';

// ==================== توابع ====================
function getLifePlanData() {
    global $lifeplanFile;
    if (!file_exists($lifeplanFile)) return ['header' => [], 'blocks' => []];
    $data = json_decode(file_get_contents($lifeplanFile), true);
    return is_array($data) ? $data : ['header' => [], 'blocks' => []];
}

function getCategories() {
    global $categoriesFile;
    if (!file_exists($categoriesFile)) return ['کار شخصی', 'کار اداری', 'یادگیری', 'ورزش', 'خرید'];
    $cats = json_decode(file_get_contents($categoriesFile), true);
    return is_array($cats) ? $cats : ['کار شخصی', 'کار اداری', 'یادگیری', 'ورزش', 'خرید'];
}

function getUserProjects($userId) {
    global $projectsFile;
    if (!file_exists($projectsFile)) return [];
    $allProjects = json_decode(file_get_contents($projectsFile), true);
    if (!is_array($allProjects)) $allProjects = [];
    return array_values(array_filter($allProjects, function($p) use ($userId) {
        return ($p['user_id'] ?? '') == $userId;
    }));
}

// ==================== پردازش درخواست‌ها ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];
    
    // ===== تبدیل به تسک =====
    if ($action === 'convert_to_task') {
        $title = trim($_POST['title'] ?? '');
        $date = $_POST['date'] ?? date('Y-m-d');
        $time = $_POST['time'] ?? '12:00';
        $priority = $_POST['priority'] ?? 'medium';
        $category = $_POST['category'] ?? 'لایف‌پلن';
        $project = $_POST['project'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $blockId = $_POST['block_id'] ?? '';
        
        if (empty($title)) {
            $response = ['success' => false, 'message' => 'عنوان تسک الزامی است'];
            echo json_encode($response);
            exit;
        }
        
        // بارگذاری تسک‌ها
        $tasks = [];
        if (file_exists($tasksFile)) {
            $tasks = json_decode(file_get_contents($tasksFile), true);
            if (!is_array($tasks)) $tasks = [];
        }
        
        // ایجاد تسک جدید
        $newTask = [
            'id' => time() . rand(100, 999),
            'user_id' => $userId,
            'title' => htmlspecialchars($title),
            'description' => htmlspecialchars($description),
            'category' => $category,
            'project' => $project,
            'date' => $date,
            'time' => $time,
            'priority' => $priority,
            'order' => count($tasks),
            'done' => false,
            'parent_id' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'completed_at' => null,
            'source' => 'lifeplan',
            'source_id' => $blockId
        ];
        
        $tasks[] = $newTask;
        file_put_contents($tasksFile, json_encode($tasks, JSON_PRETTY_PRINT));
        
        $response = ['success' => true, 'message' => 'تسک با موفقیت به Planner اضافه شد'];
    }
    
    echo json_encode($response);
    exit;
}

$data = getLifePlanData();
$header = $data['header'] ?? [];
$blocks = $data['blocks'] ?? [];
$categories = getCategories();
$projects = getUserProjects($userId);
$currentDate = date('Y-m-d');

// جدا کردن هدرها و کارت‌ها
$headings = array_filter($blocks, function($block) {
    return ($block['type'] ?? '') === 'heading';
});

$cards = array_filter($blocks, function($block) {
    return ($block['type'] ?? '') === 'card';
});
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لایف‌پلن | برنامه‌ریزی زندگی</title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container { max-width: 1000px; margin: 0 auto; }
        
        .header {
            background: white;
            border-radius: 20px;
            padding: 20px 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            font-size: 24px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header h1 i { color: #f5576c; }
        
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .back-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .back-btn:hover { background: #5a6268; transform: scale(1.02); }
        
        .to-planner-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .to-planner-btn:hover { transform: scale(1.02); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4); }
        
        /* ===== هدر لایف‌پلن ===== */
        .lifeplan-header {
            background: linear-gradient(135deg, #2c3e50, #1a1a2e);
            border-radius: 20px;
            padding: 40px 30px;
            margin-bottom: 25px;
            text-align: center;
            color: white;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        
        .lifeplan-header h1 {
            font-size: 36px;
            font-weight: 700;
            letter-spacing: 3px;
            color: #f093fb;
        }
        
        .lifeplan-header .subtitle {
            font-size: 18px;
            color: rgba(255,255,255,0.7);
            margin-top: 5px;
        }
        
        .lifeplan-header .quote {
            font-size: 20px;
            font-style: italic;
            color: #f5576c;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        /* ===== کارت‌ها ===== */
        .section-title {
            font-size: 22px;
            font-weight: 700;
            color: white;
            margin: 30px 0 20px 0;
            padding: 10px 20px;
            background: rgba(255,255,255,0.15);
            border-radius: 15px;
            display: inline-block;
            backdrop-filter: blur(5px);
        }
        
        .blocks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .block-card {
            background: white;
            border-radius: 16px;
            padding: 22px;
            transition: all 0.3s ease;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            position: relative;
            border-right: 5px solid #f5576c;
        }
        
        .block-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .block-card .block-title {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .block-card .block-content {
            font-size: 14px;
            color: #666;
            line-height: 1.8;
            white-space: pre-wrap;
            margin-bottom: 15px;
        }
        
        .block-card .block-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }
        
        .block-card .block-actions button {
            border: none;
            padding: 6px 14px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 12px;
            font-family: inherit;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .block-card .block-actions .convert-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .block-card .block-actions .convert-btn:hover { transform: scale(1.05); }
        
        /* ===== مودال تبدیل ===== */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        
        .modal.show { display: flex; }
        
        .modal-content {
            background: white;
            border-radius: 25px;
            padding: 30px;
            max-width: 550px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-header i { color: #667eea; }
        
        .modal-body input,
        .modal-body select,
        .modal-body textarea {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 15px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .modal-body input:focus,
        .modal-body select:focus,
        .modal-body textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .modal-body textarea { resize: vertical; min-height: 80px; }
        
        .modal-footer {
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }
        
        .btn-cancel {
            background: #e9ecef;
            color: #2c3e50;
            border: none;
            padding: 12px 25px;
            border-radius: 12px;
            cursor: pointer;
            flex: 1;
            font-family: inherit;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-cancel:hover { background: #dde0e3; }
        
        .btn-save {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 12px;
            cursor: pointer;
            flex: 1;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-save:hover { transform: scale(1.02); }
        
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #1a1a2e;
            color: white;
            padding: 12px 25px;
            border-radius: 12px;
            z-index: 9999;
            opacity: 0;
            transition: all 0.4s ease;
            font-family: 'Vazirmatn', sans-serif;
        }
        
        .toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        
        .toast.success { background: #28a745; }
        .toast.error { background: #dc3545; }
        
        /* ===== ریسپانسیو ===== */
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: stretch; }
            .header-actions { flex-direction: column; }
            .header-actions button, .header-actions a { width: 100%; justify-content: center; }
            .blocks-grid { grid-template-columns: 1fr; }
            .lifeplan-header h1 { font-size: 26px; }
            .lifeplan-header .quote { font-size: 16px; }
            .modal-content { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- هدر -->
        <div class="header">
            <h1><i class="fas fa-compass"></i> لایف‌پلن</h1>
            <div class="header-actions">
                <a href="../index.php" class="back-btn"><i class="fas fa-home"></i> صفحه اصلی</a>
                <a href="../planner/index.php" class="to-planner-btn"><i class="fas fa-tasks"></i> رفتن به Planner</a>
            </div>
        </div>
        
        <!-- هدر لایف‌پلن -->
        <div class="lifeplan-header">
            <h1><?php echo htmlspecialchars($header['title'] ?? 'MY LIFE PLAN'); ?></h1>
            <div class="subtitle"><?php echo htmlspecialchars($header['subtitle'] ?? 'A LIFE OF PURPOSE, IMPACT & LEGACY'); ?></div>
            <div class="quote">"<?php echo htmlspecialchars($header['quote'] ?? 'I DON\'T FOLLOW THE PATH. I DESIGN IT.'); ?>"</div>
        </div>
        
        <!-- نمایش بلوک‌ها -->
        <?php
        $currentHeading = '';
        foreach ($blocks as $block):
            if (($block['type'] ?? '') === 'heading'):
                $currentHeading = $block['title'] ?? '';
                echo '<h2 class="section-title">' . htmlspecialchars($currentHeading) . '</h2>';
            elseif (($block['type'] ?? '') === 'card'):
        ?>
                <div class="block-card">
                    <div class="block-title"><?php echo htmlspecialchars($block['title'] ?? ''); ?></div>
                    <div class="block-content"><?php echo htmlspecialchars($block['content'] ?? ''); ?></div>
                    <div class="block-actions">
                        <button class="convert-btn" onclick="openConvertModal('<?php echo htmlspecialchars($block['id'] ?? ''); ?>', '<?php echo htmlspecialchars($block['title'] ?? ''); ?>', '<?php echo htmlspecialchars($block['content'] ?? ''); ?>')">
                            <i class="fas fa-exchange-alt"></i> تبدیل به تسک
                        </button>
                    </div>
                </div>
        <?php
            endif;
        endforeach;
        ?>
    </div>
    
    <!-- ===== مودال تبدیل به تسک ===== -->
    <div class="modal" id="convertModal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-exchange-alt"></i> تبدیل به تسک
            </div>
            <div class="modal-body">
                <input type="hidden" id="convertBlockId">
                <input type="text" id="taskTitle" placeholder="عنوان تسک" required>
                <textarea id="taskDescription" placeholder="توضیحات (اختیاری)" rows="3"></textarea>
                <input type="date" id="taskDate" value="<?php echo $currentDate; ?>">
                <input type="text" id="taskTime" value="12:00" placeholder="ساعت" class="time-input">
                <select id="taskPriority">
                    <option value="high">🔴 اولویت بالا</option>
                    <option value="medium" selected>🟡 اولویت متوسط</option>
                    <option value="low">🟢 اولویت پایین</option>
                </select>
                <select id="taskCategory">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                    <option value="لایف‌پلن" selected>لایف‌پلن</option>
                </select>
                
                <!-- ===== انتخاب پروژه ===== -->
                <select id="taskProject">
                    <option value="">بدون پروژه</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo htmlspecialchars($project['name']); ?>">
                            <?php echo htmlspecialchars($project['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeConvertModal()">انصراف</button>
                <button class="btn-save" onclick="convertToTask()">تبدیل و ثبت</button>
            </div>
        </div>
    </div>
    
    <div class="toast" id="toast"></div>
    
    <script>
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type;
            setTimeout(() => toast.classList.add('show'), 50);
            setTimeout(() => toast.classList.remove('show'), 3000);
        }
        
        function openConvertModal(blockId, title, content) {
            document.getElementById('convertBlockId').value = blockId;
            document.getElementById('taskTitle').value = title;
            document.getElementById('taskDescription').value = content || '';
            document.getElementById('convertModal').classList.add('show');
        }
        
        function closeConvertModal() {
            document.getElementById('convertModal').classList.remove('show');
        }
        
        async function convertToTask() {
            const title = document.getElementById('taskTitle').value.trim();
            if (!title) {
                showToast('لطفاً عنوان تسک را وارد کنید', 'error');
                return;
            }
            
            let formData = new FormData();
            formData.append('action', 'convert_to_task');
            formData.append('block_id', document.getElementById('convertBlockId').value);
            formData.append('title', title);
            formData.append('description', document.getElementById('taskDescription').value);
            formData.append('date', document.getElementById('taskDate').value);
            formData.append('time', document.getElementById('taskTime').value);
            formData.append('priority', document.getElementById('taskPriority').value);
            formData.append('category', document.getElementById('taskCategory').value);
            formData.append('project', document.getElementById('taskProject').value);
            
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                showToast('✅ تسک با موفقیت به Planner اضافه شد', 'success');
                closeConvertModal();
            } else {
                showToast(result.message || 'خطا در ثبت تسک', 'error');
            }
        }
        
        // ===== Event Listeners =====
        document.getElementById('convertModal').addEventListener('click', function(e) {
            if (e.target === this) closeConvertModal();
        });
        
        // اتوماتیک فرمت زمان
        document.querySelectorAll('.time-input').forEach(input => {
            input.addEventListener('input', function() {
                let value = this.value.replace(/[^0-9]/g, '');
                if (value.length >= 3) {
                    let hours = value.substring(0, 2);
                    let minutes = value.substring(2, 4);
                    if (parseInt(hours) > 23) hours = '23';
                    if (parseInt(minutes) > 59) minutes = '59';
                    this.value = hours + ':' + minutes;
                } else if (value.length === 2) {
                    this.value = value + ':';
                } else {
                    this.value = value;
                }
            });
        });
    </script>
</body>
</html>
