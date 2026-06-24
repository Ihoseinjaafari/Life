<?php
// projects/index.php - صفحه مدیریت پروژه‌ها
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
$projectsFile = __DIR__ . '/../data/projects.json';
$tasksFile = __DIR__ . '/../data/tasks.json';

if (!file_exists($projectsFile)) {
    file_put_contents($projectsFile, json_encode([]));
}
if (!file_exists($tasksFile)) {
    file_put_contents($tasksFile, json_encode([]));
}

// ==================== توابع ====================
function getUserProjects($userId) {
    global $projectsFile;
    if (!file_exists($projectsFile)) return [];
    $allProjects = json_decode(file_get_contents($projectsFile), true);
    if (!is_array($allProjects)) $allProjects = [];
    return array_values(array_filter($allProjects, function($p) use ($userId) {
        return ($p['user_id'] ?? '') == $userId;
    }));
}

function saveUserProjects($userId, $projects) {
    global $projectsFile;
    $allProjects = json_decode(file_get_contents($projectsFile), true);
    if (!is_array($allProjects)) $allProjects = [];
    $allProjects = array_values(array_filter($allProjects, function($p) use ($userId) {
        return ($p['user_id'] ?? '') != $userId;
    }));
    foreach ($projects as &$project) {
        $project['user_id'] = $userId;
    }
    $allProjects = array_merge($allProjects, $projects);
    file_put_contents($projectsFile, json_encode($allProjects, JSON_PRETTY_PRINT));
}

function getUserTasks($userId) {
    global $tasksFile;
    if (!file_exists($tasksFile)) return [];
    $allTasks = json_decode(file_get_contents($tasksFile), true);
    if (!is_array($allTasks)) $allTasks = [];
    return array_values(array_filter($allTasks, function($t) use ($userId) {
        return ($t['user_id'] ?? '') == $userId;
    }));
}

function saveAllTasks($tasks) {
    global $tasksFile;
    file_put_contents($tasksFile, json_encode($tasks, JSON_PRETTY_PRINT));
}

