<?php
// planner/index.php - نسخه کامل با استایل یکسان صفحه اصلی و اتاق جنگ
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

// ==================== فایل‌های دیتا ====================
$dataFile = __DIR__ . '/../data/tasks.json';
$categoriesFile = __DIR__ . '/../data/categories.json';
$projectsFile = __DIR__ . '/../data/projects.json';
$usersFile = __DIR__ . '/../data/users.json';
$settingsFile = __DIR__ . '/../data/settings.json';

// ایجاد فایل‌ها در صورت عدم وجود
if (!file_exists($dataFile)) file_put_contents($dataFile, json_encode([]));
if (!file_exists($categoriesFile)) {
    file_put_contents($categoriesFile, json_encode(['کار شخصی', 'کار اداری', 'یادگیری', 'ورزش', 'خرید']));
}
if (!file_exists($projectsFile)) {
    file_put_contents($projectsFile, json_encode([]));
}
if (!file_exists($settingsFile)) {
    file_put_contents($settingsFile, json_encode(['registration_enabled' => true], JSON_PRETTY_PRINT));
}

// ==================== توابع ====================
function getAllUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return [];
    $users = json_decode(file_get_contents($usersFile), true);
    return is_array($users) ? $users : [];
}

function getAllTasks() {
    global $dataFile;
    if (!file_exists($dataFile)) return [];
    $tasks = json_decode(file_get_contents($dataFile), true);
    return is_array($tasks) ? $tasks : [];
}

function saveAllTasks($tasks) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($tasks, JSON_PRETTY_PRINT));
}

function getUserTasks($userId) {
    $tasks = getAllTasks();
    return array_values(array_filter($tasks, function($task) use ($userId) {
        return ($task['user_id'] ?? '') == $userId;
    }));
}

function getTaskChildren($taskId, $allTasks) {
    return array_values(array_filter($allTasks, function($t) use ($taskId) {
        return ($t['parent_id'] ?? '') == $taskId;
    }));
}

function calculateTaskProgress($taskId, $allTasks) {
    $children = getTaskChildren($taskId, $allTasks);
    if (empty($children)) return null;
    $total = count($children);
    $done = count(array_filter($children, function($c) {
        return $c['done'] == true;
    }));
    return ['total' => $total, 'done' => $done, 'percent' => round(($done / $total) * 100)];
}

function updateParentTaskStatus($taskId, $allTasks) {
    $task = null;
    foreach ($allTasks as $t) {
        if ($t['id'] == $taskId) { $task = $t; break; }
    }
    if (!$task || empty($task['parent_id'])) return $allTasks;
    
    $parentId = $task['parent_id'];
    $progress = calculateTaskProgress($parentId, $allTasks);
    
    if ($progress !== null) {
        foreach ($allTasks as &$t) {
            if ($t['id'] == $parentId) {
                $wasDone = $t['done'];
                $newDone = ($progress['done'] == $progress['total']);
                if ($newDone != $wasDone) {
                    $t['done'] = $newDone;
                    $t['completed_at'] = $newDone ? date('Y-m-d H:i:s') : null;
                }
                break;
            }
        }
        $allTasks = updateParentTaskStatus($parentId, $allTasks);
    }
    return $allTasks;
}

function saveUserTask($userId, $task) {
    $tasks = getAllTasks();
    $task['user_id'] = $userId;
    $tasks[] = $task;
    saveAllTasks($tasks);
    return $tasks;
}

function updateUserTask($userId, $taskId, $updatedData) {
    $tasks = getAllTasks();
    foreach ($tasks as &$task) {
        if ($task['id'] == $taskId && ($task['user_id'] ?? '') == $userId) {
            foreach ($updatedData as $key => $value) {
                $task[$key] = $value;
            }
            break;
        }
    }
    if (isset($updatedData['done'])) {
        $tasks = updateParentTaskStatus($taskId, $tasks);
    }
    saveAllTasks($tasks);
    return $tasks;
}

function deleteUserTask($userId, $taskId) {
    $tasks = getAllTasks();
    $tasks = array_values(array_filter($tasks, function($task) use ($userId, $taskId) {
        return !($task['id'] == $taskId && ($task['user_id'] ?? '') == $userId);
    }));
    saveAllTasks($tasks);
    return $tasks;
}

function deleteTaskWithChildren($userId, $taskId) {
    $tasks = getAllTasks();
    $toDelete = [$taskId];
    $found = true;
    while ($found) {
        $found = false;
        foreach ($tasks as $task) {
            if (in_array($task['parent_id'] ?? '', $toDelete) && !in_array($task['id'], $toDelete) && ($task['user_id'] ?? '') == $userId) {
                $toDelete[] = $task['id'];
                $found = true;
            }
        }
    }
    $tasks = array_values(array_filter($tasks, function($t) use ($toDelete) {
        return !in_array($t['id'], $toDelete);
    }));
    saveAllTasks($tasks);
    return $tasks;
}

function reorderUserTasks($userId, $ids) {
    $tasks = getAllTasks();
    $newTasks = [];
    foreach ($ids as $index => $id) {
        foreach ($tasks as $task) {
            if ($task['id'] == $id && ($task['user_id'] ?? '') == $userId) {
                $task['order'] = $index;
                $newTasks[] = $task;
                break;
            }
        }
    }
    foreach ($tasks as $task) {
        if (($task['user_id'] ?? '') != $userId) {
            $newTasks[] = $task;
        }
    }
    saveAllTasks($newTasks);
    return $newTasks;
}

function getCategories() {
    global $categoriesFile;
    if (!file_exists($categoriesFile)) return [];
    $cats = json_decode(file_get_contents($categoriesFile), true);
    return is_array($cats) ? $cats : [];
}

