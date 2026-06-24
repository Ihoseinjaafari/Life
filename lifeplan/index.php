<?php
// lifeplan/index.php - لایف‌پلن با ساختار گروه‌بندی و ویرایش
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

if (!file_exists($lifeplanFile)) {
    file_put_contents($lifeplanFile, json_encode([]));
}

// ==================== توابع ====================
function getLifePlanGroups($userId) {
    global $lifeplanFile;
    $allData = json_decode(file_get_contents($lifeplanFile), true);
    if (!is_array($allData)) return [];
    $groups = array_values(array_filter($allData, function($g) use ($userId) {
        return ($g['user_id'] ?? '') == $userId;
    }));
    usort($groups, function($a, $b) {
        return ($a['order'] ?? 0) - ($b['order'] ?? 0);
    });
    return $groups;
}

function saveLifePlanGroups($userId, $groups) {
    global $lifeplanFile;
    $allData = json_decode(file_get_contents($lifeplanFile), true);
    if (!is_array($allData)) $allData = [];
    
    $allData = array_values(array_filter($allData, function($g) use ($userId) {
        return ($g['user_id'] ?? '') != $userId;
    }));
    
    foreach ($groups as &$group) {
        $group['user_id'] = $userId;
    }
    
    $allData = array_merge($allData, $groups);
    file_put_contents($lifeplanFile, json_encode($allData, JSON_PRETTY_PRINT));
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
    
    if ($action === 'load') {
        $response = ['success' => true, 'groups' => getLifePlanGroups($userId)];
    }
    elseif ($action === 'add_group') {
        $groups = getLifePlanGroups($userId);
        $newGroup = [
            'id' => time() . rand(100, 999),
            'user_id' => $userId,
            'title' => htmlspecialchars(trim($_POST['title'] ?? 'گروه جدید')),
            'order' => count($groups),
            'created_at' => date('Y-m-d H:i:s'),
            'cards' => []
        ];
        $groups[] = $newGroup;
        saveLifePlanGroups($userId, $groups);
        $response = ['success' => true, 'groups' => getLifePlanGroups($userId)];
    }
    elseif ($action === 'edit_group') {
        $editId = $_POST['id'] ?? '';
        $groups = getLifePlanGroups($userId);
        foreach ($groups as &$group) {
            if ($group['id'] == $editId) {
                if (isset($_POST['title'])) $group['title'] = htmlspecialchars(trim($_POST['title']));
                break;
            }
        }
        saveLifePlanGroups($userId, $groups);
        $response = ['success' => true, 'groups' => getLifePlanGroups($userId)];
    }
    elseif ($action === 'delete_group') {
        $deleteId = $_POST['id'] ?? '';
        $groups = getLifePlanGroups($userId);
        $groups = array_values(array_filter($groups, function($g) use ($deleteId) {
            return $g['id'] != $deleteId;
        }));
        saveLifePlanGroups($userId, $groups);
        $response = ['success' => true, 'groups' => getLifePlanGroups($userId)];
    }
    elseif ($action === 'add_card') {
        $groupId = $_POST['group_id'] ?? '';
        $groups = getLifePlanGroups($userId);
        foreach ($groups as &$group) {
            if ($group['id'] == $groupId) {
                $newCard = [
                    'id' => time() . rand(100, 999),
                    'title' => htmlspecialchars(trim($_POST['title'] ?? 'کارت جدید')),
                    'content' => htmlspecialchars(trim($_POST['content'] ?? '')),
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $group['cards'][] = $newCard;
                break;
            }
        }
        saveLifePlanGroups($userId, $groups);
        $response = ['success' => true, 'groups' => getLifePlanGroups($userId)];
    }
    elseif ($action === 'edit_card') {
        $editCardId = $_POST['id'] ?? '';
        $groups = getLifePlanGroups($userId);
        foreach ($groups as &$group) {
            foreach ($group['cards'] as &$card) {
                if ($card['id'] == $editCardId) {
                    if (isset($_POST['title'])) $card['title'] = htmlspecialchars(trim($_POST['title']));
                    if (isset($_POST['content'])) $card['content'] = htmlspecialchars(trim($_POST['content']));
                    break 2;
                }
            }
        }
        saveLifePlanGroups($userId, $groups);
        $response = ['success' => true, 'groups' => getLifePlanGroups($userId)];
    }
    elseif ($action === 'delete_card') {
        $deleteCardId = $_POST['id'] ?? '';
        $groups = getLifePlanGroups($userId);
        foreach ($groups as &$group) {
            $group['cards'] = array_values(array_filter($group['cards'], function($c) use ($deleteCardId) {
                return $c['id'] != $deleteCardId;
            }));
        }
        saveLifePlanGroups($userId, $groups);
        $response = ['success' => true, 'groups' => getLifePlanGroups($userId)];
    }
    elseif ($action === 'reorder_groups') {
        $ids = json_decode($_POST['ids'] ?? '[]', true);
        $groups = getLifePlanGroups($userId);
        $newGroups = [];
        foreach ($ids as $index => $id) {
            foreach ($groups as $group) {
                if ($group['id'] == $id) {
                    $group['order'] = $index;
                    $newGroups[] = $group;
                    break;
                }
            }
        }
        saveLifePlanGroups($userId, $newGroups);
        $response = ['success' => true, 'groups' => getLifePlanGroups($userId)];
    }
    elseif ($action === 'convert_to_task') {
        $title = trim($_POST['title'] ?? '');
        $date = $_POST['date'] ?? date('Y-m-d');
        $time = $_POST['time'] ?? '12:00';
        $priority = $_POST['priority'] ?? 'medium';
        $category = $_POST['category'] ?? 'لایف‌پلن';
        $project = $_POST['project'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $cardId = $_POST['card_id'] ?? '';
        
        if (empty($title)) {
            $response = ['success' => false, 'message' => 'عنوان تسک الزامی است'];
            echo json_encode($response);
            exit;
        }
        
        $tasks = [];
        if (file_exists($tasksFile)) {
            $tasks = json_decode(file_get_contents($tasksFile), true);
            if (!is_array($tasks)) $tasks = [];
        }
        
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
            'source_id' => $cardId
        ];
        
        $tasks[] = $newTask;
        file_put_contents($tasksFile, json_encode($tasks, JSON_PRETTY_PRINT));
        
        $response = ['success' => true, 'message' => 'تسک با موفقیت به Planner اضافه شد'];
    }
    
    echo json_encode($response);
    exit;
}

$groups = getLifePlanGroups($userId);
$categories = getCategories();
$projects = getUserProjects($userId);
$currentDate = date('Y-m-d');
$pageTitle = 'لایف‌پلن';
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لایف‌پلن | برنامه‌ریزی زندگی</title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        /* ===== متغیرهای CSS برای تم ===== */
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
            --body-bg: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            --header-bg: rgba(255,255,255,0.05);
            --card-bg: rgba(255,255,255,0.05);
            --card-border: rgba(255,255,255,0.08);
            --input-bg: rgba(255,255,255,0.05);
            --modal-bg: #1a1a2e;
            --modal-border: rgba(255,255,255,0.08);
            --toast-bg-color: #1a1a2e;
            --empty-color: rgba(255,255,255,0.2);
            --pdf-bg: #0f0c29;
            --group-bg: rgba(255,255,255,0.05);
            --group-border: rgba(255,255,255,0.08);
            --group-header-bg: rgba(255,255,255,0.03);
            --item-bg: rgba(255,255,255,0.03);
            --item-border: rgba(255,255,255,0.06);
            --item-hover-bg: rgba(255,255,255,0.05);
            --group-title-color: #ffffff;
            --card-title-color: #ffffff;
            --card-content-color: rgba(255,255,255,0.8);
            --section-bg: rgba(255,255,255,0.05);
            --section-border: rgba(255,255,255,0.08);
        }

        /* ===== تم روشن ===== */
        body.light-mode {
            --bg-primary: #f0f2f5;
            --bg-secondary: #ffffff;
            --bg-card: #ffffff;
            --bg-card-hover: #f8f9fa;
            --bg-input: #fafafa;
            --bg-input-hover: #ffffff;
            --text-primary: #1a1a2e;
            --text-secondary: #333333;
            --text-muted: #6c757d;
            --text-light: #999999;
            --border-color: #e8e8e8;
            --border-card: #f0f0f0;
            --shadow-color: rgba(0,0,0,0.08);
            --shadow-hover: rgba(0,0,0,0.15);
            --modal-overlay: rgba(0,0,0,0.5);
            --toast-bg: #1a1a2e;
            --badge-bg: #e7f3ff;
            --badge-color: #0066cc;
            --body-bg: #f0f2f5;
            --header-bg: #ffffff;
            --card-bg: #ffffff;
            --card-border: #e8e8e8;
            --input-bg: #fafafa;
            --modal-bg: #ffffff;
            --modal-border: #e8e8e8;
            --toast-bg-color: #1a1a2e;
            --empty-color: #999999;
            --pdf-bg: #f0f2f5;
            --group-bg: #ffffff;
            --group-border: #e8e8e8;
            --group-header-bg: #f8f9fa;
            --item-bg: #fafafa;
            --item-border: #f0f0f0;
            --item-hover-bg: #f8f9fa;
            --group-title-color: #1a1a2e;
            --card-title-color: #1a1a2e;
            --card-content-color: #333333;
            --section-bg: #ffffff;
            --section-border: #e8e8e8;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            background: var(--body-bg);
            min-height: 100vh;
            padding: 20px;
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        .container { max-width: 1200px; margin: 0 auto; }
        
        /* ===== دکمه تغییر تم ===== */
        .theme-toggle-btn {
            background: rgba(255,255,255,0.1);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            width: 38px;
            height: 38px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .theme-toggle-btn:hover {
            background: rgba(255,255,255,0.15);
            transform: scale(1.05);
        }
        
        body.light-mode .theme-toggle-btn {
            background: rgba(0,0,0,0.05);
            color: #333;
        }
        
        body.light-mode .theme-toggle-btn:hover {
            background: rgba(0,0,0,0.1);
        }
        
        /* ===== هدر ===== */
        .lifeplan-header {
            background: var(--header-bg);
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
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        
        .lifeplan-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: color 0.3s ease;
        }
        
        .lifeplan-header h1 i { color: #f5576c; }
        
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
        
        .btn-planner {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-planner:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102,126,234,0.4);
        }
        
        .btn-add-group {
            background: linear-gradient(135deg, #f5576c, #f093fb);
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
        
        .btn-add-group:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(245,87,108,0.4);
        }
        
        .btn-pdf {
            background: linear-gradient(135deg, #dc3545, #c82333);
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
        
        .btn-pdf:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(220,53,69,0.4);
        }
        
        .btn-pdf:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-edit-toggle {
            background: linear-gradient(135deg, #f59e0b, #f97316);
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
        
        .btn-edit-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(245,158,11,0.4);
        }
        
        .btn-edit-toggle.active {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
        
        .btn-edit-toggle.active:hover {
            box-shadow: 0 5px 20px rgba(40,167,69,0.4);
        }
        
        /* ===== عنوان ثابت و قابل ویرایش ===== */
        .lifeplan-title-section {
            background: var(--section-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 25px;
            border: 1px solid var(--section-border);
            text-align: center;
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        
        .lifeplan-title-section .main-title {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            cursor: pointer;
            transition: color 0.3s ease;
            padding: 5px 15px;
            border-radius: 10px;
            display: inline-block;
            position: relative;
        }
        
        .lifeplan-title-section .main-title:hover {
            background: var(--bg-input);
        }
        
        .lifeplan-title-section .main-title .edit-hint {
            font-size: 12px;
            color: var(--text-light);
            font-weight: 400;
            margin-right: 10px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .lifeplan-title-section .main-title:hover .edit-hint {
            opacity: 1;
        }
        
        .lifeplan-title-section .main-title-input {
            display: none;
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            background: var(--bg-input);
            border: 2px solid #667eea;
            border-radius: 12px;
            padding: 5px 15px;
            text-align: center;
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            font-family: inherit;
        }
        
        .lifeplan-title-section .main-title-input:focus {
            outline: none;
        }
        
        .lifeplan-title-section .sub-title {
            font-size: 16px;
            color: var(--text-muted);
            margin-top: 8px;
        }
        
        /* ===== گروه‌ها ===== */
        .groups-container {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .group-card {
            background: var(--group-bg);
            border-radius: 20px;
            border: 1px solid var(--group-border);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .group-card:hover {
            border-color: rgba(102,126,234,0.3);
        }
        
        .group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            background: var(--group-header-bg);
            border-bottom: 1px solid var(--group-border);
            cursor: grab;
        }
        
        .group-header .drag-handle {
            color: var(--text-light);
            cursor: grab;
            font-size: 16px;
            margin-left: 12px;
        }
        
        .group-header .group-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--group-title-color);
            flex: 1;
            transition: color 0.3s ease;
        }
        
        .group-header .group-actions {
            display: flex;
            gap: 6px;
        }
        
        .group-header .group-actions button {
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 14px;
            display: none;
        }
        
        .group-header .group-actions button.visible {
            display: inline-flex;
        }
        
        .group-header .group-actions .edit-group-btn:hover {
            background: var(--badge-bg);
            color: var(--badge-color);
        }
        
        .group-header .group-actions .delete-group-btn:hover {
            background: rgba(220,53,69,0.15);
            color: #ff6b6b;
        }
        
        .group-header .group-actions .add-card-btn:hover {
            background: rgba(40,167,69,0.15);
            color: #28a745;
        }
        
        /* ===== کارت‌ها ===== */
        .cards-container {
            padding: 16px 24px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }
        
        .cards-container.empty {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px;
            color: var(--text-light);
            font-size: 14px;
        }
        
        .card-item {
            background: var(--item-bg);
            border-radius: 14px;
            padding: 18px 20px;
            border: 1px solid var(--item-border);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .card-item:hover {
            border-color: rgba(102,126,234,0.3);
            background: var(--item-hover-bg);
        }
        
        .card-item .card-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--card-title-color);
            margin-bottom: 6px;
            transition: color 0.3s ease;
        }
        
        .card-item .card-content {
            font-size: 14px;
            color: var(--card-content-color);
            line-height: 1.7;
            white-space: pre-wrap;
            word-break: break-word;
            transition: color 0.3s ease;
        }
        
        .card-item .card-content:empty::before {
            content: 'متن خالی';
            color: var(--text-light);
            font-style: italic;
        }
        
        .card-item .card-actions {
            display: flex;
            gap: 4px;
            position: absolute;
            top: 14px;
            left: 14px;
        }
        
        .card-item .card-actions button {
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 13px;
            display: none;
        }
        
        .card-item .card-actions button.visible {
            display: inline-flex;
        }
        
        .card-item .card-actions .edit-card-btn:hover {
            background: var(--badge-bg);
            color: var(--badge-color);
        }
        
        .card-item .card-actions .delete-card-btn:hover {
            background: rgba(220,53,69,0.15);
            color: #ff6b6b;
        }
        
        .card-item .card-actions .convert-card-btn:hover {
            background: rgba(40,167,69,0.15);
            color: #28a745;
        }
        
        /* ===== خالی ===== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }
        
        .empty-state .icon {
            font-size: 48px;
            color: var(--empty-color);
            margin-bottom: 16px;
            display: block;
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
            background: var(--modal-bg);
            border: 1px solid var(--modal-border);
            border-radius: 25px;
            padding: 30px;
            max-width: 550px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            transition: background 0.3s ease, border-color 0.3s ease;
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
        
        .modal-header i { color: #f5576c; }
        
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
            background: var(--input-bg);
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
        
        .modal-body textarea { resize: vertical; min-height: 80px; }
        
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
        
        /* ===== مودال تبدیل ===== */
        .modal-convert .modal-header i { color: #667eea; }
        .modal-convert .btn-save {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .modal-convert .modal-body input,
        .modal-convert .modal-body select,
        .modal-convert .modal-body textarea {
            margin-bottom: 12px;
        }
        
        .time-input {
            direction: ltr;
            text-align: center;
            font-family: monospace !important;
            font-size: 14px;
            letter-spacing: 2px;
        }
        
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--toast-bg-color);
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
        
        /* ===== استایل PDF ===== */
        #pdfContent {
            background: transparent;
            padding: 20px;
        }
        
        /* ===== ریسپانسیو ===== */
        @media (max-width: 768px) {
            .lifeplan-header { flex-direction: column; align-items: stretch; }
            .header-actions { flex-direction: column; }
            .header-actions button, .header-actions a { width: 100%; justify-content: center; }
            .cards-container { grid-template-columns: 1fr; padding: 12px 16px; }
            .group-header { flex-wrap: wrap; gap: 10px; }
            .group-header .group-title { font-size: 16px; }
            .modal-content { padding: 20px; }
            .lifeplan-title-section .main-title { font-size: 24px; }
            .lifeplan-title-section .main-title-input { font-size: 24px; }
        }
        
        @media (max-width: 480px) {
            .card-item .card-title { font-size: 14px; }
            .card-item .card-content { font-size: 13px; }
            .lifeplan-title-section .main-title { font-size: 20px; }
            .lifeplan-title-section .main-title-input { font-size: 20px; }
            .card-item .card-actions {
                position: static;
                margin-top: 10px;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- ===== هدر ===== -->
        <div class="lifeplan-header">
            <h1><i class="fas fa-compass"></i> لایف‌پلن</h1>
            <div class="header-actions">
                <a href="../index.php" class="btn-back"><i class="fas fa-home"></i> صفحه اصلی</a>
                <a href="../planner/index.php" class="btn-planner"><i class="fas fa-tasks"></i> رفتن به Planner</a>
                <button class="btn-add-group" onclick="openAddGroupModal()"><i class="fas fa-plus"></i> گروه جدید</button>
                <button class="btn-pdf" id="pdfBtn" onclick="exportPDF()">
                    <i class="fas fa-file-pdf"></i> خروجی PDF
                </button>
                <button class="btn-edit-toggle" id="editToggleBtn" onclick="toggleEditMode()">
                    <i class="fas fa-pen"></i> <span id="editToggleText">ویرایش</span>
                </button>
                <button class="theme-toggle-btn" onclick="toggleTheme()" title="تغییر تم">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>
            </div>
        </div>
        
        <!-- ===== عنوان ثابت و قابل ویرایش ===== -->
        <div class="lifeplan-title-section">
            <div id="titleDisplay" class="main-title" onclick="editTitle()">
                <?php echo htmlspecialchars($pageTitle); ?>
                <span class="edit-hint"><i class="fas fa-pen"></i> ویرایش</span>
            </div>
            <input type="text" id="titleInput" class="main-title-input" value="<?php echo htmlspecialchars($pageTitle); ?>" maxlength="100">
            <div class="sub-title">برنامه‌ریزی اهداف بلندمدت و مسیر زندگی</div>
        </div>
        
        <!-- ===== محتوای اصلی ===== -->
        <div id="pdfContent">
            <div class="groups-container" id="groupsContainer"></div>
        </div>
    </div>
    
    <!-- ===== مودال گروه ===== -->
    <div class="modal" id="groupModal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-layer-group"></i>
                <span id="groupModalTitle">گروه جدید</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editGroupId">
                <input type="text" id="groupTitle" placeholder="عنوان گروه..." required>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeGroupModal()">انصراف</button>
                <button class="btn-save" id="saveGroupBtn">ذخیره</button>
            </div>
        </div>
    </div>
    
    <!-- ===== مودال کارت ===== -->
    <div class="modal" id="cardModal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-sticky-note"></i>
                <span id="cardModalTitle">کارت جدید</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editCardId">
                <input type="hidden" id="cardGroupId">
                <input type="text" id="cardTitle" placeholder="عنوان کارت..." required>
                <textarea id="cardContent" placeholder="متن کارت..." rows="4"></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeCardModal()">انصراف</button>
                <button class="btn-save" id="saveCardBtn">ذخیره</button>
            </div>
        </div>
    </div>
    
    <!-- ===== مودال تبدیل به تسک ===== -->
    <div class="modal modal-convert" id="convertModal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-exchange-alt"></i>
                <span>تبدیل به تسک</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="convertCardId">
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
    let groups = [];
    let sortableInstances = {};
    let groupEditId = null;
    let cardEditId = null;
    let pageTitle = '<?php echo htmlspecialchars($pageTitle); ?>';
    let editMode = false;

    // ===== مدیریت تم =====
    function toggleTheme() {
        const body = document.body;
        body.classList.toggle('light-mode');
        const isLight = body.classList.contains('light-mode');
        localStorage.setItem('lifeplanTheme', isLight ? 'light' : 'dark');
        updateThemeIcon();
    }

    function updateThemeIcon() {
        const isLight = document.body.classList.contains('light-mode');
        const icon = document.getElementById('themeIcon');
        if (icon) {
            icon.className = isLight ? 'fas fa-sun' : 'fas fa-moon';
        }
    }

    function loadTheme() {
        const savedTheme = localStorage.getItem('lifeplanTheme');
        if (savedTheme === 'light') {
            document.body.classList.add('light-mode');
        } else {
            document.body.classList.remove('light-mode');
        }
        updateThemeIcon();
    }

    // ===== مدیریت ویرایش =====
    function toggleEditMode() {
        editMode = !editMode;
        const btn = document.getElementById('editToggleBtn');
        const text = document.getElementById('editToggleText');
        if (editMode) {
            btn.classList.add('active');
            text.textContent = 'غیرفعال‌سازی ویرایش';
        } else {
            btn.classList.remove('active');
            text.textContent = 'ویرایش';
        }
        renderGroups();
    }

    // ===== مدیریت عنوان =====
    function editTitle() {
        if (!editMode) return;
        const display = document.getElementById('titleDisplay');
        const input = document.getElementById('titleInput');
        display.style.display = 'none';
        input.style.display = 'block';
        input.value = pageTitle;
        input.focus();
        input.select();
    }

    function saveTitle() {
        const display = document.getElementById('titleDisplay');
        const input = document.getElementById('titleInput');
        const newTitle = input.value.trim() || 'لایف‌پلن';
        pageTitle = newTitle;
        display.textContent = newTitle;
        display.innerHTML = newTitle + ' <span class="edit-hint"><i class="fas fa-pen"></i> ویرایش</span>';
        display.style.display = 'inline-block';
        input.style.display = 'none';
        localStorage.setItem('lifeplanTitle', newTitle);
    }

    function loadTitle() {
        const savedTitle = localStorage.getItem('lifeplanTitle');
        if (savedTitle) {
            pageTitle = savedTitle;
            const display = document.getElementById('titleDisplay');
            display.innerHTML = savedTitle + ' <span class="edit-hint"><i class="fas fa-pen"></i> ویرایش</span>';
            document.getElementById('titleInput').value = savedTitle;
        }
    }

    // ===== توابع کمکی =====
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

    // ===== بارگذاری دیتا =====
    async function loadGroups() {
        try {
            let formData = new FormData();
            formData.append('action', 'load');
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                groups = result.groups || [];
                renderGroups();
            }
        } catch(e) {
            console.error(e);
        }
    }

    // ===== رندر گروه‌ها =====
    function renderGroups() {
        const container = document.getElementById('groupsContainer');
        
        if (!groups || groups.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <span class="icon"><i class="fas fa-compass"></i></span>
                    <div style="font-size: 18px; font-weight: 600; color: var(--text-primary);">هیچ گروهی وجود ندارد</div>
                    <div style="font-size: 14px; margin-top: 8px;">با کلیک روی دکمه "گروه جدید" شروع کنید</div>
                </div>
            `;
            return;
        }
        
        container.innerHTML = groups.map(group => `
            <div class="group-card" data-id="${group.id}">
                <div class="group-header">
                    <div style="display: flex; align-items: center; flex: 1;">
                        <span class="drag-handle"><i class="fas fa-grip-vertical"></i></span>
                        <span class="group-title">${escapeHtml(group.title)}</span>
                    </div>
                    <div class="group-actions">
                        <button class="add-card-btn ${editMode ? 'visible' : ''}" onclick="openAddCardModal('${group.id}')" title="افزودن کارت">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="edit-group-btn ${editMode ? 'visible' : ''}" onclick="openEditGroupModal('${group.id}')" title="ویرایش گروه">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="delete-group-btn ${editMode ? 'visible' : ''}" onclick="deleteGroup('${group.id}')" title="حذف گروه">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="cards-container ${!group.cards || group.cards.length === 0 ? 'empty' : ''}" data-group-id="${group.id}">
                    ${!group.cards || group.cards.length === 0 ? 
                        '<span style="color: var(--text-light);">هیچ کارتی در این گروه وجود ندارد</span>' :
                        group.cards.map(card => `
                            <div class="card-item" data-id="${card.id}">
                                <div class="card-actions">
                                    <button class="convert-card-btn ${editMode ? 'visible' : ''}" onclick="openConvertModal('${card.id}')" title="تبدیل به تسک">
                                        <i class="fas fa-exchange-alt"></i>
                                    </button>
                                    <button class="edit-card-btn ${editMode ? 'visible' : ''}" onclick="openEditCardModal('${group.id}', '${card.id}')" title="ویرایش کارت">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="delete-card-btn ${editMode ? 'visible' : ''}" onclick="deleteCard('${group.id}', '${card.id}')" title="حذف کارت">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <div class="card-title">${escapeHtml(card.title)}</div>
                                <div class="card-content">${escapeHtml(card.content || '').replace(/\n/g, '<br>')}</div>
                            </div>
                        `).join('')
                    }
                </div>
            </div>
        `).join('');
        
        initSortables();
    }

    // ===== سورت کردن گروه‌ها =====
    function initSortables() {
        for (let id in sortableInstances) sortableInstances[id].destroy();
        sortableInstances = {};
        
        const container = document.getElementById('groupsContainer');
        if (container) {
            sortableInstances['groups'] = new Sortable(container, {
                animation: 300,
                handle: '.drag-handle',
                ghostClass: 'dragging',
                onEnd: async function() {
                    let ids = [];
                    document.querySelectorAll('.group-card').forEach(el => {
                        ids.push(el.dataset.id);
                    });
                    await reorderGroups(ids);
                }
            });
        }
    }

    // ===== تغییر ترتیب گروه‌ها =====
    async function reorderGroups(ids) {
        try {
            let formData = new FormData();
            formData.append('action', 'reorder_groups');
            formData.append('ids', JSON.stringify(ids));
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                groups = result.groups;
            }
        } catch(e) {
            console.error(e);
        }
    }

    // ===== مدیریت گروه =====
    function openAddGroupModal() {
        groupEditId = null;
        document.getElementById('groupModalTitle').textContent = 'گروه جدید';
        document.getElementById('editGroupId').value = '';
        document.getElementById('groupTitle').value = '';
        document.getElementById('saveGroupBtn').textContent = 'افزودن';
        document.getElementById('groupModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('groupTitle').focus(), 150);
    }

    function openEditGroupModal(id) {
        const group = groups.find(g => g.id == id);
        if (!group) return;
        groupEditId = id;
        document.getElementById('groupModalTitle').textContent = 'ویرایش گروه';
        document.getElementById('editGroupId').value = id;
        document.getElementById('groupTitle').value = group.title;
        document.getElementById('saveGroupBtn').textContent = 'ذخیره تغییرات';
        document.getElementById('groupModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('groupTitle').focus(), 150);
    }

    function closeGroupModal() {
        document.getElementById('groupModal').classList.remove('show');
        document.body.style.overflow = '';
    }

    async function saveGroup() {
        const title = document.getElementById('groupTitle').value.trim();
        if (!title) {
            showToast('لطفاً عنوان گروه را وارد کنید', 'error');
            return;
        }
        
        const id = document.getElementById('editGroupId').value;
        const action = id ? 'edit_group' : 'add_group';
        
        const btn = document.getElementById('saveGroupBtn');
        btn.disabled = true;
        btn.textContent = 'در حال ذخیره...';
        
        try {
            let formData = new FormData();
            formData.append('action', action);
            if (id) formData.append('id', id);
            formData.append('title', title);
            
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                groups = result.groups;
                renderGroups();
                closeGroupModal();
                showToast(id ? 'گروه ویرایش شد' : 'گروه اضافه شد', 'success');
            } else {
                showToast('خطا در ذخیره گروه', 'error');
            }
        } catch(e) {
            showToast('خطا در ارتباط با سرور', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = id ? 'ذخیره تغییرات' : 'افزودن';
        }
    }

    async function deleteGroup(id) {
        if (!confirm('آیا از حذف این گروه و تمام کارت‌های آن مطمئن هستید؟')) return;
        
        try {
            let formData = new FormData();
            formData.append('action', 'delete_group');
            formData.append('id', id);
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                groups = result.groups;
                renderGroups();
                showToast('گروه حذف شد', 'success');
            }
        } catch(e) {
            showToast('خطا در حذف گروه', 'error');
        }
    }

    // ===== مدیریت کارت =====
    function openAddCardModal(groupId) {
        cardEditId = null;
        document.getElementById('cardModalTitle').textContent = 'کارت جدید';
        document.getElementById('editCardId').value = '';
        document.getElementById('cardGroupId').value = groupId;
        document.getElementById('cardTitle').value = '';
        document.getElementById('cardContent').value = '';
        document.getElementById('saveCardBtn').textContent = 'افزودن';
        document.getElementById('cardModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('cardTitle').focus(), 150);
    }

    function openEditCardModal(groupId, cardId) {
        const group = groups.find(g => g.id == groupId);
        if (!group) return;
        const card = group.cards.find(c => c.id == cardId);
        if (!card) return;
        
        cardEditId = cardId;
        document.getElementById('cardModalTitle').textContent = 'ویرایش کارت';
        document.getElementById('editCardId').value = cardId;
        document.getElementById('cardGroupId').value = groupId;
        document.getElementById('cardTitle').value = card.title;
        document.getElementById('cardContent').value = card.content || '';
        document.getElementById('saveCardBtn').textContent = 'ذخیره تغییرات';
        document.getElementById('cardModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('cardTitle').focus(), 150);
    }

    function closeCardModal() {
        document.getElementById('cardModal').classList.remove('show');
        document.body.style.overflow = '';
    }

    async function saveCard() {
        const title = document.getElementById('cardTitle').value.trim();
        if (!title) {
            showToast('لطفاً عنوان کارت را وارد کنید', 'error');
            return;
        }
        
        const groupId = document.getElementById('cardGroupId').value;
        const cardId = document.getElementById('editCardId').value;
        const action = cardId ? 'edit_card' : 'add_card';
        
        const btn = document.getElementById('saveCardBtn');
        btn.disabled = true;
        btn.textContent = 'در حال ذخیره...';
        
        try {
            let formData = new FormData();
            formData.append('action', action);
            if (cardId) formData.append('id', cardId);
            formData.append('group_id', groupId);
            formData.append('title', title);
            formData.append('content', document.getElementById('cardContent').value);
            
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                groups = result.groups;
                renderGroups();
                closeCardModal();
                showToast(cardId ? 'کارت ویرایش شد' : 'کارت اضافه شد', 'success');
            } else {
                showToast('خطا در ذخیره کارت', 'error');
            }
        } catch(e) {
            showToast('خطا در ارتباط با سرور', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = cardId ? 'ذخیره تغییرات' : 'افزودن';
        }
    }

    async function deleteCard(groupId, cardId) {
        if (!confirm('آیا از حذف این کارت مطمئن هستید؟')) return;
        
        try {
            let formData = new FormData();
            formData.append('action', 'delete_card');
            formData.append('id', cardId);
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                groups = result.groups;
                renderGroups();
                showToast('کارت حذف شد', 'success');
            }
        } catch(e) {
            showToast('خطا در حذف کارت', 'error');
        }
    }

    // ===== تبدیل به تسک =====
    function openConvertModal(cardId) {
        let card = null;
        for (let group of groups) {
            const found = group.cards.find(c => c.id == cardId);
            if (found) { card = found; break; }
        }
        if (!card) return;
        
        document.getElementById('convertCardId').value = cardId;
        document.getElementById('taskTitle').value = card.title;
        document.getElementById('taskDescription').value = card.content || '';
        document.getElementById('taskDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('taskTime').value = '12:00';
        document.getElementById('convertModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeConvertModal() {
        document.getElementById('convertModal').classList.remove('show');
        document.body.style.overflow = '';
    }

    async function convertToTask() {
        const title = document.getElementById('taskTitle').value.trim();
        if (!title) {
            showToast('لطفاً عنوان تسک را وارد کنید', 'error');
            return;
        }
        
        let formData = new FormData();
        formData.append('action', 'convert_to_task');
        formData.append('card_id', document.getElementById('convertCardId').value);
        formData.append('title', title);
        formData.append('description', document.getElementById('taskDescription').value);
        formData.append('date', document.getElementById('taskDate').value);
        formData.append('time', document.getElementById('taskTime').value);
        formData.append('priority', document.getElementById('taskPriority').value);
        formData.append('category', document.getElementById('taskCategory').value);
        formData.append('project', document.getElementById('taskProject').value);
        
        try {
            let response = await fetch(window.location.href, { method: 'POST', body: formData });
            let result = await response.json();
            if (result.success) {
                showToast('✅ تسک با موفقیت به Planner اضافه شد', 'success');
                closeConvertModal();
            } else {
                showToast(result.message || 'خطا در تبدیل به تسک', 'error');
            }
        } catch(e) {
            showToast('خطا در ارتباط با سرور', 'error');
        }
    }

    // ===== خروجی PDF =====
    async function exportPDF() {
        const btn = document.getElementById('pdfBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال تولید...';
        
        try {
            // مخفی کردن دکمه‌های اکشن
            document.querySelectorAll('.group-actions, .card-actions, .drag-handle, .add-card-btn, .edit-group-btn, .delete-group-btn, .edit-card-btn, .delete-card-btn, .convert-card-btn').forEach(el => {
                el.style.display = 'none';
            });
            
            // مخفی کردن دکمه‌های هدر (به جز دکمه PDF)
            document.querySelectorAll('.header-actions .btn-add-group, .header-actions .btn-planner, .header-actions .btn-back, .header-actions .theme-toggle-btn, .header-actions .btn-edit-toggle').forEach(el => {
                el.style.display = 'none';
            });
            
            // تغییر رنگ پس‌زمینه برای PDF
            const isLightMode = document.body.classList.contains('light-mode');
            const bgColor = isLightMode ? '#f0f2f5' : '#0f0c29';
            
            document.querySelectorAll('.group-card').forEach(el => {
                el.style.background = isLightMode ? '#ffffff' : 'rgba(255,255,255,0.05)';
                el.style.border = isLightMode ? '1px solid #e8e8e8' : '1px solid rgba(255,255,255,0.1)';
            });
            
            document.querySelectorAll('.card-item').forEach(el => {
                el.style.background = isLightMode ? '#fafafa' : 'rgba(255,255,255,0.03)';
                el.style.border = isLightMode ? '1px solid #f0f0f0' : '1px solid rgba(255,255,255,0.06)';
            });
            
            // گرفتن اسکرین‌شات
            const element = document.getElementById('pdfContent');
            const canvas = await html2canvas(element, {
                scale: 2,
                useCORS: true,
                backgroundColor: bgColor,
                logging: false,
                width: element.scrollWidth,
                height: element.scrollHeight,
                windowWidth: element.scrollWidth,
                windowHeight: element.scrollHeight
            });
            
            // ایجاد PDF با jsPDF
            const imgData = canvas.toDataURL('image/jpeg', 0.95);
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF('p', 'mm', 'a4');
            
            const pdfWidth = 210;
            const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
            
            if (pdfHeight <= 297) {
                pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight);
            } else {
                let heightLeft = pdfHeight;
                let position = 0;
                let pageNum = 1;
                
                while (heightLeft > 0) {
                    if (pageNum > 1) {
                        pdf.addPage();
                    }
                    
                    const pageCanvas = document.createElement('canvas');
                    const pageCtx = pageCanvas.getContext('2d');
                    const cropHeight = Math.min(heightLeft, 297);
                    pageCanvas.width = canvas.width;
                    pageCanvas.height = cropHeight * (canvas.width / pdfWidth);
                    
                    pageCtx.drawImage(
                        canvas,
                        0, position * (canvas.width / pdfWidth),
                        canvas.width, cropHeight * (canvas.width / pdfWidth),
                        0, 0,
                        canvas.width, cropHeight * (canvas.width / pdfWidth)
                    );
                    
                    const pageImgData = pageCanvas.toDataURL('image/jpeg', 0.95);
                    pdf.addImage(pageImgData, 'JPEG', 0, 0, pdfWidth, cropHeight);
                    
                    heightLeft -= 297;
                    position += 297;
                    pageNum++;
                }
            }
            
            const date = new Date().toISOString().split('T')[0];
            pdf.save(`LifePlan_${date}.pdf`);
            
            // بازیابی حالت
            document.querySelectorAll('.group-actions, .card-actions, .drag-handle, .add-card-btn, .edit-group-btn, .delete-group-btn, .edit-card-btn, .delete-card-btn, .convert-card-btn').forEach(el => {
                el.style.display = '';
            });
            document.querySelectorAll('.header-actions .btn-add-group, .header-actions .btn-planner, .header-actions .btn-back, .header-actions .theme-toggle-btn, .header-actions .btn-edit-toggle').forEach(el => {
                el.style.display = '';
            });
            document.querySelectorAll('.group-card').forEach(el => {
                el.style.background = '';
                el.style.border = '';
            });
            document.querySelectorAll('.card-item').forEach(el => {
                el.style.background = '';
                el.style.border = '';
            });
            
            showToast('✅ PDF با موفقیت دانلود شد', 'success');
        } catch(e) {
            console.error(e);
            showToast('خطا در تولید PDF', 'error');
            
            document.querySelectorAll('.group-actions, .card-actions, .drag-handle, .add-card-btn, .edit-group-btn, .delete-group-btn, .edit-card-btn, .delete-card-btn, .convert-card-btn').forEach(el => {
                el.style.display = '';
            });
            document.querySelectorAll('.header-actions .btn-add-group, .header-actions .btn-planner, .header-actions .btn-back, .header-actions .theme-toggle-btn, .header-actions .btn-edit-toggle').forEach(el => {
                el.style.display = '';
            });
            document.querySelectorAll('.group-card').forEach(el => {
                el.style.background = '';
                el.style.border = '';
            });
            document.querySelectorAll('.card-item').forEach(el => {
                el.style.background = '';
                el.style.border = '';
            });
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-file-pdf"></i> خروجی PDF';
        }
    }

    // ===== Event Listeners =====
    document.getElementById('saveGroupBtn').addEventListener('click', saveGroup);
    document.getElementById('saveCardBtn').addEventListener('click', saveCard);
    
    document.getElementById('titleInput').addEventListener('blur', saveTitle);
    document.getElementById('titleInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveTitle();
        }
        if (e.key === 'Escape') {
            const display = document.getElementById('titleDisplay');
            const input = document.getElementById('titleInput');
            display.style.display = 'inline-block';
            input.style.display = 'none';
        }
    });
    
    document.getElementById('groupModal').addEventListener('click', function(e) {
        if (e.target === this) closeGroupModal();
    });
    document.getElementById('cardModal').addEventListener('click', function(e) {
        if (e.target === this) closeCardModal();
    });
    document.getElementById('convertModal').addEventListener('click', function(e) {
        if (e.target === this) closeConvertModal();
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeGroupModal();
            closeCardModal();
            closeConvertModal();
        }
    });

    // ===== اتوماتیک فرمت زمان =====
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

    // ===== شروع =====
    loadTheme();
    loadTitle();
    loadGroups();
    </script>
</body>
</html>