function addUserProject($userId, $projectName, $description = '') {
    $projects = getUserProjects($userId);
    if (in_array($projectName, array_column($projects, 'name'))) return false;
    
    $newProject = [
        'id' => time() . rand(100, 999),
        'user_id' => $userId,
        'name' => htmlspecialchars(trim($projectName)),
        'description' => htmlspecialchars(trim($description)),
        'color' => '#' . dechex(rand(0x000000, 0xFFFFFF)),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    $projects[] = $newProject;
    saveUserProjects($userId, $projects);
    return true;
}

function deleteUserProject($userId, $projectId) {
    $projects = getUserProjects($userId);
    $projectToDelete = null;
    $projectName = '';
    foreach ($projects as $p) {
        if ($p['id'] == $projectId) {
            $projectToDelete = $p;
            $projectName = $p['name'];
            break;
        }
    }
    if (!$projectToDelete) return false;
    
    $newProjects = array_values(array_filter($projects, function($p) use ($projectId) {
        return $p['id'] != $projectId;
    }));
    saveUserProjects($userId, $newProjects);
    
    // حذف پروژه از تسک‌ها
    $tasks = getUserTasks($userId);
    $changed = false;
    foreach ($tasks as &$task) {
        if (($task['project'] ?? '') === $projectName) {
            $task['project'] = '';
            $changed = true;
        }
    }
    if ($changed) {
        $allTasks = json_decode(file_get_contents($tasksFile), true);
        if (!is_array($allTasks)) $allTasks = [];
        foreach ($allTasks as &$t) {
            if (($t['user_id'] ?? '') == $userId && ($t['project'] ?? '') === $projectName) {
                $t['project'] = '';
            }
        }
        file_put_contents($tasksFile, json_encode($allTasks, JSON_PRETTY_PRINT));
    }
    return true;
}

function updateProject($userId, $projectId, $data) {
    $projects = getUserProjects($userId);
    foreach ($projects as &$p) {
        if ($p['id'] == $projectId) {
            if (isset($data['name'])) $p['name'] = htmlspecialchars(trim($data['name']));
            if (isset($data['description'])) $p['description'] = htmlspecialchars(trim($data['description']));
            if (isset($data['color'])) $p['color'] = $data['color'];
            $p['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    saveUserProjects($userId, $projects);
    return true;
}

function getProjectTasks($userId, $projectName) {
    $tasks = getUserTasks($userId);
    return array_values(array_filter($tasks, function($t) use ($projectName) {
        return ($t['project'] ?? '') == $projectName;
    }));
}

function getProjectProgress($tasks) {
    if (empty($tasks)) return ['total' => 0, 'done' => 0, 'percent' => 0];
    $total = count($tasks);
    $done = count(array_filter($tasks, function($t) {
        return $t['done'] == true;
    }));
    return ['total' => $total, 'done' => $done, 'percent' => round(($done / $total) * 100)];
}

// ==================== پردازش درخواست‌ها ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];
    
    if ($action === 'load') {
        $projects = getUserProjects($userId);
        $allTasks = getUserTasks($userId);
        foreach ($projects as &$p) {
            $tasks = getProjectTasks($userId, $p['name']);
            $progress = getProjectProgress($tasks);
            $p['_tasks_count'] = count($tasks);
            $p['_progress'] = $progress;
        }
        $response = ['success' => true, 'projects' => $projects];
    }
    elseif ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if (empty($name)) {
            $response = ['success' => false, 'message' => 'نام پروژه الزامی است'];
        } else {
            $result = addUserProject($userId, $name, $description);
            if ($result) {
                $projects = getUserProjects($userId);
                $allTasks = getUserTasks($userId);
                foreach ($projects as &$p) {
                    $tasks = getProjectTasks($userId, $p['name']);
                    $progress = getProjectProgress($tasks);
                    $p['_tasks_count'] = count($tasks);
                    $p['_progress'] = $progress;
                }
                $response = ['success' => true, 'projects' => $projects];
            } else {
                $response = ['success' => false, 'message' => 'پروژه با این نام قبلاً وجود دارد'];
            }
        }
    }
    elseif ($action === 'edit') {
        $projectId = $_POST['id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $color = $_POST['color'] ?? '';
        if (empty($projectId)) {
            $response = ['success' => false, 'message' => 'شناسه پروژه نامعتبر است'];
        } else {
            $data = [];
            if (!empty($name)) $data['name'] = $name;
            $data['description'] = $description;
            if (!empty($color)) $data['color'] = $color;
            updateProject($userId, $projectId, $data);
            $projects = getUserProjects($userId);
            $allTasks = getUserTasks($userId);
            foreach ($projects as &$p) {
                $tasks = getProjectTasks($userId, $p['name']);
                $progress = getProjectProgress($tasks);
                $p['_tasks_count'] = count($tasks);
                $p['_progress'] = $progress;
            }
            $response = ['success' => true, 'projects' => $projects];
        }
    }
    elseif ($action === 'delete') {
        $projectId = $_POST['id'] ?? '';
        if (empty($projectId)) {
            $response = ['success' => false, 'message' => 'شناسه پروژه نامعتبر است'];
        } else {
            $result = deleteUserProject($userId, $projectId);
            if ($result) {
                $projects = getUserProjects($userId);
                $allTasks = getUserTasks($userId);
                foreach ($projects as &$p) {
                    $tasks = getProjectTasks($userId, $p['name']);
                    $progress = getProjectProgress($tasks);
                    $p['_tasks_count'] = count($tasks);
                    $p['_progress'] = $progress;
                }
                $response = ['success' => true, 'projects' => $projects];
            } else {
                $response = ['success' => false, 'message' => 'خطا در حذف پروژه'];
            }
        }
    }
    
    echo json_encode($response);
    exit;
}

$projects = getUserProjects($userId);
$allTasks = getUserTasks($userId);
foreach ($projects as &$p) {
    $tasks = getProjectTasks($userId, $p['name']);
    $progress = getProjectProgress($tasks);
    $p['_tasks_count'] = count($tasks);
    $p['_progress'] = $progress;
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پروژه‌ها | مدیریت پروژه‌ها</title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #0f0c29;
            --bg-secondary: #302b63;
            --bg-card: rgba(255,255,255,0.05);
            --bg-card-hover: rgba(255,255,255,0.08);
            --bg-input: rgba(255,255,255,0.05);
            --bg-input-hover: rgba(255,255,255,0.08);
            --text-primary: #ffffff;
            --text-secondary: rgba(255,255,255,0.8);
            --text-muted: rgba(255,255,255,0.5);
            --text-light: rgba(255,255,255,0.3);
            --border-color: rgba(255,255,255,0.08);
            --modal-overlay: rgba(0,0,0,0.7);
            --shadow-color: rgba(0,0,0,0.2);
            --shadow-hover: rgba(0,0,0,0.4);
            --toast-bg: #1a1a2e;
            --empty-color: rgba(255,255,255,0.2);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh;
            padding: 20px;
            color: var(--text-primary);
        }

        .container { max-width: 1200px; margin: 0 auto; }

        /* ===== هدر ===== */
        .header {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px 30px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header h1 i { color: #667eea; }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn-back {
            background: var(--bg-input);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
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

        .btn-back:hover {
            background: var(--bg-card-hover);
            border-color: #667eea;
        }

        .btn-add-project {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-add-project:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102,126,234,0.4);
        }

        /* ===== آمار ===== */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            border: 1px solid var(--border-color);
        }

        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 13px;
            margin-top: 5px;
        }

        /* ===== پروژه‌ها ===== */
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .project-card {
            background: var(--bg-card);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            padding: 24px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .project-card:hover {
            border-color: rgba(102,126,234,0.3);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px var(--shadow-hover);
        }

        .project-color-bar {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            padding-top: 4px;
        }

        .project-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            flex: 1;
        }

        .project-actions {
            display: flex;
            gap: 6px;
        }

        .project-actions button {
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 14px;
        }

        .project-actions .edit-project-btn:hover {
            background: var(--badge-bg);
            color: #667eea;
        }

        .project-actions .delete-project-btn:hover {
            background: rgba(220,53,69,0.15);
            color: #ff6b6b;
        }

        .project-description {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
            word-break: break-word;
            min-height: 40px;
        }

        .project-description:empty::before {
            content: 'بدون توضیحات';
            color: var(--text-light);
            font-style: italic;
        }

        .project-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
        }

        .project-tasks-count {
            font-size: 13px;
            color: var(--text-muted);
        }

        .project-tasks-count i { color: #667eea; }

        .project-progress-container {
            flex: 1;
            min-width: 100px;
        }

        .project-progress-bar {
            width: 100%;
            height: 6px;
            background: var(--bg-input);
            border-radius: 10px;
            overflow: hidden;
        }

        .project-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        .project-progress-text {
            font-size: 11px;
            color: var(--text-light);
            margin-top: 4px;
            text-align: left;
        }

        .project-tasks-preview {
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
        }

        .project-task-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 0;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .project-task-item .task-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .project-task-item .task-dot.done {
            background: #28a745;
        }

        .project-task-item .task-dot.pending {
            background: #ffc107;
        }

        .project-task-item .task-title-preview {
            flex: 1;
            word-break: break-word;
        }

        .project-task-item .task-title-preview.done {
            text-decoration: line-through;
            opacity: 0.6;
        }

        .project-view-all {
            display: inline-block;
            margin-top: 8px;
            color: #667eea;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
        }

        .project-view-all:hover {
            text-decoration: underline;
        }

        /* ===== خالی ===== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--empty-color);
            grid-column: 1 / -1;
        }

        .empty-state .icon {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }

        .empty-state .title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        /* ===== مودال ===== */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: var(--modal-overlay);
            align-items: center;
            justify-content: center;
        }

        .modal.show { display: flex; }

        .modal-content {
            background: #1a1a2e;
            border: 1px solid var(--border-color);
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
            color: var(--text-primary);
            margin-bottom: 20px;
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
            border: 1px solid var(--border-color);
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            background: var(--bg-input);
            color: var(--text-primary);
            transition: all 0.3s;
        }

        .modal-body input:focus,
        .modal-body select:focus,
        .modal-body textarea:focus {
            outline: none;
            border-color: #667eea;
            background: var(--bg-input-hover);
        }

        .modal-body textarea {
            resize: vertical;
            min-height: 80px;
        }

        .modal-body .color-picker {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .modal-body .color-picker .color-option {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
        }

        .modal-body .color-picker .color-option:hover {
            transform: scale(1.1);
        }

        .modal-body .color-picker .color-option.active {
            border-color: white;
            box-shadow: 0 0 10px rgba(102,126,234,0.5);
        }

        .modal-footer {
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }

        .btn-cancel {
            background: var(--bg-input);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            padding: 12px 25px;
            border-radius: 12px;
            cursor: pointer;
            flex: 1;
            font-family: inherit;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-cancel:hover { background: var(--bg-card-hover); }

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

        /* ===== Toast ===== */
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--toast-bg);
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

        /* ===== Responsive ===== */
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: stretch; }
            .header-actions { flex-direction: column; }
            .header-actions button, .header-actions a { width: 100%; justify-content: center; }
            .projects-grid { grid-template-columns: 1fr; }
            .modal-content { padding: 20px; }
        }

        @media (max-width: 480px) {
            body { padding: 10px; }
            .project-name { font-size: 18px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- ===== هدر ===== -->
        <div class="header">
            <h1><i class="fas fa-project-diagram"></i> پروژه‌های من</h1>
            <div class="header-actions">
                <a href="../planner/index.php" class="btn-back"><i class="fas fa-tasks"></i> بازگشت به پلنر</a>
                <a href="../lifeplan/index.php" class="btn-back"><i class="fas fa-compass"></i> لایف‌پلن</a>
                <a href="../index.php" class="btn-back"><i class="fas fa-home"></i> صفحه اصلی</a>
                <button class="btn-add-project" onclick="openAddProjectModal()"><i class="fas fa-plus"></i> پروژه جدید</button>
            </div>
        </div>

        <!-- ===== آمار ===== -->
        <div class="stats" id="statsContainer">
            <div class="stat-card">
                <div class="stat-number" id="totalProjects">0</div>
                <div class="stat-label">کل پروژه‌ها</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="totalTasks">0</div>
                <div class="stat-label">کل تسک‌ها</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="completedTasks">0</div>
                <div class="stat-label">تسک‌های انجام شده</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="avgProgress">0%</div>
                <div class="stat-label">میانگین پیشرفت</div>
            </div>
        </div>

        <!-- ===== پروژه‌ها ===== -->
        <div class="projects-grid" id="projectsContainer"></div>
    </div>

    <!-- ===== مودال پروژه ===== -->
    <div class="modal" id="projectModal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-project-diagram"></i>
                <span id="projectModalTitle">پروژه جدید</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editProjectId">
                <input type="text" id="projectName" placeholder="نام پروژه..." required>
                <textarea id="projectDescription" placeholder="توضیحات پروژه (اختیاری)..." rows="3"></textarea>
                <label style="font-size: 13px; color: var(--text-muted); display: block; margin-bottom: 6px;">رنگ پروژه:</label>
                <div class="color-picker" id="colorPicker">
                    <div class="color-option active" style="background: #667eea;" data-color="#667eea"></div>
                    <div class="color-option" style="background: #f5576c;" data-color="#f5576c"></div>
                    <div class="color-option" style="background: #f093fb;" data-color="#f093fb"></div>
                    <div class="color-option" style="background: #43e97b;" data-color="#43e97b"></div>
                    <div class="color-option" style="background: #f9d423;" data-color="#f9d423"></div>
                    <div class="color-option" style="background: #ff6b6b;" data-color="#ff6b6b"></div>
                    <div class="color-option" style="background: #4ecdc4;" data-color="#4ecdc4"></div>
                    <div class="color-option" style="background: #a8a8a8;" data-color="#a8a8a8"></div>
                    <div class="color-option" style="background: #ff9ff3;" data-color="#ff9ff3"></div>
                    <div class="color-option" style="background: #54a0ff;" data-color="#54a0ff"></div>
                </div>
                <input type="hidden" id="projectColor" value="#667eea">
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeProjectModal()">انصراف</button>
                <button class="btn-save" id="saveProjectBtn">ذخیره</button>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
    let projects = [];
    let editProjectId = null;
    let selectedColor = '#667eea';

    // ===== Toast =====
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = 'toast ' + type;
        setTimeout(() => toast.classList.add('show'), 50);
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function escapeHtml(text) {
        if (!text) return '';
        let div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ===== بارگذاری پروژه‌ها =====
    async function loadProjects() {
        try {
            let formData = new FormData();
            formData.append('action', 'load');
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                projects = result.projects || [];
                renderProjects();
                updateStats();
            }
        } catch(e) {
            console.error(e);
            showToast('خطا در بارگذاری پروژه‌ها', 'error');
        }
    }

    // ===== رندر پروژه‌ها =====
    function renderProjects() {
        const container = document.getElementById('projectsContainer');
        
        if (!projects || projects.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <span class="icon"><i class="fas fa-project-diagram"></i></span>
                    <div class="title">هیچ پروژه‌ای وجود ندارد</div>
                    <div style="font-size: 14px; margin-top: 8px;">با کلیک روی دکمه "پروژه جدید" شروع کنید</div>
                </div>
            `;
            return;
        }
        
        container.innerHTML = projects.map(project => {
            const progress = project._progress || { total: 0, done: 0, percent: 0 };
            const tasks = project._tasks_count || 0;
            const color = project.color || '#667eea';
            
            // گرفتن 3 تسک اول برای پیش‌نمایش
            const previewTasks = project._tasks || [];
            
            return `
                <div class="project-card">
                    <div class="project-color-bar" style="background: ${color};"></div>
                    <div class="project-header">
                        <div class="project-name">${escapeHtml(project.name)}</div>
                        <div class="project-actions">
                            <button class="edit-project-btn" onclick="openEditProjectModal('${project.id}')" title="ویرایش پروژه">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="delete-project-btn" onclick="deleteProject('${project.id}')" title="حذف پروژه">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="project-description">${escapeHtml(project.description || '')}</div>
                    <div class="project-meta">
                        <span class="project-tasks-count"><i class="fas fa-tasks"></i> ${tasks} تسک</span>
                        <div class="project-progress-container">
                            <div class="project-progress-bar">
                                <div class="project-progress-fill" style="width: ${progress.percent}%;"></div>
                            </div>
                            <div class="project-progress-text">${progress.done} از ${progress.total} انجام شده (${progress.percent}%)</div>
                        </div>
                    </div>
                    <div class="project-tasks-preview">
                        ${previewTasks.length > 0 ? 
                            previewTasks.slice(0, 3).map(task => `
                                <div class="project-task-item">
                                    <span class="task-dot ${task.done ? 'done' : 'pending'}"></span>
                                    <span class="task-title-preview ${task.done ? 'done' : ''}">${escapeHtml(task.title)}</span>
                                </div>
                            `).join('') :
                            '<div style="color: var(--text-light); font-size: 13px;">هیچ تسکی در این پروژه وجود ندارد</div>'
                        }
                        ${tasks > 3 ? `<div class="project-view-all" onclick="window.location.href='../planner/index.php?project=${encodeURIComponent(project.name)}'">مشاهده همه ${tasks} تسک...</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
    }

    // ===== بروزرسانی آمار =====
    function updateStats() {
        const totalProjects = projects.length;
        let totalTasks = 0;
        let completedTasks = 0;
        let totalProgress = 0;
        
        projects.forEach(p => {
            const progress = p._progress || { total: 0, done: 0, percent: 0 };
            totalTasks += progress.total;
            completedTasks += progress.done;
            totalProgress += progress.percent;
        });
        
        const avgProgress = totalProjects > 0 ? Math.round(totalProgress / totalProjects) : 0;
        
        document.getElementById('totalProjects').textContent = totalProjects;
        document.getElementById('totalTasks').textContent = totalTasks;
        document.getElementById('completedTasks').textContent = completedTasks;
        document.getElementById('avgProgress').textContent = avgProgress + '%';
    }

    // ===== مدیریت رنگ =====
    function initColorPicker() {
        const container = document.getElementById('colorPicker');
        if (!container) return;
        
        container.querySelectorAll('.color-option').forEach(el => {
            el.addEventListener('click', function() {
                container.querySelectorAll('.color-option').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('projectColor').value = this.dataset.color;
                selectedColor = this.dataset.color;
            });
        });
    }

    // ===== مدیریت پروژه =====
    function openAddProjectModal() {
        editProjectId = null;
        document.getElementById('projectModalTitle').textContent = 'پروژه جدید';
        document.getElementById('editProjectId').value = '';
        document.getElementById('projectName').value = '';
        document.getElementById('projectDescription').value = '';
        document.getElementById('projectColor').value = '#667eea';
        selectedColor = '#667eea';
        document.querySelectorAll('#colorPicker .color-option').forEach(b => b.classList.remove('active'));
        document.querySelector('#colorPicker .color-option[data-color="#667eea"]')?.classList.add('active');
        document.getElementById('saveProjectBtn').textContent = 'افزودن';
        document.getElementById('projectModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('projectName').focus(), 150);
    }

    function openEditProjectModal(id) {
        const project = projects.find(p => p.id == id);
        if (!project) return;
        
        editProjectId = id;
        document.getElementById('projectModalTitle').textContent = 'ویرایش پروژه';
        document.getElementById('editProjectId').value = id;
        document.getElementById('projectName').value = project.name;
        document.getElementById('projectDescription').value = project.description || '';
        const color = project.color || '#667eea';
        document.getElementById('projectColor').value = color;
        selectedColor = color;
        document.querySelectorAll('#colorPicker .color-option').forEach(b => b.classList.remove('active'));
        document.querySelector(`#colorPicker .color-option[data-color="${color}"]`)?.classList.add('active');
        document.getElementById('saveProjectBtn').textContent = 'ذخیره تغییرات';
        document.getElementById('projectModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('projectName').focus(), 150);
    }

    function closeProjectModal() {
        document.getElementById('projectModal').classList.remove('show');
        document.body.style.overflow = '';
    }

    async function saveProject() {
        const name = document.getElementById('projectName').value.trim();
        if (!name) {
            showToast('لطفاً نام پروژه را وارد کنید', 'error');
            return;
        }
        
        const description = document.getElementById('projectDescription').value.trim();
        const color = document.getElementById('projectColor').value || '#667eea';
        const id = document.getElementById('editProjectId').value;
        const action = id ? 'edit' : 'add';
        
        const btn = document.getElementById('saveProjectBtn');
        btn.disabled = true;
        btn.textContent = 'در حال ذخیره...';
        
        try {
            let formData = new FormData();
            formData.append('action', action);
            if (id) formData.append('id', id);
            formData.append('name', name);
            formData.append('description', description);
            formData.append('color', color);
            
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                projects = result.projects;
                renderProjects();
                updateStats();
                closeProjectModal();
                showToast(id ? 'پروژه ویرایش شد' : 'پروژه اضافه شد', 'success');
            } else {
                showToast(result.message || 'خطا در ذخیره پروژه', 'error');
            }
        } catch(e) {
            showToast('خطا در ارتباط با سرور', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = id ? 'ذخیره تغییرات' : 'افزودن';
        }
    }

    async function deleteProject(id) {
        const project = projects.find(p => p.id == id);
        if (!project) return;
        if (!confirm(`آیا از حذف پروژه "${project.name}" و جدا شدن تسک‌های آن مطمئن هستید؟`)) return;
        
        try {
            let formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                projects = result.projects;
                renderProjects();
                updateStats();
                showToast('پروژه حذف شد', 'success');
            } else {
                showToast(result.message || 'خطا در حذف پروژه', 'error');
            }
        } catch(e) {
            showToast('خطا در ارتباط با سرور', 'error');
        }
    }

    // ===== Event Listeners =====
    document.getElementById('saveProjectBtn').addEventListener('click', saveProject);
    
    document.getElementById('projectModal').addEventListener('click', function(e) {
        if (e.target === this) closeProjectModal();
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeProjectModal();
    });

    // ===== شروع =====
    loadProjects();
    initColorPicker();
    </script>
</body>
</html>