function saveCategories($categories) {
    global $categoriesFile;
    file_put_contents($categoriesFile, json_encode($categories, JSON_PRETTY_PRINT));
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

function addUserProject($userId, $projectName) {
    $projects = getUserProjects($userId);
    if (in_array($projectName, array_column($projects, 'name'))) return false;
    $newProject = [
        'name' => $projectName,
        'description' => '',
        'created_at' => date('Y-m-d H:i:s'),
        'color' => '#' . dechex(rand(0x000000, 0xFFFFFF)),
        'user_id' => $userId
    ];
    $projects[] = $newProject;
    saveUserProjects($userId, $projects);
    return true;
}

function deleteUserProject($userId, $projectName) {
    $projects = getUserProjects($userId);
    $newProjects = array_values(array_filter($projects, function($p) use ($projectName) {
        return $p['name'] != $projectName;
    }));
    saveUserProjects($userId, $newProjects);
    $tasks = getAllTasks();
    $changed = false;
    foreach ($tasks as &$task) {
        if (($task['user_id'] ?? '') == $userId && ($task['project'] ?? '') === $projectName) {
            $task['project'] = '';
            $changed = true;
        }
    }
    if ($changed) saveAllTasks($tasks);
    return true;
}

function updateUserProjectDescription($userId, $projectName, $description) {
    $projects = getUserProjects($userId);
    foreach ($projects as &$p) {
        if ($p['name'] === $projectName) {
            $p['description'] = $description;
            break;
        }
    }
    saveUserProjects($userId, $projects);
}

// ==================== پردازش درخواست‌ها ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];
    $userId = $_SESSION['user_id'];
    
    if ($action === 'logout') {
        session_destroy();
        $response = ['success' => true];
    }
    elseif ($action === 'change_password') {
        $newPassword = $_POST['new_password'] ?? '';
        if (strlen($newPassword) >= 4) {
            $users = getAllUsers();
            foreach ($users as &$user) {
                if ($user['id'] == $userId) {
                    $user['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                    break;
                }
            }
            file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
            $response = ['success' => true];
        } else {
            $response = ['success' => false, 'message' => 'رمز عبور باید حداقل ۴ کاراکتر باشد'];
        }
    }
    elseif ($action === 'add') {
        $tasks = getUserTasks($userId);
        $newId = time() . rand(100, 999);
        $newTask = [
            'id' => $newId,
            'user_id' => $userId,
            'title' => htmlspecialchars(trim($_POST['title'] ?? '')),
            'description' => htmlspecialchars(trim($_POST['description'] ?? '')),
            'category' => $_POST['category'] ?? 'بدون دسته',
            'project' => $_POST['project'] ?? '',
            'date' => $_POST['date'] ?? date('Y-m-d'),
            'time' => $_POST['time'] ?? '12:00',
            'priority' => $_POST['priority'] ?? 'medium',
            'order' => count($tasks),
            'done' => false,
            'parent_id' => $_POST['parent_id'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'completed_at' => null
        ];
        $allTasks = saveUserTask($userId, $newTask);
        if (!empty($newTask['parent_id'])) {
            $allTasks = updateParentTaskStatus($newTask['parent_id'], $allTasks);
            saveAllTasks($allTasks);
        }
        $response = ['success' => true, 'tasks' => getUserTasks($userId)];
    }
    elseif ($action === 'toggle') {
        $task = null;
        $tasksList = getAllTasks();
        foreach ($tasksList as $t) {
            if ($t['id'] == $_POST['id'] && ($t['user_id'] ?? '') == $userId) {
                $task = $t;
                break;
            }
        }
        $currentDone = $task ? $task['done'] : false;
        $newDone = !$currentDone;
        $completedAt = $newDone ? date('Y-m-d H:i:s') : null;
        $allTasks = updateUserTask($userId, $_POST['id'], [
            'done' => $newDone,
            'completed_at' => $completedAt
        ]);
        $allTasks = updateParentTaskStatus($_POST['id'], $allTasks);
        saveAllTasks($allTasks);
        $response = ['success' => true, 'tasks' => getUserTasks($userId)];
    }
    elseif ($action === 'delete') {
        $deleteChildren = isset($_POST['delete_children']) && $_POST['delete_children'] == 'true';
        if ($deleteChildren) {
            $allTasks = deleteTaskWithChildren($userId, $_POST['id']);
        } else {
            $allTasks = deleteUserTask($userId, $_POST['id']);
        }
        $response = ['success' => true, 'tasks' => getUserTasks($userId)];
    }
    elseif ($action === 'reorder') {
        $ids = json_decode($_POST['ids'] ?? '[]', true);
        $allTasks = reorderUserTasks($userId, $ids);
        $response = ['success' => true, 'tasks' => getUserTasks($userId)];
    }
    elseif ($action === 'edit') {
        $updateData = [];
        if (isset($_POST['title'])) $updateData['title'] = htmlspecialchars(trim($_POST['title']));
        if (isset($_POST['description'])) $updateData['description'] = htmlspecialchars(trim($_POST['description']));
        if (isset($_POST['category'])) $updateData['category'] = $_POST['category'];
        if (isset($_POST['project'])) $updateData['project'] = $_POST['project'];
        if (isset($_POST['date'])) $updateData['date'] = $_POST['date'];
        if (isset($_POST['time'])) $updateData['time'] = $_POST['time'];
        if (isset($_POST['priority'])) $updateData['priority'] = $_POST['priority'];
        if (isset($_POST['parent_id'])) $updateData['parent_id'] = $_POST['parent_id'];
        $allTasks = updateUserTask($userId, $_POST['id'], $updateData);
        saveAllTasks($allTasks);
        $response = ['success' => true, 'tasks' => getUserTasks($userId)];
    }
    elseif ($action === 'load') {
        $tasks = getUserTasks($userId);
        $allTasks = getAllTasks();
        foreach ($tasks as &$task) {
            $progress = calculateTaskProgress($task['id'], $allTasks);
            if ($progress !== null) {
                $task['_progress'] = $progress;
            }
        }
        $response = [
            'success' => true,
            'tasks' => $tasks,
            'categories' => getCategories(),
            'projects' => getUserProjects($userId),
            'user' => getUserById($userId)
        ];
    }
    elseif ($action === 'add_category') {
        $categories = getCategories();
        $newCategory = htmlspecialchars(trim($_POST['category'] ?? ''));
        if ($newCategory && !in_array($newCategory, $categories)) {
            $categories[] = $newCategory;
            saveCategories($categories);
        }
        $response = ['success' => true, 'categories' => $categories];
    }
    elseif ($action === 'delete_category') {
        $categoryToDelete = $_POST['category'] ?? '';
        $categories = getCategories();
        $categories = array_values(array_filter($categories, function($c) use ($categoryToDelete) {
            return $c != $categoryToDelete;
        }));
        saveCategories($categories);
        $response = ['success' => true, 'categories' => $categories];
    }
    elseif ($action === 'add_project') {
        $newProject = htmlspecialchars(trim($_POST['project'] ?? ''));
        if ($newProject) {
            $success = addUserProject($userId, $newProject);
            $response = ['success' => $success, 'projects' => getUserProjects($userId)];
            if (!$success) $response['message'] = 'این پروژه قبلاً وجود دارد';
        } else {
            $response = ['success' => false, 'message' => 'لطفاً نام پروژه را وارد کنید'];
        }
    }
    elseif ($action === 'delete_project') {
        $projectToDelete = $_POST['project'] ?? '';
        if ($projectToDelete) {
            deleteUserProject($userId, $projectToDelete);
            $response = ['success' => true, 'projects' => getUserProjects($userId)];
        } else {
            $response = ['success' => false];
        }
    }
    elseif ($action === 'update_project_description') {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        if ($name) {
            updateUserProjectDescription($userId, $name, $description);
            $response = ['success' => true, 'projects' => getUserProjects($userId)];
        } else {
            $response = ['success' => false];
        }
    }
    elseif ($action === 'export_csv') {
        $tasks = getUserTasks($userId);
        $csv = "عنوان,توضیحات,دسته‌بندی,پروژه,تاریخ,زمان,اولویت,تسک مادر,وضعیت,تاریخ انجام,پیشرفت\n";
        foreach ($tasks as $task) {
            $status = $task['done'] ? 'انجام شده' : 'در انتظار';
            $completedAt = $task['completed_at'] ? date('Y-m-d H:i', strtotime($task['completed_at'])) : '';
            $parentTitle = '';
            foreach ($tasks as $p) {
                if ($p['id'] == ($task['parent_id'] ?? '')) {
                    $parentTitle = $p['title'];
                    break;
                }
            }
            $allTasks = getAllTasks();
            $progress = calculateTaskProgress($task['id'], $allTasks);
            $progressText = $progress !== null ? "{$progress['done']}/{$progress['total']} ({$progress['percent']}%)" : '—';
            $csv .= '"' . str_replace('"', '""', $task['title']) . '",';
            $csv .= '"' . str_replace('"', '""', $task['description'] ?? '') . '",';
            $csv .= '"' . $task['category'] . '",';
            $csv .= '"' . ($task['project'] ?? '') . '",';
            $csv .= '"' . $task['date'] . '",';
            $csv .= '"' . $task['time'] . '",';
            $csv .= '"' . $task['priority'] . '",';
            $csv .= '"' . $parentTitle . '",';
            $csv .= '"' . $status . '",';
            $csv .= '"' . $completedAt . '",';
            $csv .= '"' . $progressText . "\"\n";
        }
        $response = ['success' => true, 'data' => $csv];
    }
    
    echo json_encode($response);
    exit;
}

$currentDate = date('Y-m-d');
$todayTehran = date('Y-m-d');
$tomorrowTehran = date('Y-m-d', strtotime('+1 days'));
$userId = $_SESSION['user_id'];
$currentUser = getUserById($userId);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>برنامه‌ریز شخصی | با پیشرفت خودکار</title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <style>
        /* ===== استایل یکسان با صفحه اصلی و اتاق جنگ ===== */
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
            --border-card: rgba(255,255,255,0.06);
            --shadow-color: rgba(0,0,0,0.2);
            --shadow-hover: rgba(0,0,0,0.4);
            --modal-overlay: rgba(0,0,0,0.7);
            --toast-bg: #1a1a2e;
            --badge-bg: rgba(102,126,234,0.15);
            --badge-color: #667eea;
            --done-bg: rgba(40,167,69,0.1);
            --done-border: rgba(40,167,69,0.3);
            --progress-bg: rgba(255,255,255,0.05);
            --progress-fill: linear-gradient(90deg, #667eea, #764ba2);
            --completed-date: #28a745;
            --empty-color: rgba(255,255,255,0.2);
            --user-info-bg: rgba(255,255,255,0.03);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body, html, div, input, select, button, textarea, span, p, h1, h2, h3, h4, h5, h6, a, li, label, option, .modal, .modal-content, .task-card, .task-item-list, .navbar, .nav-btn, .manage-btn, .stat-card, .date-header, .card-btn, .edit-btn-list, .delete-btn-list, .project-link, .btn-cancel, .btn-save, .auth-btn, .logout-btn, .clear-filters, .add-task-fab {
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            transition: background 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }
        
        body {
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh;
            padding: 20px;
            color: var(--text-primary);
        }

        .theme-toggle-btn {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .theme-toggle-btn:hover {
            background: rgba(255,255,255,0.25);
            transform: scale(1.05);
        }

        .time-input {
            direction: ltr;
            text-align: center;
            font-family: monospace !important;
            font-size: 14px;
            letter-spacing: 2px;
        }
        
        .container { max-width: 1400px; margin: 0 auto; }
        
        .main-app { display: block; }
        
        .user-info { display: none; }
        
        .navbar {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 12px 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px var(--shadow-color);
            border: 1px solid var(--border-color);
        }
        
        .navbar-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .logo {
            font-size: 20px;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .desktop-menu {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .nav-btn {
            padding: 6px 14px;
            cursor: pointer;
            border-radius: 20px;
            border: none;
            background: rgba(255,255,255,0.03);
            font-size: 13px;
            transition: all 0.3s;
            color: var(--text-muted);
        }
        
        .nav-btn:hover { background: rgba(255,255,255,0.08); color: var(--text-primary); }
        .nav-btn.active { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        
        .manage-btn {
            padding: 6px 14px;
            color: var(--text-secondary);
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
            font-weight: 500;
            background: rgba(255,255,255,0.03);
        }
        
        .manage-btn:hover { background: rgba(255,255,255,0.08); transform: scale(1.05); }
        
        .add-task-fab {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
            z-index: 998;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .add-task-fab:hover { transform: scale(1.1); }
        
        .view-toggle-global {
            background: var(--bg-card);
            border-radius: 15px;
            padding: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
            box-shadow: 0 2px 10px var(--shadow-color);
            border: 1px solid var(--border-color);
        }
        
        .view-btn-global {
            padding: 8px 25px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            background: rgba(255,255,255,0.03);
            color: var(--text-muted);
        }
        
        .view-btn-global.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .filters-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px var(--shadow-color);
            border: 1px solid var(--border-color);
            display: none;
        }
        
        .filter-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .date-range-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .date-range-input {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 13px;
            cursor: pointer;
            background: rgba(255,255,255,0.03);
            color: var(--text-primary);
        }
        
        .filter-select {
            padding: 8px 15px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            background: rgba(255,255,255,0.03);
            color: var(--text-primary);
        }
        
        .clear-filters {
            background: rgba(220,53,69,0.2);
            color: #ff6b6b;
            border: none;
            padding: 8px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .clear-filters:hover { background: rgba(220,53,69,0.3); }
        
        .apply-date-range {
            background: rgba(40,167,69,0.2);
            color: #28a745;
            border: none;
            padding: 8px 15px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13px;
        }
        
        .apply-date-range:hover { background: rgba(40,167,69,0.3); }
        
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
            box-shadow: 0 5px 15px var(--shadow-color);
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
        
        .tasks-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 5px 20px var(--shadow-color);
            border: 1px solid var(--border-color);
        }
        
        .drag-info {
            background: var(--badge-bg);
            color: var(--badge-color);
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        .date-group { margin-bottom: 30px; }
        
        .date-header {
            background: linear-gradient(135deg, rgba(102,126,234,0.15), rgba(118,75,162,0.15));
            color: var(--text-primary);
            padding: 12px 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border-color);
        }
        
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
        }
        
        .task-card {
            background: rgba(255,255,255,0.03);
            border-radius: 16px;
            padding: 16px 18px;
            box-shadow: 0 2px 12px var(--shadow-color);
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
            position: relative;
            cursor: grab;
            display: flex;
            flex-direction: column;
        }
        
        .task-card:active { cursor: grabbing; }
        .task-card.dragging { opacity: 0.3; }
        .task-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px var(--shadow-hover);
            border-color: #667eea;
        }
        
        .task-card.completed { 
            opacity: 0.75; 
            background: var(--done-bg); 
            border-color: var(--done-border);
        }
        .task-card.completed .task-title-text { text-decoration: line-through; }
        
        .card-drag-handle {
            position: absolute;
            top: 16px;
            left: 16px;
            cursor: grab;
            color: var(--text-light);
            font-size: 16px;
            z-index: 2;
        }
        
        .card-check {
            position: absolute;
            top: 16px;
            left: 44px;
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #667eea;
            z-index: 2;
        }
        
        .card-content {
            flex: 1;
            padding-top: 6px;
            padding-right: 10px;
        }
        
        .task-header-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 8px;
        }
        
        .task-title-text {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.5;
            flex: 1;
            word-break: break-word;
            padding-right: 70px;
        }
        
        .task-title-text .task-link { color: var(--text-primary); text-decoration: none; }
        .task-title-text .task-link:hover { color: #667eea; }
        .task-title-text.completed { text-decoration: line-through; opacity: 0.6; }
        
        .subtask-badge {
            font-size: 11px;
            background: var(--badge-bg);
            color: var(--badge-color);
            padding: 2px 8px;
            border-radius: 12px;
            white-space: nowrap;
            display: inline-block;
            margin-right: 5px;
        }
        
        .task-description {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
            margin: 8px 0 10px 0;
            padding: 8px 0;
            border-top: 1px dashed var(--border-color);
            border-bottom: 1px dashed var(--border-color);
            word-break: break-word;
            padding-right: 70px;
        }
        
        .task-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 8px;
            padding-right: 70px;
        }
        
        .task-meta > span {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .time-badge { background: rgba(102,126,234,0.15); color: #667eea; }
        .category-badge { background: rgba(245,87,108,0.15); color: #f5576c; }
        .project-badge { background: rgba(67,233,123,0.15); color: #43e97b; }
        .priority-high { background: rgba(220,53,69,0.2); color: #ff6b6b; }
        .priority-medium { background: rgba(255,193,7,0.2); color: #ffc107; }
        .priority-low { background: rgba(40,167,69,0.2); color: #28a745; }
        
        .completed-date {
            font-size: 11px;
            color: var(--completed-date);
            margin: 5px 0;
            font-weight: 500;
            padding-right: 70px;
        }
        
        .progress-container {
            margin: 8px 0;
            background: var(--progress-bg);
            border-radius: 20px;
            height: 4px;
            overflow: hidden;
            padding-right: 70px;
        }
        
        .progress-bar {
            height: 100%;
            background: var(--progress-fill);
            border-radius: 20px;
            transition: width 0.5s ease;
        }
        
        .progress-text {
            font-size: 11px;
            color: var(--text-light);
            font-weight: 600;
            margin-top: 3px;
            display: flex;
            justify-content: space-between;
            padding-right: 70px;
        }
        
        .subtasks-container {
            margin-top: 10px;
            padding: 10px 14px;
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            border-right: 3px solid #667eea;
            margin-right: 70px;
        }
        
        .subtask-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .subtask-item:last-child { border-bottom: none; }
        
        .subtask-check {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #667eea;
            flex-shrink: 0;
        }
        
        .subtask-title-text {
            flex: 1;
            font-size: 13px;
            word-break: break-word;
            color: var(--text-secondary);
        }
        
        .subtask-title-text.completed {
            text-decoration: line-through;
            opacity: 0.6;
        }
        
        .subtask-actions button {
            background: none;
            border: none;
            cursor: pointer;
            padding: 2px 5px;
            font-size: 12px;
            border-radius: 4px;
            color: var(--text-light);
        }
        
        .subtask-actions button:hover { background: var(--border-color); }
        
        .card-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
            padding-right: 70px;
        }
        
        .card-btn {
            background: none;
            border: none;
            font-size: 12px;
            cursor: pointer;
            padding: 5px 12px;
            border-radius: 8px;
            transition: all 0.2s;
            color: var(--text-secondary);
        }
        
        .edit-card-btn:hover { background: var(--badge-bg); color: var(--badge-color); }
        .delete-card-btn:hover { background: rgba(220,53,69,0.15); color: #ff6b6b; }
        .subtask-btn { 
            background: none; 
            border: none; 
            font-size: 12px; 
            cursor: pointer; 
            padding: 5px 12px; 
            border-radius: 8px; 
            color: #667eea; 
        }
        .subtask-btn:hover { background: var(--badge-bg); }
        
        .list-view-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .task-item-list {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 12px 16px;
            transition: all 0.3s;
            cursor: grab;
            flex-wrap: wrap;
        }
        
        .task-item-list:active { cursor: grabbing; }
        .task-item-list.dragging { opacity: 0.3; }
        .task-item-list:hover { border-color: #667eea; }
        .task-item-list.completed .task-title-list { text-decoration: line-through; opacity: 0.6; }
        
        .drag-handle-list { cursor: grab; color: var(--text-light); font-size: 16px; flex-shrink: 0; }
        .task-check-list { width: 18px; height: 18px; cursor: pointer; accent-color: #667eea; flex-shrink: 0; }
        
        .task-content-list {
            flex: 1;
            min-width: 150px;
        }
        
        .task-title-list {
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            color: var(--text-primary);
        }
        
        .task-title-list .task-link { color: var(--text-primary); text-decoration: none; }
        .task-title-list .task-link:hover { color: #667eea; }
        
        .task-meta-list {
            display: flex;
            gap: 6px;
            font-size: 11px;
            flex-wrap: wrap;
            margin-top: 4px;
        }
        
        .task-meta-list > span {
            padding: 2px 8px;
            border-radius: 10px;
        }
        
        .task-actions-list {
            display: flex;
            gap: 5px;
            flex-shrink: 0;
        }
        
        .edit-btn-list, .delete-btn-list {
            background: none;
            border: none;
            font-size: 14px;
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 6px;
            color: var(--text-light);
        }
        
        .edit-btn-list:hover { background: var(--badge-bg); color: var(--badge-color); }
        .delete-btn-list:hover { background: rgba(220,53,69,0.15); color: #ff6b6b; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--empty-color);
            grid-column: 1 / -1;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: var(--modal-overlay);
        }
        
        .modal-content {
            background: #1a1a2e;
            border: 1px solid rgba(255,255,255,0.08);
            margin: 5% auto;
            width: 90%;
            max-width: 500px;
            border-radius: 25px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px 25px;
            font-weight: 600;
            font-size: 18px;
            position: sticky;
            top: 0;
            border-radius: 25px 25px 0 0;
        }
        
        .modal-body { padding: 25px; }
        
        .modal-body input, .modal-body select, .modal-body textarea {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 18px;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            font-size: 14px;
            background: rgba(255,255,255,0.03);
            color: var(--text-primary);
        }
        
        .modal-body input:focus, .modal-body select:focus, .modal-body textarea:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255,255,255,0.06);
        }
        
        .modal-body textarea { resize: vertical; min-height: 100px; }
        
        .modal-footer {
            padding: 20px 25px 25px;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .btn-cancel {
            background: rgba(255,255,255,0.05);
            color: var(--text-secondary);
            padding: 12px 25px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            cursor: pointer;
            flex: 1;
            font-family: inherit;
        }
        
        .btn-cancel:hover { background: rgba(255,255,255,0.1); }
        
        .btn-save {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            flex: 1;
            font-family: inherit;
            font-weight: 600;
        }
        
        .btn-save:hover { transform: scale(1.02); }
        
        .project-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .project-link {
            cursor: pointer;
            text-decoration: none;
            color: var(--text-primary);
            flex: 1;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .project-link:hover { color: #2d6a4f; }
        
        .project-stat-card {
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        
        .profile-info {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            color: white;
        }
        
        .profile-email { color: var(--text-muted); font-size: 14px; margin-top: 5px; }
        
        .project-task-item {
            background: rgba(255,255,255,0.03);
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .project-task-item:hover {
            background: rgba(255,255,255,0.06);
            border-color: #667eea;
            transform: translateX(5px);
        }
        
        .project-task-item .task-link {
            flex: 1;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            min-width: 150px;
        }
        
        .project-task-item .task-link:hover .task-title { color: #667eea; }
        .project-task-item .task-title { font-weight: bold; transition: color 0.3s; margin-bottom: 4px; }
        .project-task-item .task-title.done { text-decoration: line-through; opacity: 0.6; color: var(--text-muted); }
        .project-task-item .task-meta { font-size: 11px; color: var(--text-light); display: flex; flex-wrap: wrap; gap: 5px; }
        .project-task-item .task-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .project-task-item .view-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }
        .project-task-item .view-btn:hover { background: #764ba2; transform: scale(1.05); }
        
        /* ===== منوی کشویی پروفایل ===== */
        .profile-dropdown {
            position: relative;
            display: inline-block;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 4px 10px 4px 4px;
            border-radius: 30px;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border-color);
        }

        .profile-trigger:hover {
            background: rgba(255,255,255,0.08);
            border-color: #667eea;
        }

        .profile-trigger .user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
            flex-shrink: 0;
        }

        .profile-trigger .user-name {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-primary);
            white-space: nowrap;
        }

        .dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 180px;
            background: #1a1a2e;
            border-radius: 14px;
            box-shadow: 0 10px 40px var(--shadow-color);
            border: 1px solid var(--border-color);
            padding: 6px 0;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px) scale(0.96);
            transition: all 0.25s ease;
            z-index: 1000;
            transform-origin: top right;
        }

        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 18px;
            width: 100%;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 13px;
            color: var(--text-secondary);
            font-family: inherit;
            transition: all 0.2s ease;
            text-align: right;
        }

        .dropdown-item:hover {
            background: rgba(255,255,255,0.03);
            color: var(--text-primary);
        }

        .dropdown-item i {
            width: 18px;
            font-size: 14px;
            color: #667eea;
        }

        .dropdown-item.logout-item {
            border-top: 1px solid var(--border-color);
            margin-top: 4px;
            padding-top: 10px;
        }

        .dropdown-item.logout-item i {
            color: #dc3545;
        }

        .dropdown-item.logout-item:hover {
            background: rgba(220,53,69,0.15);
            color: #ff6b6b;
        }

        /* ===== منو موبایل ===== */
        .hamburger {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: 10px;
            z-index: 1001;
        }
        
        .hamburger span {
            display: block;
            width: 25px;
            height: 3px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            margin: 5px 0;
            transition: all 0.3s ease;
            border-radius: 3px;
        }
        
        .hamburger.active span:nth-child(1) { transform: rotate(45deg) translate(5px, 5px); }
        .hamburger.active span:nth-child(2) { opacity: 0; }
        .hamburger.active span:nth-child(3) { transform: rotate(-45deg) translate(7px, -7px); }
        
        .mobile-menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--modal-overlay);
            z-index: 999;
            display: none;
        }
        .mobile-menu-overlay.active { display: block; }
        
        .mobile-menu {
            position: fixed;
            top: 0;
            right: -320px;
            width: 320px;
            height: 100vh;
            background: #1a1a2e;
            z-index: 1000;
            padding: 80px 20px 30px;
            transition: right 0.3s ease;
            box-shadow: -5px 0 30px var(--shadow-color);
            overflow-y: auto;
            border-left: 1px solid var(--border-color);
        }
        .mobile-menu.open { right: 0; }
        
        .user-info-mobile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: var(--user-info-bg);
            border-radius: 15px;
            margin-bottom: 20px;
        }
        
        .user-avatar-mobile {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .user-info-mobile .user-details .name {
            font-weight: 600;
            font-size: 15px;
            color: var(--text-primary);
        }
        
        .user-info-mobile .user-details .email {
            font-size: 12px;
            color: var(--text-light);
        }
        
        .menu-section { margin-bottom: 20px; }
        
        .menu-section-title {
            font-size: 12px;
            color: var(--text-light);
            font-weight: 700;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .menu-section-title i { color: #667eea; }
        
        .menu-section-buttons {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .menu-section-buttons button,
        .menu-section-buttons a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            border: none;
            border-radius: 12px;
            background: rgba(255,255,255,0.03);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            color: var(--text-secondary);
            width: 100%;
            font-family: inherit;
        }
        
        .menu-section-buttons button:hover,
        .menu-section-buttons a:hover {
            background: rgba(255,255,255,0.06);
            transform: translateX(-5px);
        }
        
        .menu-section-buttons .active-mobile {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .toast {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--toast-bg);
            color: white;
            padding: 12px 24px;
            border-radius: 14px;
            font-size: 14px;
            z-index: 2000;
            opacity: 0;
            transition: all 0.4s ease;
            font-family: 'Vazirmatn', sans-serif;
            white-space: nowrap;
            box-shadow: 0 4px 20px var(--shadow-color);
        }
        
        .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .toast.success { background: #28a745; }
        .toast.error { background: #dc3545; }
        
        @media (max-width: 768px) {
            .desktop-menu { display: none; }
            .hamburger { display: block; }
            .navbar-top { flex-direction: row; }
            .filter-group { flex-direction: column; width: 100%; }
            .filter-select, .clear-filters { width: 100%; }
            .date-range-group { width: 100%; }
            .date-range-input { flex: 1; }
            .add-task-fab {
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                font-size: 24px;
            }
            .modal-content { width: 95%; margin: 10% auto; }
            .cards-grid { grid-template-columns: 1fr; }
            .date-header { flex-direction: column; text-align: center; }
            .view-btn-global { padding: 6px 15px; font-size: 12px; }
            
            .task-card { padding: 14px; }
            .task-title-text { 
                font-size: 14px; 
                padding-right: 65px;
            }
            .task-description { padding-right: 65px; }
            .task-meta { padding-right: 65px; }
            .card-actions { padding-right: 65px; }
            .progress-container { padding-right: 65px; }
            .progress-text { padding-right: 65px; }
            .subtasks-container { margin-right: 65px; }
            .completed-date { padding-right: 65px; }
            
            .card-check { 
                top: 14px;
                left: 44px;
                width: 18px;
                height: 18px;
            }
            .card-drag-handle { 
                top: 14px;
                left: 14px;
                font-size: 14px;
            }
            
            .task-item-list { padding: 10px 12px; }
            .task-content-list { min-width: 100%; }
            .task-actions-list { width: 100%; justify-content: flex-end; }
        }
        
        @media (max-width: 480px) {
            body { padding: 10px; }
            .task-card { padding: 12px; }
            .task-title-text { 
                font-size: 13px; 
                padding-right: 60px;
            }
            .task-description { 
                font-size: 12px; 
                padding-right: 60px;
            }
            .task-meta { padding-right: 60px; }
            .task-meta > span { font-size: 10px; padding: 2px 6px; }
            .card-actions { padding-right: 60px; }
            .card-btn { font-size: 11px; padding: 4px 8px; }
            .progress-container { padding-right: 60px; }
            .progress-text { padding-right: 60px; font-size: 10px; }
            .subtasks-container { 
                margin-right: 60px; 
                padding: 8px 10px;
            }
            .subtask-title-text { font-size: 12px; }
            .completed-date { 
                padding-right: 60px;
                font-size: 10px;
            }
            
            .card-check { 
                top: 12px;
                left: 38px;
                width: 16px;
                height: 16px;
            }
            .card-drag-handle { 
                top: 12px;
                left: 12px;
                font-size: 12px;
            }
        }
        
        @media (min-width: 769px) {
            .mobile-menu, .mobile-menu-overlay { display: none !important; }
        }
    </style>
</head>
<body>
    <input type="hidden" id="serverToday" value="<?php echo $todayTehran; ?>">
    <input type="hidden" id="serverTomorrow" value="<?php echo $tomorrowTehran; ?>">
    
    <div id="mainApp" class="main-app">
        <div class="container">
            <div class="navbar">
                <div class="navbar-top">
                    <div class="logo"><i class="fas fa-tasks"></i> برنامه‌ریز</div>
                    <button class="hamburger" id="hamburgerBtn">
                        <span></span><span></span><span></span>
                    </button>
                    
                    <div class="desktop-menu">
                        <div class="menu-group">
                            <span class="menu-group-label"><i class="fas fa-filter"></i></span>
                            <button class="nav-btn" data-filter="today">📅 امروز</button>
                            <button class="nav-btn" data-filter="tomorrow">⭐ فردا</button>
                            <button class="nav-btn" data-filter="upcoming">📆 آینده</button>
                            <button class="nav-btn" data-filter="past">⏪ گذشته</button>
                            <button class="nav-btn" data-filter="completed">✅ انجام</button>
                            <button class="nav-btn" data-filter="all">📋 همه</button>
                        </div>
                        
                        <div class="menu-group">
                            <span class="menu-group-label"><i class="fas fa-tools"></i></span>
                            <button class="manage-btn project-btn" onclick="openProjectModal()" style="background: rgba(45,106,79,0.2); color:#2d6a4f;">
                                <i class="fas fa-project-diagram"></i> پروژه‌ها
                            </button>
                            <button class="manage-btn" onclick="openCategoryModal()" style="background: rgba(111,66,193,0.2); color:#8b5cf6;">
                                <i class="fas fa-tags"></i> دسته‌ها
                            </button>
                            <button class="manage-btn" onclick="location.href='habits.php'" style="background: rgba(245,158,11,0.2); color:#f59e0b;">
                                <i class="fas fa-fire"></i> عادت‌ها
                            </button>
                        </div>
                        
                        <div class="menu-group">
                            <span class="menu-group-label"><i class="fas fa-export"></i></span>
                            <button class="manage-btn" id="exportCsvBtn" style="background: rgba(40,167,69,0.2); color:#28a745;">
                                <i class="fas fa-file-csv"></i> CSV
                            </button>
                            
                            <?php if ($currentUser && $currentUser['email'] === 'admin@example.com'): ?>
                                <a href="admin.php" class="manage-btn" style="background: rgba(220,53,69,0.2); color:#ff6b6b; text-decoration: none;">
                                    <i class="fas fa-shield-alt"></i> مدیریت
                                </a>
                            <?php endif; ?>
                            
                            <a href="../index.php" class="manage-btn" style="background: linear-gradient(135deg, #667eea, #764ba2); color:white; text-decoration: none;">
                                <i class="fas fa-home"></i> صفحه اصلی
                            </a>
                        </div>
                        
                        <div class="menu-group">
                            <span class="menu-group-label"><i class="fas fa-user"></i></span>
                            <div class="profile-dropdown">
                                <div class="profile-trigger" id="profileTrigger">
                                    <div class="user-avatar" style="background: <?php echo $currentUser['avatar_color'] ?? '#667eea'; ?>">
                                        <?php echo mb_substr($currentUser['name'], 0, 1); ?>
                                    </div>
                                    <span class="user-name"><?php echo htmlspecialchars($currentUser['name']); ?></span>
                                    <i class="fas fa-chevron-down" style="font-size: 10px; color: var(--text-light); margin-right: 4px;"></i>
                                </div>
                                <div class="dropdown-menu" id="profileDropdown">
                                    <button class="dropdown-item" onclick="openProfileModal()">
                                        <i class="fas fa-key"></i> تغییر رمز عبور
                                    </button>
                                    <button class="dropdown-item logout-item" onclick="logout()">
                                        <i class="fas fa-sign-out-alt"></i> خروج
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="menu-group">
                            <button class="theme-toggle-btn" onclick="toggleTheme()" title="تغییر تم">
                                <i class="fas fa-moon"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>
            <div class="mobile-menu" id="mobileMenu">
                <div class="user-info-mobile">
                    <div class="user-avatar-mobile" id="mobileUserAvatar" style="background: <?php echo $currentUser['avatar_color'] ?? '#667eea'; ?>">
                        <?php echo mb_substr($currentUser['name'], 0, 1); ?>
                    </div>
                    <div class="user-details">
                        <div class="name" id="mobileUserName"><?php echo htmlspecialchars($currentUser['name']); ?></div>
                        <div class="email" id="mobileUserEmail"><?php echo htmlspecialchars($currentUser['email']); ?></div>
                    </div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px;">
                    <button onclick="openProfileModal(); closeMobileMenu();" style="width: 100%; padding: 10px; border: none; border-radius: 10px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; font-size: 14px; font-weight: 500; cursor: pointer; font-family: inherit;">
                        <i class="fas fa-user-circle"></i> پروفایل
                    </button>
                    <button onclick="logout(); closeMobileMenu();" style="width: 100%; padding: 10px; border: none; border-radius: 10px; background: rgba(220,53,69,0.2); color:#ff6b6b; font-size: 14px; font-weight: 500; cursor: pointer; font-family: inherit;">
                        <i class="fas fa-sign-out-alt"></i> خروج
                    </button>
                    <button onclick="toggleTheme(); closeMobileMenu();" style="width: 100%; padding: 10px; border: none; border-radius: 10px; background: rgba(255,255,255,0.05); color: var(--text-secondary); font-size: 14px; font-weight: 500; cursor: pointer; font-family: inherit; border: 1px solid var(--border-color);">
                        <i class="fas fa-moon"></i> تغییر تم
                    </button>
                    <a href="../index.php" style="width: 100%; padding: 10px; border: none; border-radius: 10px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; font-size: 14px; font-weight: 500; cursor: pointer; font-family: inherit; text-decoration: none; text-align: center;">
                        <i class="fas fa-home"></i> صفحه اصلی
                    </a>
                </div>
                
                <div class="menu-section">
                    <div class="menu-section-title"><i class="fas fa-filter"></i> فیلترها</div>
                    <div class="menu-section-buttons">
                        <button class="nav-btn-mobile" data-filter="today"><i class="fas fa-calendar-day"></i> امروز</button>
                        <button class="nav-btn-mobile" data-filter="tomorrow"><i class="fas fa-calendar-plus"></i> فردا</button>
                        <button class="nav-btn-mobile" data-filter="upcoming"><i class="fas fa-calendar-week"></i> آینده</button>
                        <button class="nav-btn-mobile" data-filter="past"><i class="fas fa-calendar-minus"></i> گذشته</button>
                        <button class="nav-btn-mobile" data-filter="completed"><i class="fas fa-check-circle"></i> انجام شده</button>
                        <button class="nav-btn-mobile" data-filter="all"><i class="fas fa-list"></i> همه</button>
                    </div>
                </div>
                
                <div class="menu-section">
                    <div class="menu-section-title"><i class="fas fa-tools"></i> مدیریت</div>
                    <div class="menu-section-buttons">
                        <button onclick="openProjectModal(); closeMobileMenu();" style="background: rgba(45,106,79,0.2); color:#2d6a4f;">
                            <i class="fas fa-project-diagram"></i> پروژه‌ها
                        </button>
                        <button onclick="openCategoryModal(); closeMobileMenu();" style="background: rgba(111,66,193,0.2); color:#8b5cf6;">
                            <i class="fas fa-tags"></i> دسته‌بندی
                        </button>
                        <button onclick="location.href='habits.php'; closeMobileMenu();" style="background: rgba(245,158,11,0.2); color:#f59e0b;">
                            <i class="fas fa-fire"></i> عادت‌ها
                        </button>
                    </div>
                </div>
                
                <div class="menu-section">
                    <div class="menu-section-title"><i class="fas fa-export"></i> خروجی</div>
                    <div class="menu-section-buttons">
                        <button onclick="exportCSV(); closeMobileMenu();" style="background: rgba(40,167,69,0.2); color:#28a745;">
                            <i class="fas fa-file-csv"></i> خروجی CSV
                        </button>
                        <?php if ($currentUser && $currentUser['email'] === 'admin@example.com'): ?>
                            <button onclick="location.href='admin.php'; closeMobileMenu();" style="background: rgba(220,53,69,0.2); color:#ff6b6b;">
                                <i class="fas fa-shield-alt"></i> پنل مدیریت
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="filters-card" id="filtersCard">
                <div class="filter-group">
                    <div class="date-range-group">
                        <input type="date" id="filterDateFrom" class="date-range-input" placeholder="از تاریخ">
                        <span>تا</span>
                        <input type="date" id="filterDateTo" class="date-range-input" placeholder="تا تاریخ">
                        <button class="apply-date-range" id="applyDateRangeBtn">اعمال بازه</button>
                    </div>
                    <select id="filterPriority" class="filter-select">
                        <option value="">همه اولویت‌ها</option>
                        <option value="high">🔴 بالا</option>
                        <option value="medium">🟡 متوسط</option>
                        <option value="low">🟢 پایین</option>
                    </select>
                    <select id="filterCategory" class="filter-select"><option value="">همه دسته‌بندی‌ها</option></select>
                    <select id="filterProject" class="filter-select"><option value="">همه پروژه‌ها</option></select>
                    <button class="clear-filters" onclick="clearFilters()">پاک کردن فیلترها</button>
                </div>
            </div>
            
            <div class="stats" id="stats"></div>
            
            <div class="tasks-card">
                <div class="view-toggle-global">
                    <button class="view-btn-global" id="gridViewBtn">
                        <i class="fas fa-th-large"></i> نمایش کارتی
                    </button>
                    <button class="view-btn-global" id="listViewBtn">
                        <i class="fas fa-list"></i> نمایش لیستی
                    </button>
                </div>
                
                <div class="drag-info">
                    <i class="fas fa-arrows-alt"></i> برای تغییر اولویت، کارها را با ماوس بکشید و جابجا کنید
                </div>
                <div id="tasksList"></div>
            </div>
        </div>
        
        <button class="add-task-fab" id="openAddTaskBtn">
            <i class="fas fa-plus"></i>
        </button>
    </div>
    
    <!-- مودال‌ها -->
    <div id="addTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><i class="fas fa-plus-circle"></i> افزودن کار جدید</div>
            <div class="modal-body">
                <input type="text" id="addTitle" placeholder="عنوان کار..." required>
                <select id="addCategory"></select>
                <select id="addProject"><option value="">بدون پروژه</option></select>
                <input type="date" id="addDate" value="<?php echo $currentDate; ?>">
                <input type="text" id="addTime" class="time-input" placeholder="ساعت" value="12:00" maxlength="5" autocomplete="off">
                <select id="addPriority">
                    <option value="high">🔴 اولویت بالا</option>
                    <option value="medium" selected>🟡 اولویت متوسط</option>
                    <option value="low">🟢 اولویت پایین</option>
                </select>
                <textarea id="addDescription" placeholder="توضیحات (اختیاری)..." rows="3"></textarea>
                
                <div style="margin-top: 10px; padding: 10px; background: rgba(255,255,255,0.03); border-radius: 10px; border: 1px solid var(--border-color);">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">زیرتسک برای کار مادر</label>
                    <select id="addParentTask">
                        <option value="">بدون والد (تسک اصلی)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddTaskModal()">انصراف</button>
                <button class="btn-save" id="saveAddTaskBtn">افزودن کار</button>
            </div>
        </div>
    </div>
    
    <div id="projectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><i class="fas fa-project-diagram"></i> مدیریت پروژه‌ها</div>
            <div class="modal-body">
                <div id="projectList"></div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <input type="text" id="newProjectName" placeholder="نام پروژه جدید" style="flex:1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 12px; font-size: 14px; background: rgba(255,255,255,0.03); color: var(--text-primary);">
                    <button class="btn-save" id="addProjectBtn" style="flex:0;">افزودن</button>
                </div>
                <div style="margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.03); border-radius: 10px; font-size: 12px; color: var(--text-light); border: 1px solid var(--border-color);">
                    <i class="fas fa-info-circle"></i> برای مشاهده صفحه اختصاصی هر پروژه، روی نام آن کلیک کنید
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeProjectModal()">بستن</button>
            </div>
        </div>
    </div>
    
    <div id="projectPageModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header"><i class="fas fa-project-diagram"></i> <span id="projectPageTitle"></span></div>
            <div class="modal-body">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">توضیحات پروژه:</label>
                    <div id="projectPageDesc" style="background: rgba(255,255,255,0.03); padding: 15px; border-radius: 12px; min-height: 80px; white-space: pre-wrap; border: 1px solid var(--border-color); color: var(--text-secondary);"></div>
                    <button id="editProjectDescBtn" class="manage-btn" style="margin-top: 10px; background: rgba(102,126,234,0.2); color:#667eea; border: 1px solid rgba(102,126,234,0.15);"><i class="fas fa-edit"></i> ویرایش توضیحات</button>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">آمار پروژه:</label>
                    <div id="projectPageStats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px;"></div>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">تسک‌های پروژه:</label>
                    <div id="projectPageTasks" style="max-height: 350px; overflow-y: auto;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeProjectPageModal()">بستن</button>
            </div>
        </div>
    </div>
    
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><i class="fas fa-tags"></i> مدیریت دسته‌بندی</div>
            <div class="modal-body">
                <div id="categoryList"></div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <input type="text" id="newCategoryName" placeholder="نام دسته جدید" style="flex:1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 12px; font-size: 14px; background: rgba(255,255,255,0.03); color: var(--text-primary);">
                    <button class="btn-save" id="addCategoryBtn" style="flex:0;">افزودن</button>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeCategoryModal()">بستن</button>
            </div>
        </div>
    </div>
    
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><i class="fas fa-edit"></i> ویرایش کار</div>
            <div class="modal-body">
                <input type="text" id="editTitle" placeholder="عنوان کار">
                <select id="editCategory"></select>
                <select id="editProject"><option value="">بدون پروژه</option></select>
                <input type="date" id="editDate">
                <input type="text" id="editTime" class="time-input" placeholder="ساعت" maxlength="5">
                <select id="editPriority">
                    <option value="high">🔴 بالا</option>
                    <option value="medium">🟡 متوسط</option>
                    <option value="low">🟢 پایین</option>
                </select>
                <textarea id="editDescription" placeholder="توضیحات..." rows="4"></textarea>
                
                <div style="margin-top: 10px; padding: 10px; background: rgba(255,255,255,0.03); border-radius: 10px; border: 1px solid var(--border-color);">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">زیرتسک برای کار مادر</label>
                    <select id="editParentTask">
                        <option value="">بدون والد (تسک اصلی)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeEditModal()">انصراف</button>
                <button class="btn-save" onclick="saveEdit()">ذخیره تغییرات</button>
            </div>
        </div>
    </div>
    
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><i class="fas fa-user-circle"></i> پروفایل کاربری</div>
            <div class="modal-body">
                <div class="profile-info">
                    <div class="profile-avatar" style="background: <?php echo $currentUser['avatar_color'] ?? '#667eea'; ?>">
                        <?php echo mb_substr($currentUser['name'], 0, 1); ?>
                    </div>
                    <h3 id="profileName" style="color: var(--text-primary);"><?php echo htmlspecialchars($currentUser['name']); ?></h3>
                    <div class="profile-email" id="profileEmail"><?php echo htmlspecialchars($currentUser['email']); ?></div>
                </div>
                
                <div style="margin-top: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">تغییر رمز عبور</label>
                    <input type="password" id="newPassword" placeholder="رمز عبور جدید" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 15px; background: rgba(255,255,255,0.03); color: var(--text-primary);">
                    <input type="password" id="confirmNewPassword" placeholder="تکرار رمز عبور جدید" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 15px; background: rgba(255,255,255,0.03); color: var(--text-primary);">
                    <div id="passwordError" style="color: #dc3545; font-size: 12px; margin-bottom: 10px; display: none;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeProfileModal()">انصراف</button>
                <button class="btn-save" id="changePasswordBtn">تغییر رمز عبور</button>
            </div>
        </div>
    </div>

    <script>
        const SERVER_TODAY = document.getElementById('serverToday').value;
        const SERVER_TOMORROW = document.getElementById('serverTomorrow').value;
        
        let tasks = [];
        let categories = [];
        let projects = [];
        let currentFilter = 'today';
        let currentEditId = null;
        let sortableInstances = {};
        let filters = { date: '', priority: '', category: '', project: '', dateFrom: '', dateTo: '' };
        let currentView = localStorage.getItem('taskView') || 'grid';
        let currentProjectPage = null;
        
        // ===== مدیریت تم =====
        function toggleTheme() {
            const body = document.body;
            body.classList.toggle('dark-mode');
            const isDark = body.classList.contains('dark-mode');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            updateThemeIcon();
        }
        
        function updateThemeIcon() {
            const isDark = document.body.classList.contains('dark-mode');
            document.querySelectorAll('.theme-toggle-btn, .theme-toggle-btn-mobile').forEach(btn => {
                btn.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            });
        }
        
        function loadTheme() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
            } else {
                document.body.classList.remove('dark-mode');
            }
            updateThemeIcon();
        }
        
        // ===== توابع کمکی =====
        function toPersianNumbers(str) {
            if (str === undefined || str === null) return '';
            const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            return String(str).replace(/[0-9]/g, function(d) { return persianDigits[parseInt(d)]; });
        }
        
        function validateEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }
        
        function validateTime(timeStr) { return /^([0-1][0-9]|2[0-3]):([0-5][0-9])$/.test(timeStr); }
        
        function formatTimeToPersian(timeStr) {
            if (!timeStr) return '⏰ --:--';
            if (!validateTime(timeStr)) return '⏰ ۱۲:۰۰';
            const [hours, minutes] = timeStr.split(':');
            return `${toPersianNumbers(hours)}:${toPersianNumbers(minutes)}`;
        }
        
        function formatDate(dateStr) {
            if (!dateStr) return '';
            if (dateStr === SERVER_TODAY) {
                let d = new Date(dateStr);
                let options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                return toPersianNumbers(d.toLocaleDateString('fa-IR', options));
            }
            let d = new Date(dateStr);
            let options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            return toPersianNumbers(d.toLocaleDateString('fa-IR', options));
        }
        
        function formatDateTime(dateTimeStr) {
            if (!dateTimeStr) return '';
            let d = new Date(dateTimeStr);
            let date = formatDate(d.toISOString().split('T')[0]);
            let time = d.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
            return `${date} ساعت ${time}`;
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            let div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function isToday(dateStr) { return dateStr === SERVER_TODAY; }
        function isTomorrow(dateStr) { return dateStr === SERVER_TOMORROW; }
        function isPast(dateStr) { return dateStr < SERVER_TODAY; }
        function isUpcoming(dateStr) { return dateStr > SERVER_TODAY && dateStr !== SERVER_TOMORROW; }
        
        function getTaskChildren(taskId) { return tasks.filter(t => t.parent_id == taskId); }
        
        function getTaskProgress(taskId) {
            let children = getTaskChildren(taskId);
            if (children.length === 0) return null;
            let total = children.length;
            let done = children.filter(t => t.done).length;
            return { total, done, percent: Math.round((done / total) * 100) };
        }
        
        function updateParentSelects() {
            let options = '<option value="">بدون والد (تسک اصلی)</option>';
            let sortedTasks = [...tasks].sort((a, b) => (a.order || 0) - (b.order || 0));
            let parentCandidates = sortedTasks.filter(t => !t.parent_id || t.parent_id === '');
            parentCandidates.forEach(task => {
                options += `<option value="${task.id}">${escapeHtml(task.title)}</option>`;
            });
            document.getElementById('addParentTask').innerHTML = options;
            document.getElementById('editParentTask').innerHTML = options;
        }
        
        function syncMobileNav() {
            document.querySelectorAll('.nav-btn-mobile').forEach(btn => {
                btn.classList.remove('active-mobile');
                if (btn.dataset.filter === currentFilter) {
                    btn.classList.add('active-mobile');
                }
            });
        }
        
        // ===== منوی کشویی پروفایل =====
        document.addEventListener('DOMContentLoaded', function() {
            const trigger = document.getElementById('profileTrigger');
            const dropdown = document.getElementById('profileDropdown');
            
            if (trigger && dropdown) {
                trigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    dropdown.classList.toggle('show');
                });
                
                document.addEventListener('click', function(e) {
                    if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
                        dropdown.classList.remove('show');
                    }
                });
                
                dropdown.querySelectorAll('.dropdown-item').forEach(item => {
                    item.addEventListener('click', function() {
                        dropdown.classList.remove('show');
                    });
                });
            }
        });
        
        // ===== خروج =====
        function logout() {
            if (confirm('آیا از خروج مطمئن هستید؟')) {
                let formData = new FormData();
                formData.append('action', 'logout');
                fetch(window.location.href, { method: 'POST', body: formData }).then(() => {
                    location.href = '../index.php';
                });
            }
        }
        
        // ===== خروجی CSV =====
        async function exportCSV() {
            let formData = new FormData();
            formData.append('action', 'export_csv');
            try {
                let response = await fetch(window.location.href, { method: 'POST', body: formData });
                let result = await response.json();
                if (result.success) {
                    let blob = new Blob(["\uFEFF" + result.data], { type: 'text/csv;charset=utf-8;' });
                    let url = URL.createObjectURL(blob);
                    let a = document.createElement('a');
                    a.href = url;
                    a.download = `tasks_${new Date().toISOString().split('T')[0]}.csv`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                } else {
                    alert('خطا در ایجاد خروجی CSV');
                }
            } catch(e) {
                console.error('خطا:', e);
                alert('خطا در ارتباط با سرور');
            }
        }
        
        // ===== توابع دیتا =====
        async function loadData() {
            try {
                let formData = new FormData();
                formData.append('action', 'load');
                let response = await fetch(window.location.href, { method: 'POST', body: formData });
                let result = await response.json();
                if (result.success) {
                    tasks = result.tasks || [];
                    categories = result.categories || [];
                    projects = result.projects || [];
                    updateSelects();
                    updateParentSelects();
                    renderAll();
                } else {
                    console.error('خطا در بارگذاری دیتا:', result);
                }
            } catch(e) {
                console.error('خطا در ارتباط با سرور:', e);
            }
        }
        
        async function sendRequest(action, data = {}) {
            try {
                let formData = new FormData();
                formData.append('action', action);
                for (let key in data) {
                    formData.append(key, data[key]);
                }
                let response = await fetch(window.location.href, { method: 'POST', body: formData });
                let result = await response.json();
                if (result.success) {
                    if (result.tasks) {
                        tasks = result.tasks;
                        updateParentSelects();
                    }
                    if (result.categories) {
                        categories = result.categories;
                        updateSelects();
                        if (document.getElementById('categoryModal').style.display === 'block') refreshCategoryList();
                    }
                    if (result.projects) {
                        projects = result.projects;
                        updateSelects();
                        if (document.getElementById('projectModal').style.display === 'block') refreshProjectList();
                    }
                    renderAll();
                } else {
                    console.error('خطا در درخواست:', result);
                }
                return result;
            } catch(e) {
                console.error('خطا در ارتباط با سرور:', e);
                return { success: false };
            }
        }
        
        function updateSelects() {
            let categoryOptions = categories.map(c => `<option value="${c}">${c}</option>`).join('');
            let projectOptions = projects.map(p => `<option value="${p.name}">${p.name}</option>`).join('');
            document.getElementById('addCategory').innerHTML = categoryOptions;
            document.getElementById('addProject').innerHTML = '<option value="">بدون پروژه</option>' + projectOptions;
            document.getElementById('editCategory').innerHTML = categoryOptions;
            document.getElementById('editProject').innerHTML = '<option value="">بدون پروژه</option>' + projectOptions;
            document.getElementById('filterCategory').innerHTML = '<option value="">همه دسته‌بندی‌ها</option>' + categoryOptions;
            document.getElementById('filterProject').innerHTML = '<option value="">همه پروژه‌ها</option>' + projectOptions;
        }
        
        function refreshCategoryList() {
            let container = document.getElementById('categoryList');
            if (!categories || categories.length === 0) {
                container.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-light);">هیچ دسته‌بندی تعریف نشده است</div>';
            } else {
                container.innerHTML = categories.map(cat => `<div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border-color);"><span style="color:var(--text-primary);">${cat}</span><button onclick="deleteCategory('${cat}')" style="background:rgba(220,53,69,0.15); color:#ff6b6b; padding:4px 10px; border:none; border-radius:6px; cursor:pointer;">🗑️</button></div>`).join('');
            }
        }
        
        function refreshProjectList() {
            let container = document.getElementById('projectList');
            if (!projects || projects.length === 0) {
                container.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-light);">هیچ پروژه‌ای تعریف نشده است</div>';
            } else {
                container.innerHTML = projects.map(proj => `
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border-color);">
                        <a onclick="openProjectPage('${encodeURIComponent(proj.name)}')" style="cursor:pointer; color:var(--text-primary); text-decoration:none; display:flex; align-items:center; gap:8px;">
                            <i class="fas fa-project-diagram" style="color: ${proj.color || '#2d6a4f'}"></i> ${escapeHtml(proj.name)}
                        </a>
                        <button onclick="deleteProject('${proj.name}')" style="background:rgba(220,53,69,0.15); color:#ff6b6b; padding:4px 10px; border:none; border-radius:6px; cursor:pointer;">🗑️</button>
                    </div>
                `).join('');
            }
        }
        
        // ===== توابع فیلتر و رندر =====
        function getFilteredTasks() {
            let filtered = [...tasks];
            
            if (currentFilter === 'completed') {
                filtered = filtered.filter(t => t.done === true);
                if (filters.dateFrom && filters.dateTo) {
                    filtered = filtered.filter(t => t.date >= filters.dateFrom && t.date <= filters.dateTo);
                }
                if (filters.priority) filtered = filtered.filter(t => t.priority === filters.priority);
                if (filters.category) filtered = filtered.filter(t => t.category === filters.category);
                if (filters.project) filtered = filtered.filter(t => t.project === filters.project);
            } else {
                switch(currentFilter) {
                    case 'today': filtered = filtered.filter(t => isToday(t.date) && !t.done); break;
                    case 'tomorrow': filtered = filtered.filter(t => isTomorrow(t.date) && !t.done); break;
                    case 'upcoming': filtered = filtered.filter(t => isUpcoming(t.date) && !t.done); break;
                    case 'past': filtered = filtered.filter(t => isPast(t.date) && !t.done); break;
                    case 'all': break;
                }
            }
            
            filtered.sort((a, b) => (a.order || 0) - (b.order || 0));
            return filtered;
        }
        
        function groupByDate(tasksList) {
            let groups = {};
            tasksList.forEach(task => { if (!groups[task.date]) groups[task.date] = []; groups[task.date].push(task); });
            return groups;
        }
        
        function updateStats() {
            let total = tasks.length;
            let completed = tasks.filter(t => t.done).length;
            let todayTasks = tasks.filter(t => isToday(t.date) && !t.done).length;
            let upcoming = tasks.filter(t => !isPast(t.date) && !t.done && !isToday(t.date)).length;
            let parentTasks = tasks.filter(t => !t.parent_id || t.parent_id === '').length;
            let subtasks = tasks.filter(t => t.parent_id && t.parent_id !== '').length;
            
            let tasksWithProgress = tasks.filter(t => getTaskChildren(t.id).length > 0);
            let avgProgress = 0;
            if (tasksWithProgress.length > 0) {
                let totalProgress = tasksWithProgress.reduce((sum, t) => {
                    let p = getTaskProgress(t.id);
                    return sum + (p ? p.percent : 0);
                }, 0);
                avgProgress = Math.round(totalProgress / tasksWithProgress.length);
            }
            
            document.getElementById('stats').innerHTML = `
                <div class="stat-card"><div class="stat-number">${toPersianNumbers(total)}</div><div class="stat-label">کل وظایف</div></div>
                <div class="stat-card"><div class="stat-number">${toPersianNumbers(completed)}</div><div class="stat-label">انجام شده</div></div>
                <div class="stat-card"><div class="stat-number">${toPersianNumbers(todayTasks)}</div><div class="stat-label">وظایف امروز</div></div>
                <div class="stat-card"><div class="stat-number">${toPersianNumbers(upcoming)}</div><div class="stat-label">روزهای آینده</div></div>
                <div class="stat-card"><div class="stat-number">${toPersianNumbers(parentTasks)}</div><div class="stat-label">تسک‌های اصلی</div></div>
                <div class="stat-card"><div class="stat-number">${toPersianNumbers(subtasks)}</div><div class="stat-label">زیرتسک‌ها</div></div>
                ${tasksWithProgress.length > 0 ? `<div class="stat-card"><div class="stat-number">${toPersianNumbers(avgProgress)}%</div><div class="stat-label">میانگین پیشرفت</div></div>` : ''}
            `;
        }
        
        function autoFormatTime(input) {
            let value = input.value.replace(/[^0-9]/g, '');
            if (value.length >= 3) {
                let hours = value.substring(0, 2);
                let minutes = value.substring(2, 4);
                if (parseInt(hours) > 23) hours = '23';
                if (parseInt(minutes) > 59) minutes = '59';
                input.value = hours + ':' + minutes;
            } else if (value.length === 2) {
                input.value = value + ':';
            } else {
                input.value = value;
            }
        }
        
        function setView(view) {
            currentView = view;
            localStorage.setItem('taskView', view);
            
            if (view === 'grid') {
                document.getElementById('gridViewBtn').classList.add('active');
                document.getElementById('listViewBtn').classList.remove('active');
            } else {
                document.getElementById('gridViewBtn').classList.remove('active');
                document.getElementById('listViewBtn').classList.add('active');
            }
            renderTasks();
        }
        
        function renderProgressBar(taskId) {
            let progress = getTaskProgress(taskId);
            if (!progress) return '';
            
            return `
                <div class="progress-container">
                    <div class="progress-bar" style="width: ${progress.percent}%"></div>
                </div>
                <div class="progress-text">
                    <span>پیشرفت</span>
                    <span>${toPersianNumbers(progress.done)} از ${toPersianNumbers(progress.total)} (${toPersianNumbers(progress.percent)}%)</span>
                </div>
            `;
        }
        
        function renderSubtasks(taskId, parentTitle) {
            let children = tasks.filter(t => t.parent_id == taskId);
            if (children.length === 0) return '';
            
            return `
                <div class="subtasks-container">
                    <div style="font-size: 12px; font-weight: bold; margin-bottom: 6px; color: #667eea;">
                        <i class="fas fa-sitemap"></i> زیرتسک‌ها (${children.length})
                    </div>
                    ${children.map(child => `
                        <div class="subtask-item">
                            <input type="checkbox" class="subtask-check" ${child.done ? 'checked' : ''} onchange="toggleTask('${child.id}', ${child.done})">
                            <span class="subtask-title-text ${child.done ? 'completed' : ''}">${escapeHtml(child.title)}</span>
                            <div class="subtask-actions">
                                <button onclick="openEditModal('${child.id}')"><i class="fas fa-edit"></i></button>
                                <button onclick="deleteTask('${child.id}')"><i class="fas fa-trash" style="color:#ff6b6b;"></i></button>
                            </div>
                        </div>
                    `).join('')}
                    <div style="margin-top: 6px;">
                        <button class="subtask-btn" onclick="openAddSubtaskModal('${taskId}', '${escapeHtml(parentTitle)}')">
                            <i class="fas fa-plus"></i> افزودن زیرتسک
                        </button>
                    </div>
                </div>
            `;
        }
        
        function renderGridTasks(tasksList) {
            let mainTasks = tasksList.filter(t => !t.parent_id || t.parent_id === '');
            
            if (mainTasks.length === 0) {
                return `
                    <div class="cards-grid">
                        <div class="empty-state">هیچ تسک اصلی یافت نشد</div>
                    </div>
                `;
            }
            
            return `
                <div class="cards-grid sortable-grid">
                    ${mainTasks.map(task => {
                        let progress = getTaskProgress(task.id);
                        let isCompleted = task.done;
                        return `
                            <div class="task-card ${isCompleted ? 'completed' : ''}" data-id="${task.id}">
                                <div class="card-drag-handle"><i class="fas fa-grip-vertical"></i></div>
                                <input type="checkbox" class="card-check" ${isCompleted ? 'checked' : ''} onchange="toggleTask('${task.id}', ${isCompleted})">
                                
                                <div class="card-content">
                                    <div class="task-header-row">
                                        <div class="task-title-text ${isCompleted ? 'completed' : ''}">
                                            <a href="task_detail.php?id=${task.id}" class="task-link">
                                                ${escapeHtml(task.title)}
                                            </a>
                                            <span class="subtask-badge"><i class="fas fa-sitemap"></i> ${getTaskChildren(task.id).length}</span>
                                        </div>
                                    </div>
                                    
                                    ${task.description ? `<div class="task-description"><i class="fas fa-align-left"></i> ${escapeHtml(task.description)}</div>` : ''}
                                    
                                    ${progress ? renderProgressBar(task.id) : ''}
                                    
                                    <div class="task-meta">
                                        <span class="time-badge"><i class="far fa-clock"></i> ${formatTimeToPersian(task.time)}</span>
                                        <span class="category-badge"><i class="fas fa-tag"></i> ${task.category}</span>
                                        ${task.project ? `<span class="project-badge"><i class="fas fa-project-diagram"></i> ${task.project}</span>` : ''}
                                        <span class="priority-badge priority-${task.priority}">
                                            ${task.priority === 'high' ? '🔴 بالا' : task.priority === 'medium' ? '🟡 متوسط' : '🟢 پایین'}
                                        </span>
                                    </div>
                                    
                                    ${task.completed_at ? `<div class="completed-date"><i class="fas fa-check-circle"></i> ${formatDateTime(task.completed_at)}</div>` : ''}
                                    
                                    ${renderSubtasks(task.id, task.title)}
                                </div>
                                
                                <div class="card-actions">
                                    <button class="card-btn edit-card-btn" onclick="openEditModal('${task.id}')"><i class="fas fa-edit"></i> ویرایش</button>
                                    <button class="card-btn delete-card-btn" onclick="deleteTaskWithChildren('${task.id}')"><i class="fas fa-trash"></i> حذف</button>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        }
        
        function renderListTasks(tasksList) {
            let mainTasks = tasksList.filter(t => !t.parent_id || t.parent_id === '');
            
            if (mainTasks.length === 0) {
                return `<div class="empty-state">هیچ تسک اصلی یافت نشد</div>`;
            }
            
            return `
                <div class="list-view-container sortable-list">
                    ${mainTasks.map(task => {
                        let progress = getTaskProgress(task.id);
                        return `
                            <div class="task-item-list ${task.done ? 'completed' : ''}" data-id="${task.id}">
                                <div class="drag-handle-list"><i class="fas fa-grip-vertical"></i></div>
                                <input type="checkbox" class="task-check-list" ${task.done ? 'checked' : ''} onchange="toggleTask('${task.id}', ${task.done})">
                                <div class="task-content-list">
                                    <div class="task-title-list">
                                        <a href="task_detail.php?id=${task.id}" class="task-link">
                                            ${escapeHtml(task.title)}
                                        </a>
                                        <span class="subtask-badge"><i class="fas fa-sitemap"></i> ${getTaskChildren(task.id).length}</span>
                                    </div>
                                    ${progress ? renderProgressBar(task.id) : ''}
                                    <div class="task-meta-list">
                                        <span class="time-badge"><i class="far fa-clock"></i> ${formatTimeToPersian(task.time)}</span>
                                        <span class="category-badge"><i class="fas fa-tag"></i> ${task.category}</span>
                                        ${task.project ? `<span class="project-badge"><i class="fas fa-project-diagram"></i> ${task.project}</span>` : ''}
                                        <span class="priority-badge priority-${task.priority}">
                                            ${task.priority === 'high' ? '🔴 بالا' : task.priority === 'medium' ? '🟡 متوسط' : '🟢 پایین'}
                                        </span>
                                    </div>
                                    ${task.description ? `<div style="font-size: 12px; color: var(--text-muted); margin-top: 5px;"><i class="fas fa-align-left"></i> ${escapeHtml(task.description)}</div>` : ''}
                                    ${task.completed_at ? `<div style="font-size: 11px; color: var(--completed-date); margin-top: 5px;"><i class="fas fa-check-circle"></i> انجام شده در ${formatDateTime(task.completed_at)}</div>` : ''}
                                    ${renderSubtasks(task.id, task.title)}
                                </div>
                                <div class="task-actions-list">
                                    <button class="edit-btn-list" onclick="openEditModal('${task.id}')"><i class="fas fa-edit"></i></button>
                                    <button class="delete-btn-list" onclick="deleteTaskWithChildren('${task.id}')"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        }
        
        function renderTasks() {
            let filtered = getFilteredTasks();
            let grouped = groupByDate(filtered);
            let sortedDates = Object.keys(grouped).sort().reverse();
            let container = document.getElementById('tasksList');
            
            if (sortedDates.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-inbox" style="font-size: 48px;"></i><div>هیچ کاری یافت نشد</div></div>';
                updateStats();
                return;
            }
            
            container.innerHTML = sortedDates.map(date => `
                <div class="date-group">
                    <div class="date-header">
                        <span><i class="fas fa-calendar-alt"></i> ${formatDate(date)}</span>
                        <span>${toPersianNumbers(grouped[date].length)} کار</span>
                    </div>
                    <div class="tasks-container-${currentView}">
                        ${currentView === 'grid' ? renderGridTasks(grouped[date]) : renderListTasks(grouped[date])}
                    </div>
                </div>
            `).join('');
            
            initSortables();
            updateStats();
            syncMobileNav();
        }
        
        function initSortables() {
            for (let id in sortableInstances) sortableInstances[id].destroy();
            sortableInstances = {};
            
            document.querySelectorAll('.sortable-grid').forEach(grid => {
                sortableInstances[grid.id] = new Sortable(grid, {
                    animation: 300,
                    handle: '.card-drag-handle',
                    ghostClass: 'dragging',
                    onEnd: async function() {
                        let allIds = [];
                        document.querySelectorAll('.sortable-grid').forEach(g => {
                            g.querySelectorAll('.task-card').forEach(card => {
                                let id = card.getAttribute('data-id');
                                if (id) allIds.push(id);
                            });
                        });
                        if (allIds.length > 0) await sendRequest('reorder', { ids: JSON.stringify(allIds) });
                    }
                });
            });
            
            document.querySelectorAll('.sortable-list').forEach(list => {
                sortableInstances[list.id] = new Sortable(list, {
                    animation: 300,
                    handle: '.drag-handle-list',
                    ghostClass: 'dragging',
                    onEnd: async function() {
                        let allIds = [];
                        document.querySelectorAll('.sortable-list').forEach(l => {
                            l.querySelectorAll('.task-item-list').forEach(item => {
                                let id = item.getAttribute('data-id');
                                if (id) allIds.push(id);
                            });
                        });
                        if (allIds.length > 0) await sendRequest('reorder', { ids: JSON.stringify(allIds) });
                    }
                });
            });
        }
        
        // ===== توابع عملیات تسک =====
        async function toggleTask(id, currentDone) {
            await sendRequest('toggle', { id: id, current_done: currentDone });
            if (currentProjectPage) {
                openProjectPage(encodeURIComponent(currentProjectPage));
            }
        }
        
        async function deleteTask(id) {
            if (confirm('حذف شود؟')) await sendRequest('delete', { id: id });
        }
        
        async function deleteTaskWithChildren(id) {
            let task = tasks.find(t => t.id == id);
            let children = getTaskChildren(id);
            
            if (children.length > 0) {
                if (confirm(`تسک "${task ? task.title : ''}" دارای ${children.length} زیرتسک است.\nآیا می‌خواهید همه آن‌ها را حذف کنید؟`)) {
                    await sendRequest('delete', { id: id, delete_children: 'true' });
                }
            } else {
                if (confirm('حذف شود؟')) await sendRequest('delete', { id: id });
            }
        }
        
        function openAddTaskModal() {
            document.getElementById('addTitle').value = '';
            document.getElementById('addDescription').value = '';
            document.getElementById('addDate').value = new Date().toISOString().split('T')[0];
            document.getElementById('addTime').value = '12:00';
            document.getElementById('addPriority').value = 'medium';
            document.getElementById('addParentTask').value = '';
            document.getElementById('addTaskModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function openAddSubtaskModal(parentId, parentTitle) {
            document.getElementById('addTitle').value = '';
            document.getElementById('addDescription').value = '';
            document.getElementById('addDate').value = new Date().toISOString().split('T')[0];
            document.getElementById('addTime').value = '12:00';
            document.getElementById('addPriority').value = 'medium';
            
            let parentSelect = document.getElementById('addParentTask');
            parentSelect.value = parentId;
            
            document.querySelector('#addTaskModal .modal-header').innerHTML = 
                `<i class="fas fa-plus-circle"></i> افزودن زیرتسک برای "${escapeHtml(parentTitle)}"`;
            
            document.getElementById('addTaskModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeAddTaskModal() {
            document.getElementById('addTaskModal').style.display = 'none';
            document.body.style.overflow = '';
            document.querySelector('#addTaskModal .modal-header').innerHTML = 
                `<i class="fas fa-plus-circle"></i> افزودن کار جدید`;
        }
        
        async function addNewTask() {
            let title = document.getElementById('addTitle').value.trim();
            if (!title) { alert('لطفاً عنوان کار را وارد کنید'); return; }
            let timeValue = document.getElementById('addTime').value;
            if (!validateTime(timeValue)) { alert('فرمت زمان صحیح نیست'); return; }
            
            let parentId = document.getElementById('addParentTask').value;
            
            await sendRequest('add', {
                title: title,
                description: document.getElementById('addDescription').value,
                category: document.getElementById('addCategory').value,
                project: document.getElementById('addProject').value,
                date: document.getElementById('addDate').value,
                time: timeValue,
                priority: document.getElementById('addPriority').value,
                parent_id: parentId
            });
            closeAddTaskModal();
            document.querySelector('#addTaskModal .modal-header').innerHTML = 
                `<i class="fas fa-plus-circle"></i> افزودن کار جدید`;
        }
        
        function openEditModal(id) {
            let task = tasks.find(t => t.id == id);
            if (!task) return;
            currentEditId = id;
            document.getElementById('editTitle').value = task.title;
            document.getElementById('editCategory').value = task.category;
            document.getElementById('editProject').value = task.project || '';
            document.getElementById('editDate').value = task.date;
            document.getElementById('editTime').value = task.time;
            document.getElementById('editPriority').value = task.priority;
            document.getElementById('editDescription').value = task.description || '';
            document.getElementById('editParentTask').value = task.parent_id || '';
            document.getElementById('editModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeEditModal() { 
            document.getElementById('editModal').style.display = 'none'; 
            currentEditId = null;
            document.body.style.overflow = '';
        }
        
        async function saveEdit() {
            if (!currentEditId) return;
            let timeValue = document.getElementById('editTime').value;
            if (!validateTime(timeValue)) { alert('فرمت زمان صحیح نیست'); return; }
            
            await sendRequest('edit', {
                id: currentEditId,
                title: document.getElementById('editTitle').value,
                description: document.getElementById('editDescription').value,
                category: document.getElementById('editCategory').value,
                project: document.getElementById('editProject').value,
                date: document.getElementById('editDate').value,
                time: timeValue,
                priority: document.getElementById('editPriority').value,
                parent_id: document.getElementById('editParentTask').value
            });
            closeEditModal();
        }
        
        // ===== توابع مدیریت دسته و پروژه =====
        function openCategoryModal() {
            refreshCategoryList();
            document.getElementById('categoryModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeCategoryModal() { 
            document.getElementById('categoryModal').style.display = 'none'; 
            document.body.style.overflow = '';
        }
        
        async function addCategory() {
            let newCat = document.getElementById('newCategoryName').value.trim();
            if (newCat && !categories.includes(newCat)) {
                await sendRequest('add_category', { category: newCat });
                document.getElementById('newCategoryName').value = '';
            } else if (newCat && categories.includes(newCat)) {
                alert('این دسته بندی قبلاً وجود دارد');
            } else {
                alert('لطفاً نام دسته بندی را وارد کنید');
            }
        }
        
        async function deleteCategory(category) {
            if (confirm(`حذف دسته "${category}"؟`)) {
                await sendRequest('delete_category', { category: category });
            }
        }
        
        function openProjectModal() {
            refreshProjectList();
            document.getElementById('projectModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeProjectModal() { 
            document.getElementById('projectModal').style.display = 'none'; 
            document.body.style.overflow = '';
        }
        
        async function addProject() {
            let newProj = document.getElementById('newProjectName').value.trim();
            if (!newProj) {
                alert('لطفاً نام پروژه را وارد کنید');
                return;
            }
            if (projects.some(p => p.name === newProj)) {
                alert('این پروژه قبلاً وجود دارد');
                return;
            }
            let result = await sendRequest('add_project', { project: newProj });
            if (result && !result.success && result.message) {
                alert(result.message);
            } else {
                document.getElementById('newProjectName').value = '';
            }
        }
        
        async function deleteProject(project) {
            if (confirm(`آیا از حذف پروژه "${project}" مطمئن هستید؟\nتوجه: تسک‌های این پروژه به "بدون پروژه" تغییر می‌یابند.`)) {
                await sendRequest('delete_project', { project: project });
                closeProjectModal();
            }
        }
        
        // ===== صفحه پروژه =====
        function openProjectPage(projectName) {
            let decodedName = decodeURIComponent(projectName);
            let project = projects.find(p => p.name === decodedName);
            if (!project) {
                alert('پروژه یافت نشد');
                return;
            }
            currentProjectPage = decodedName;
            document.getElementById('projectPageTitle').innerHTML = project.name;
            let descText = project.description || 'هنوز توضیحاتی ثبت نشده است';
            document.getElementById('projectPageDesc').innerHTML = escapeHtml(descText).replace(/\n/g, '<br>');
            
            let projectTasks = tasks.filter(t => t.project === decodedName);
            let total = projectTasks.length;
            let completed = projectTasks.filter(t => t.done).length;
            let pending = total - completed;
            let percent = total > 0 ? Math.round((completed / total) * 100) : 0;
            
            document.getElementById('projectPageStats').innerHTML = `
                <div class="project-stat-card"><div style="font-size: 22px; font-weight: bold; color: #43e97b;">${toPersianNumbers(total)}</div><div>کل تسک‌ها</div></div>
                <div class="project-stat-card"><div style="font-size: 22px; font-weight: bold; color: #43e97b;">${toPersianNumbers(completed)}</div><div>انجام شده</div></div>
                <div class="project-stat-card"><div style="font-size: 22px; font-weight: bold; color: #f5576c;">${toPersianNumbers(pending)}</div><div>در انتظار</div></div>
                <div class="project-stat-card"><div style="font-size: 22px; font-weight: bold; color: #667eea;">${toPersianNumbers(percent)}%</div><div>پیشرفت</div></div>
            `;
            
            if (projectTasks.length === 0) {
                document.getElementById('projectPageTasks').innerHTML = '<div style="text-align:center; padding:30px; color:var(--text-light);">هیچ تسکی برای این پروژه وجود ندارد</div>';
            } else {
                document.getElementById('projectPageTasks').innerHTML = projectTasks.map(task => {
                    let progress = getTaskProgress(task.id);
                    let progressText = progress ? ` | پیشرفت: ${progress.done}/${progress.total} (${progress.percent}%)` : '';
                    return `
                        <div class="project-task-item">
                            <a href="task_detail.php?id=${task.id}" class="task-link">
                                <div class="task-title ${task.done ? 'done' : ''}">
                                    ${escapeHtml(task.title)}
                                    ${progress ? `<span style="font-size: 11px; color: var(--completed-date);"> (${progress.percent}%)</span>` : ''}
                                </div>
                                <div class="task-meta">
                                    <i class="far fa-calendar-alt"></i> ${formatDate(task.date)} - ${formatTimeToPersian(task.time)}
                                    <span style="background: rgba(245,87,108,0.15); color:#f5576c; padding: 2px 6px; border-radius: 10px; margin-right: 8px;"><i class="fas fa-tag"></i> ${task.category}</span>
                                    ${task.parent_id ? `<span style="background: var(--badge-bg); color: var(--badge-color); padding: 2px 6px; border-radius: 10px;"><i class="fas fa-sitemap"></i> زیرتسک</span>` : ''}
                                    ${task.description ? `<span style="color: var(--text-light); margin-right: 8px;"><i class="fas fa-align-left"></i> ${escapeHtml(task.description.substring(0, 30))}${task.description.length > 30 ? '...' : ''}</span>` : ''}
                                    ${progressText}
                                    ${task.completed_at ? `<span style="color: var(--completed-date); margin-right: 8px; font-size: 10px;"><i class="fas fa-check-circle"></i> ${formatDateTime(task.completed_at)}</span>` : ''}
                                </div>
                            </a>
                            <div class="task-actions">
                                <span class="priority-badge priority-${task.priority}" style="font-size: 11px; padding: 2px 8px; border-radius: 10px;">
                                    ${task.priority === 'high' ? '🔴 بالا' : task.priority === 'medium' ? '🟡 متوسط' : '🟢 پایین'}
                                </span>
                                <input type="checkbox" ${task.done ? 'checked' : ''} onchange="toggleTaskFromProject('${task.id}')" style="width: 22px; height: 22px; cursor: pointer; accent-color: #667eea;">
                                <button onclick="event.stopPropagation(); window.location.href='task_detail.php?id=${task.id}'" class="view-btn">
                                    <i class="fas fa-external-link-alt"></i> مشاهده
                                </button>
                            </div>
                        </div>
                    `;
                }).join('');
            }
            document.getElementById('projectPageModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeProjectPageModal() {
            document.getElementById('projectPageModal').style.display = 'none';
            document.body.style.overflow = '';
            currentProjectPage = null;
        }
        
        async function toggleTaskFromProject(id) {
            let task = tasks.find(t => t.id == id);
            if (task) {
                await toggleTask(id, task.done);
                if (currentProjectPage) {
                    openProjectPage(encodeURIComponent(currentProjectPage));
                }
            }
        }
        
        async function editProjectDescription() {
            if (!currentProjectPage) return;
            let currentDesc = document.getElementById('projectPageDesc').innerText;
            let newDesc = prompt('توضیحات جدید را وارد کنید:', currentDesc);
            if (newDesc !== null && newDesc !== currentDesc) {
                await sendRequest('update_project_description', {
                    name: currentProjectPage,
                    description: newDesc
                });
                document.getElementById('projectPageDesc').innerHTML = escapeHtml(newDesc).replace(/\n/g, '<br>');
                let project = projects.find(p => p.name === currentProjectPage);
                if (project) project.description = newDesc;
            }
        }
        
        // ===== توابع فیلتر =====
        function applyDateRange() {
            let fromDate = document.getElementById('filterDateFrom').value;
            let toDate = document.getElementById('filterDateTo').value;
            
            if (fromDate && toDate) {
                filters.dateFrom = fromDate;
                filters.dateTo = toDate;
                renderTasks();
            } else {
                alert('لطفاً هر دو تاریخ را انتخاب کنید');
            }
        }
        
        function clearFilters() {
            filters = { date: '', priority: '', category: '', project: '', dateFrom: '', dateTo: '' };
            document.getElementById('filterDateFrom').value = '';
            document.getElementById('filterDateTo').value = '';
            document.getElementById('filterPriority').value = '';
            document.getElementById('filterCategory').value = '';
            document.getElementById('filterProject').value = '';
            renderTasks();
        }
        
        function setupFilters() {
            let filtersCard = document.getElementById('filtersCard');
            if (currentFilter === 'completed') {
                filtersCard.style.display = 'block';
            } else {
                filtersCard.style.display = 'none';
                clearFilters();
            }
        }
        
        function renderAll() {
            setupFilters();
            renderTasks();
            syncMobileNav();
        }
        
        // ===== پروفایل و منو =====
        function openProfileModal() {
            document.getElementById('profileModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeProfileModal() {
            document.getElementById('profileModal').style.display = 'none';
            document.body.style.overflow = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmNewPassword').value = '';
            document.getElementById('passwordError').style.display = 'none';
        }
        
        async function changePassword() {
            let newPassword = document.getElementById('newPassword').value;
            let confirmPassword = document.getElementById('confirmNewPassword').value;
            let errorDiv = document.getElementById('passwordError');
            
            if (!newPassword || !confirmPassword) {
                errorDiv.innerText = 'لطفاً رمز عبور جدید را وارد کنید';
                errorDiv.style.display = 'block';
                return;
            }
            
            if (newPassword.length < 4) {
                errorDiv.innerText = 'رمز عبور باید حداقل ۴ کاراکتر باشد';
                errorDiv.style.display = 'block';
                return;
            }
            
            if (newPassword !== confirmPassword) {
                errorDiv.innerText = 'رمز عبور و تکرار آن مطابقت ندارند';
                errorDiv.style.display = 'block';
                return;
            }
            
            let formData = new FormData();
            formData.append('action', 'change_password');
            formData.append('new_password', newPassword);
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            
            if (result.success) {
                alert('رمز عبور با موفقیت تغییر کرد');
                closeProfileModal();
                document.getElementById('newPassword').value = '';
                document.getElementById('confirmNewPassword').value = '';
            } else {
                errorDiv.innerText = result.message || 'خطا در تغییر رمز عبور';
                errorDiv.style.display = 'block';
            }
        }
        
        // ===== Event Listeners =====
        document.getElementById('openAddTaskBtn')?.addEventListener('click', openAddTaskModal);
        document.getElementById('saveAddTaskBtn')?.addEventListener('click', addNewTask);
        document.getElementById('addCategoryBtn')?.addEventListener('click', addCategory);
        document.getElementById('addProjectBtn')?.addEventListener('click', addProject);
        document.getElementById('editProjectDescBtn')?.addEventListener('click', editProjectDescription);
        document.getElementById('exportCsvBtn')?.addEventListener('click', exportCSV);
        document.getElementById('applyDateRangeBtn')?.addEventListener('click', applyDateRange);
        document.getElementById('changePasswordBtn')?.addEventListener('click', changePassword);
        
        document.getElementById('gridViewBtn')?.addEventListener('click', () => setView('grid'));
        document.getElementById('listViewBtn')?.addEventListener('click', () => setView('list'));
        
        document.querySelectorAll('.time-input').forEach(input => {
            input.addEventListener('input', function(e) { autoFormatTime(this); });
        });
        
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (btn.dataset.filter) {
                    document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentFilter = btn.dataset.filter;
                    renderAll();
                }
            });
        });
        
        document.querySelectorAll('.nav-btn-mobile').forEach(btn => {
            btn.addEventListener('click', function() {
                let filter = this.dataset.filter;
                if (filter) {
                    document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
                    document.querySelectorAll('.nav-btn-mobile').forEach(b => b.classList.remove('active-mobile'));
                    
                    document.querySelector(`.nav-btn[data-filter="${filter}"]`)?.classList.add('active');
                    this.classList.add('active-mobile');
                    
                    currentFilter = filter;
                    renderAll();
                    closeMobileMenu();
                }
            });
        });
        
        document.getElementById('filterPriority')?.addEventListener('change', (e) => { filters.priority = e.target.value; renderTasks(); });
        document.getElementById('filterCategory')?.addEventListener('change', (e) => { filters.category = e.target.value; renderTasks(); });
        document.getElementById('filterProject')?.addEventListener('change', (e) => { filters.project = e.target.value; renderTasks(); });
        
        window.onclick = function(event) {
            if (event.target === document.getElementById('addTaskModal')) {
                closeAddTaskModal();
                document.querySelector('#addTaskModal .modal-header').innerHTML = 
                    `<i class="fas fa-plus-circle"></i> افزودن کار جدید`;
            }
            if (event.target === document.getElementById('editModal')) closeEditModal();
            if (event.target === document.getElementById('categoryModal')) closeCategoryModal();
            if (event.target === document.getElementById('projectModal')) closeProjectModal();
            if (event.target === document.getElementById('projectPageModal')) closeProjectPageModal();
            if (event.target === document.getElementById('profileModal')) closeProfileModal();
        }
        
        // ===== مقداردهی اولیه =====
        if (currentView === 'grid') {
            document.getElementById('gridViewBtn')?.classList.add('active');
        } else {
            document.getElementById('listViewBtn')?.classList.add('active');
        }
        
        document.querySelector('.nav-btn[data-filter="today"]')?.classList.add('active');
        
        loadTheme();
        loadData();
    </script>
</body>
</html>
