<?php
session_start();
date_default_timezone_set('Asia/Tehran');

$settingsFile = 'data/settings.json';
$usersFile = 'data/users.json';

// ==================== توابع ====================
function getSettings() {
    global $settingsFile;
    if (!file_exists($settingsFile)) {
        $default = ['registration_enabled' => true];
        file_put_contents($settingsFile, json_encode($default, JSON_PRETTY_PRINT));
        return $default;
    }
    $settings = json_decode(file_get_contents($settingsFile), true);
    return is_array($settings) ? $settings : ['registration_enabled' => true];
}

function getAllUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return [];
    $users = json_decode(file_get_contents($usersFile), true);
    return is_array($users) ? $users : [];
}

function getUserByEmail($email) {
    $users = getAllUsers();
    foreach ($users as $user) {
        if ($user['email'] === $email) {
            return $user;
        }
    }
    return null;
}

function getUserById($id) {
    $users = getAllUsers();
    foreach ($users as $user) {
        if ($user['id'] == $id) {
            return $user;
        }
    }
    return null;
}

function registerUser($name, $email, $password) {
    $users = getAllUsers();
    
    $settings = getSettings();
    if (!($settings['registration_enabled'] ?? true)) {
        return ['success' => false, 'message' => 'ثبت‌نام جدید در حال حاضر غیرفعال است'];
    }
    
    if (getUserByEmail($email)) {
        return ['success' => false, 'message' => 'این ایمیل قبلاً ثبت شده است'];
    }
    
    $newId = time() . rand(100, 999);
    $newUser = [
        'id' => $newId,
        'name' => htmlspecialchars(trim($name)),
        'email' => htmlspecialchars(trim($email)),
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => date('Y-m-d H:i:s'),
        'avatar_color' => '#' . dechex(rand(0x000000, 0xFFFFFF))
    ];
    
    $users[] = $newUser;
    file_put_contents($GLOBALS['usersFile'], json_encode($users, JSON_PRETTY_PRINT));
    
    return ['success' => true, 'user' => $newUser];
}

function loginUser($email, $password) {
    $user = getUserByEmail($email);
    if (!$user) {
        return ['success' => false, 'message' => 'ایمیل یا رمز عبور اشتباه است'];
    }
    
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'ایمیل یا رمز عبور اشتباه است'];
    }
    
    return ['success' => true, 'user' => $user];
}

// ==================== پردازش درخواست‌ها ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];
    
    if ($action === 'register') {
        $response = registerUser($_POST['name'] ?? '', $_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($response['success']) {
            $_SESSION['user_id'] = $response['user']['id'];
            $_SESSION['user_name'] = $response['user']['name'];
            $_SESSION['user_email'] = $response['user']['email'];
            unset($response['user']['password']);
        }
    }
    elseif ($action === 'login') {
        $response = loginUser($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($response['success']) {
            $_SESSION['user_id'] = $response['user']['id'];
            $_SESSION['user_name'] = $response['user']['name'];
            $_SESSION['user_email'] = $response['user']['email'];
            unset($response['user']['password']);
        }
    }
    elseif ($action === 'logout') {
        session_destroy();
        $response = ['success' => true];
    }
    elseif ($action === 'check_session') {
        if (isset($_SESSION['user_id'])) {
            $user = getUserById($_SESSION['user_id']);
            if ($user) {
                $response = ['success' => true, 'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'avatar_color' => $user['avatar_color']]];
            } else {
                session_destroy();
                $response = ['success' => false];
            }
        } else {
            $response = ['success' => false];
        }
    }
    
    echo json_encode($response);
    exit;
}

$isLoggedIn = isset($_SESSION['user_id']);
$currentUser = $isLoggedIn ? getUserById($_SESSION['user_id']) : null;
$registrationEnabled = getSettings()['registration_enabled'] ?? true;
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سیستم مدیریت زندگی | پلن</title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Vazirmatn', 'Vazir', 'Tahoma', sans-serif !important;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .main-container {
            max-width: 1200px;
            width: 100%;
        }
        
        .header {
            text-align: center;
            padding: 30px 0 40px;
        }
        
        .header h1 {
            font-size: 42px;
            font-weight: 700;
            color: white;
            text-shadow: 0 0 40px rgba(102, 126, 234, 0.3);
        }
        
        .header h1 span {
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .header p {
            color: rgba(255,255,255,0.6);
            font-size: 18px;
            margin-top: 10px;
        }
        
        /* ===== دکمه منو ===== */
        .menu-toggle-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1001;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            width: 48px;
            height: 48px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 22px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }
        
        .menu-toggle-btn:hover {
            background: rgba(255,255,255,0.2);
            transform: scale(1.05);
        }
        
        .menu-toggle-btn .fa-times { display: none; }
        .menu-toggle-btn.active .fa-bars { display: none; }
        .menu-toggle-btn.active .fa-times { display: block; }
        
        /* ===== اوورلی منو ===== */
        .side-menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .side-menu-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        /* ===== منوی کناری ===== */
        .side-menu {
            position: fixed;
            top: 0;
            right: -320px;
            width: 320px;
            height: 100vh;
            background: rgba(20, 20, 40, 0.95);
            backdrop-filter: blur(20px);
            z-index: 1000;
            padding: 20px 0;
            transition: right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            border-left: 1px solid rgba(255,255,255,0.05);
            box-shadow: -10px 0 40px rgba(0,0,0,0.3);
        }
        
        .side-menu.open {
            right: 0;
        }
        
        .side-menu-header {
            padding: 15px 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .side-menu-header .logo-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }
        
        .side-menu-header .logo-text {
            color: white;
            font-size: 18px;
            font-weight: 700;
        }
        
        .side-menu-header .logo-text span {
            color: #667eea;
        }
        
        .user-info-side {
            padding: 20px;
            background: rgba(255,255,255,0.03);
            margin: 10px 15px 15px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        
        .user-info-side .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }
        
        .user-info-side .details .name {
            color: white;
            font-size: 15px;
            font-weight: 600;
        }
        
        .user-info-side .details .email {
            color: rgba(255,255,255,0.5);
            font-size: 12px;
        }
        
        .user-info-side .logout-btn-side {
            margin-right: auto;
            background: rgba(220,53,69,0.2);
            border: none;
            color: #dc3545;
            padding: 6px 14px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .user-info-side .logout-btn-side:hover {
            background: rgba(220,53,69,0.3);
        }
        
        .menu-items {
            padding: 5px 10px;
        }
        
        .menu-item {
            margin-bottom: 2px;
        }
        
        .menu-item > a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 12px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.25s ease;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            position: relative;
        }
        
        .menu-item > a:hover {
            background: rgba(255,255,255,0.06);
            color: white;
        }
        
        .menu-item > a.active {
            background: rgba(102, 126, 234, 0.15);
            color: #667eea;
        }
        
        .menu-item > a .icon {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .menu-item > a .icon.home { background: rgba(102,126,234,0.2); color: #667eea; }
        .menu-item > a .icon.planner { background: rgba(102,126,234,0.2); color: #667eea; }
        .menu-item > a .icon.lifeplan { background: rgba(245,87,108,0.2); color: #f5576c; }
        .menu-item > a .icon.warroom { background: rgba(245,87,108,0.2); color: #f5576c; }
        .menu-item > a .icon.meaning { background: rgba(79,172,254,0.2); color: #4facfe; }
        .menu-item > a .icon.managers { background: rgba(67,233,123,0.2); color: #43e97b; }
        .menu-item > a .icon.inspection { background: rgba(250,112,154,0.2); color: #fa709a; }
        .menu-item > a .icon.nottodo { background: rgba(168,192,255,0.2); color: #a8c0ff; }
        .menu-item > a .icon.habits { background: rgba(245,158,11,0.2); color: #f59e0b; }
        .menu-item > a .icon.admin { background: rgba(220,53,69,0.2); color: #dc3545; }
        
        .menu-item > a .badge {
            margin-right: auto;
            font-size: 10px;
            padding: 2px 10px;
            border-radius: 20px;
            background: rgba(40,167,69,0.2);
            color: #28a745;
        }
        
        .menu-item > a .badge-soon {
            background: rgba(255,193,7,0.2);
            color: #ffc107;
        }
        
        .menu-footer {
            padding: 15px 20px;
            border-top: 1px solid rgba(255,255,255,0.05);
            margin-top: 10px;
        }
        
        .menu-footer .version {
            font-size: 11px;
            color: rgba(255,255,255,0.2);
            text-align: center;
        }
        
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .side-menu::-webkit-scrollbar {
            width: 3px;
        }
        
        .side-menu::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .side-menu::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
        }
        
        .auth-wrapper {
            max-width: 450px;
            margin: 0 auto 40px;
        }
        
        .auth-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 35px 30px;
        }
        
        .auth-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid rgba(255,255,255,0.1);
        }
        
        .auth-tab {
            flex: 1;
            padding: 12px;
            border: none;
            background: none;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            color: rgba(255,255,255,0.5);
            font-weight: 500;
            font-family: inherit;
        }
        
        .auth-tab.active {
            color: #667eea;
            border-bottom: 2px solid #667eea;
            margin-bottom: -2px;
        }
        
        .auth-form { display: none; }
        .auth-form.active { display: block; }
        
        .auth-form input {
            width: 100%;
            padding: 14px;
            margin-bottom: 15px;
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 15px;
            font-size: 14px;
            transition: all 0.3s;
            background: rgba(255,255,255,0.05);
            color: white;
            font-family: inherit;
        }
        
        .auth-form input::placeholder { color: rgba(255,255,255,0.4); }
        
        .auth-form input:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255,255,255,0.08);
        }
        
        .auth-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .auth-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .auth-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .error-msg {
            background: rgba(220,53,69,0.2);
            color: #ff6b6b;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 15px;
            font-size: 13px;
            display: none;
            border: 1px solid rgba(220,53,69,0.3);
        }
        
        .disabled-msg {
            background: rgba(255,193,7,0.15);
            color: #ffc107;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 15px;
            font-size: 13px;
            border: 1px solid rgba(255,193,7,0.2);
            text-align: center;
        }
        
        /* ===== منوی اصلی ===== */
        .main-menu {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-top: 30px;
        }
        
        .menu-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 30px 25px;
            text-align: center;
            transition: all 0.4s ease;
            cursor: pointer;
            text-decoration: none;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .menu-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102,126,234,0.1), rgba(118,75,162,0.1));
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        
        .menu-card:hover::before { opacity: 1; }
        
        .menu-card:hover {
            transform: translateY(-8px);
            border-color: rgba(102,126,234,0.5);
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }
        
        .menu-card .icon {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 28px;
            position: relative;
            z-index: 1;
        }
        
        .menu-card .icon.planner { background: linear-gradient(135deg, #667eea, #764ba2); }
        .menu-card .icon.lifeplan { background: linear-gradient(135deg, #f093fb, #f5576c); }
        .menu-card .icon.warroom { background: linear-gradient(135deg, #f5576c, #f093fb); }
        .menu-card .icon.meaning { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .menu-card .icon.managers { background: linear-gradient(135deg, #43e97b, #38f9d7); }
        .menu-card .icon.inspection { background: linear-gradient(135deg, #fa709a, #fee140); }
        .menu-card .icon.nottodo { background: linear-gradient(135deg, #a8c0ff, #3f2b96); }
        
        .menu-card h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 6px;
            position: relative;
            z-index: 1;
        }
        
        .menu-card p {
            font-size: 13px;
            color: rgba(255,255,255,0.6);
            position: relative;
            z-index: 1;
        }
        
        .menu-card .badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(255,255,255,0.1);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            color: rgba(255,255,255,0.5);
        }
        
        .menu-card .coming-soon {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255,193,7,0.2);
            color: #ffc107;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .menu-card .active-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(40,167,69,0.2);
            color: #28a745;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .menu-card.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .user-status {
            text-align: center;
            color: rgba(255,255,255,0.5);
            font-size: 14px;
            margin-top: 25px;
            padding-bottom: 10px;
        }
        
        .user-status .user-name {
            color: white;
            font-weight: 500;
        }
        
        .logout-link {
            color: #dc3545 !important;
            cursor: pointer;
            background: none;
            border: none;
            font-family: inherit;
            font-size: 14px;
            text-decoration: underline;
        }
        
        .logout-link:hover { color: #ff6b6b !important; }
        
        .user-avatar-mini {
            display: inline-block;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            text-align: center;
            line-height: 32px;
            font-weight: bold;
            font-size: 14px;
            margin-left: 8px;
            vertical-align: middle;
        }
        
        @media (max-width: 992px) {
            .main-menu {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .header h1 { font-size: 32px; }
            .main-menu {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            .menu-card { padding: 20px 15px; }
            .menu-card .icon { width: 50px; height: 50px; font-size: 22px; }
            .menu-card h3 { font-size: 15px; }
            .menu-card p { font-size: 12px; }
            .side-menu { width: 280px; }
        }
        
        @media (max-width: 480px) {
            .main-menu {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .header h1 { font-size: 28px; }
            .side-menu { width: 100%; right: -100%; }
        }
    </style>
</head>
<body>
    <!-- ===== دکمه باز کردن منو ===== -->
    <button class="menu-toggle-btn" id="menuToggleBtn" onclick="toggleSideMenu()">
        <i class="fas fa-bars"></i>
        <i class="fas fa-times"></i>
    </button>
    
    <!-- ===== اوورلی منو ===== -->
    <div class="side-menu-overlay" id="sideMenuOverlay" onclick="closeSideMenu()"></div>
    
    <!-- ===== منوی کناری ===== -->
    <div class="side-menu" id="sideMenu">
        <div class="side-menu-header">
            <div class="logo-icon"><i class="fas fa-rocket"></i></div>
            <div class="logo-text">سیستم <span>زندگی</span></div>
        </div>
        
        <?php if ($isLoggedIn && $currentUser): ?>
            <div class="user-info-side">
                <div class="avatar" style="background: <?php echo $currentUser['avatar_color'] ?? '#667eea'; ?>">
                    <?php echo mb_substr($currentUser['name'], 0, 1); ?>
                </div>
                <div class="details">
                    <div class="name"><?php echo htmlspecialchars($currentUser['name']); ?></div>
                    <div class="email"><?php echo htmlspecialchars($currentUser['email']); ?></div>
                </div>
                <button class="logout-btn-side" onclick="logout()"><i class="fas fa-sign-out-alt"></i></button>
            </div>
        <?php else: ?>
            <div class="user-info-side" style="justify-content:center;">
                <div style="color:rgba(255,255,255,0.5); font-size:13px;"><i class="fas fa-user"></i> وارد شوید</div>
            </div>
        <?php endif; ?>
        
        <div class="menu-items">
            <!-- صفحه اصلی -->
            <div class="menu-item">
                <a href="index.php" class="menu-item-link <?php echo ($currentDir === 'root' || $currentDir === '') ? 'active' : ''; ?>">
                    <span class="icon home"><i class="fas fa-home"></i></span>
                    <span>🏠 صفحه اصلی</span>
                </a>
            </div>
            
            <!-- Planner -->
            <div class="menu-item">
                <a href="planner/index.php" class="menu-item-link <?php echo ($currentDir === 'planner') ? 'active' : ''; ?>">
                    <span class="icon planner"><i class="fas fa-tasks"></i></span>
                    <span>📋 Planner</span>
                    <span class="badge">فعال</span>
                </a>
            </div>
            
            <!-- Lifeplan -->
            <div class="menu-item">
                <a href="lifeplan/index.php" class="menu-item-link <?php echo ($currentDir === 'lifeplan') ? 'active' : ''; ?>">
                    <span class="icon lifeplan"><i class="fas fa-compass"></i></span>
                    <span>🧭 Lifeplan</span>
                    <span class="badge">فعال</span>
                </a>
            </div>
            
            <!-- War Room -->
            <div class="menu-item">
                <a href="wr/index.php" class="menu-item-link <?php echo ($currentDir === 'wr') ? 'active' : ''; ?>">
                    <span class="icon warroom"><i class="fas fa-bullseye"></i></span>
                    <span>⚔️ اتاق جنگ</span>
                    <span class="badge">فعال</span>
                </a>
            </div>
            
            <!-- Meaning -->
            <div class="menu-item">
                <a href="meaning/index.php" class="menu-item-link">
                    <span class="icon meaning"><i class="fas fa-brain"></i></span>
                    <span>💡 Meaning</span>
                    <span class="badge badge-soon">به زودی</span>
                </a>
            </div>
            
            <!-- Middle Managers -->
            <div class="menu-item">
                <a href="middlemanagers/index.php" class="menu-item-link">
                    <span class="icon managers"><i class="fas fa-users-cog"></i></span>
                    <span>👔 Middle Managers</span>
                    <span class="badge badge-soon">به زودی</span>
                </a>
            </div>
            
            <!-- Inspection -->
            <div class="menu-item">
                <a href="inspection/index.php" class="menu-item-link">
                    <span class="icon inspection"><i class="fas fa-search"></i></span>
                    <span>🔍 Inspection</span>
                    <span class="badge badge-soon">به زودی</span>
                </a>
            </div>
            
            <!-- Not To Do List -->
            <div class="menu-item">
                <a href="nottodolist/index.php" class="menu-item-link">
                    <span class="icon nottodo"><i class="fas fa-ban"></i></span>
                    <span>🚫 Not To Do List</span>
                    <span class="badge badge-soon">به زودی</span>
                </a>
            </div>
            
            <!-- Habits -->
            <div class="menu-item">
                <a href="planner/habits.php" class="menu-item-link <?php echo ($currentDir === 'planner' && $currentPage === 'habits.php') ? 'active' : ''; ?>">
                    <span class="icon habits"><i class="fas fa-fire"></i></span>
                    <span>🔥 عادت‌ها</span>
                    <span class="badge">فعال</span>
                </a>
            </div>
            
            <!-- Notes -->
            <div class="menu-item">
                <a href="notes.php" class="menu-item-link <?php echo ($currentPage === 'notes.php') ? 'active' : ''; ?>">
                    <span class="icon" style="background:rgba(255,193,7,0.2); color:#ffc107;"><i class="fas fa-sticky-note"></i></span>
                    <span>📝 نوت‌ها</span>
                    <span class="badge">فعال</span>
                </a>
            </div>
            
            <!-- Admin (فقط ادمین) -->
            <?php if ($currentUser && $currentUser['email'] === 'admin@example.com'): ?>
                <div class="menu-item">
                    <a href="planner/admin.php" class="menu-item-link <?php echo ($currentDir === 'planner' && $currentPage === 'admin.php') ? 'active' : ''; ?>">
                        <span class="icon admin"><i class="fas fa-shield-alt"></i></span>
                        <span>🛡️ مدیریت</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="menu-footer">
            <div class="version">نسخه 1.0 | سیستم مدیریت زندگی</div>
        </div>
    </div>
    
    <!-- ===== محتوای اصلی ===== -->
    <div class="main-content">
        <div class="header">
            <h1>🚀 <span>سیستم مدیریت زندگی</span></h1>
            <p>همه ابزارهای مدیریت زندگی در یک مکان</p>
        </div>
        
        <?php if ($isLoggedIn && $currentUser): ?>
            <div class="user-status">
                <span class="user-avatar-mini" style="background: <?php echo $currentUser['avatar_color'] ?? '#667eea'; ?>">
                    <?php echo mb_substr($currentUser['name'], 0, 1); ?>
                </span>
                <span>خوش آمدی، <span class="user-name"><?php echo htmlspecialchars($currentUser['name']); ?></span></span>
                <span style="margin: 0 10px;">|</span>
                <button class="logout-link" onclick="logout()">خروج</button>
            </div>
            
            <div class="main-menu">
                <!-- Planner -->
                <a href="planner/index.php" class="menu-card">
                    <div class="active-badge">فعال</div>
                    <div class="icon planner"><i class="fas fa-tasks"></i></div>
                    <h3>📋 Planner</h3>
                    <p>برنامه‌ریزی روزانه، مدیریت تسک‌ها و پروژه‌ها</p>
                </a>
                
                <!-- Lifeplan -->
                <a href="lifeplan/index.php" class="menu-card">
                    <div class="active-badge">فعال</div>
                    <div class="icon lifeplan"><i class="fas fa-compass"></i></div>
                    <h3>🧭 Lifeplan</h3>
                    <p>برنامه‌ریزی اهداف بلندمدت و مسیر زندگی</p>
                </a>
                
                <!-- War Room -->
                <a href="wr/index.php" class="menu-card">
                    <div class="active-badge">فعال</div>
                    <div class="icon warroom"><i class="fas fa-bullseye"></i></div>
                    <h3>⚔️ اتاق جنگ</h3>
                    <p>مدیریت استراتژیک اهداف و ماموریت‌های بزرگ</p>
                </a>
                
                <!-- Meaning -->
                <a href="meaning/index.php" class="menu-card disabled">
                    <div class="coming-soon">به زودی</div>
                    <div class="icon meaning"><i class="fas fa-brain"></i></div>
                    <h3>💡 Meaning</h3>
                    <p>پیدا کردن معنا و هدف در زندگی</p>
                </a>
                
                <!-- Middle Managers -->
                <a href="middlemanagers/index.php" class="menu-card disabled">
                    <div class="coming-soon">به زودی</div>
                    <div class="icon managers"><i class="fas fa-users-cog"></i></div>
                    <h3>👔 Middle Managers</h3>
                    <p>ابزارهای مدیریت میانی و تیم‌سازی</p>
                </a>
                
                <!-- Inspection -->
                <a href="inspection/index.php" class="menu-card disabled">
                    <div class="coming-soon">به زودی</div>
                    <div class="icon inspection"><i class="fas fa-search"></i></div>
                    <h3>🔍 Inspection</h3>
                    <p>بازرسی و ارزیابی عملکرد</p>
                </a>
                
                <!-- Not To Do List -->
                <a href="nottodolist/index.php" class="menu-card disabled">
                    <div class="coming-soon">به زودی</div>
                    <div class="icon nottodo"><i class="fas fa-ban"></i></div>
                    <h3>🚫 Not To Do List</h3>
                    <p>لیست کارهایی که نباید انجام داد</p>
                </a>
            </div>
            
        <?php else: ?>
            <div class="auth-wrapper">
                <div class="auth-card">
                    <div class="auth-tabs">
                        <button class="auth-tab active" data-tab="login">ورود</button>
                        <button class="auth-tab" data-tab="register">ثبت‌نام</button>
                    </div>
                    
                    <div id="errorMsg" class="error-msg"></div>
                    
                    <?php if (!$registrationEnabled): ?>
                        <div class="disabled-msg">
                            <i class="fas fa-exclamation-triangle"></i> ثبت‌نام جدید در حال حاضر غیرفعال است
                        </div>
                    <?php endif; ?>
                    
                    <form id="loginForm" class="auth-form active" onsubmit="return false;">
                        <input type="email" id="loginEmail" placeholder="ایمیل" required>
                        <input type="password" id="loginPassword" placeholder="رمز عبور" required>
                        <button type="submit" class="auth-btn" id="loginBtn">ورود</button>
                    </form>
                    
                    <form id="registerForm" class="auth-form" onsubmit="return false;">
                        <input type="text" id="registerName" placeholder="نام کامل" required <?php echo !$registrationEnabled ? 'disabled' : ''; ?>>
                        <input type="email" id="registerEmail" placeholder="ایمیل" required <?php echo !$registrationEnabled ? 'disabled' : ''; ?>>
                        <input type="password" id="registerPassword" placeholder="رمز عبور" required <?php echo !$registrationEnabled ? 'disabled' : ''; ?>>
                        <input type="password" id="registerConfirmPassword" placeholder="تکرار رمز عبور" required <?php echo !$registrationEnabled ? 'disabled' : ''; ?>>
                        <button type="submit" class="auth-btn" id="registerBtn" <?php echo !$registrationEnabled ? 'disabled' : ''; ?>>
                            <?php echo $registrationEnabled ? 'ثبت‌نام' : 'ثبت‌نام غیرفعال'; ?>
                        </button>
                    </form>
                </div>
            </div>
            
            <div style="text-align:center; color:rgba(255,255,255,0.3); font-size:13px; margin-top:10px;">
                <i class="fas fa-lock"></i> برای دسترسی به ابزارها، وارد شوید
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // ===== منوی کناری =====
        function toggleSideMenu() {
            const menu = document.getElementById('sideMenu');
            const overlay = document.getElementById('sideMenuOverlay');
            const btn = document.getElementById('menuToggleBtn');
            
            menu.classList.toggle('open');
            overlay.classList.toggle('active');
            btn.classList.toggle('active');
        }
        
        function closeSideMenu() {
            document.getElementById('sideMenu').classList.remove('open');
            document.getElementById('sideMenuOverlay').classList.remove('active');
            document.getElementById('menuToggleBtn').classList.remove('active');
        }
        
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('sideMenu');
            const btn = document.getElementById('menuToggleBtn');
            if (menu && menu.classList.contains('open') && 
                !menu.contains(e.target) && 
                !btn.contains(e.target)) {
                closeSideMenu();
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSideMenu();
            }
        });
        
        // ===== تغییر تب‌ها =====
        document.querySelectorAll('.auth-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                document.querySelectorAll('.auth-form').forEach(form => form.classList.remove('active'));
                document.getElementById(this.dataset.tab + 'Form').classList.add('active');
                document.getElementById('errorMsg').style.display = 'none';
            });
        });
        
        // ===== لاگین =====
        document.getElementById('loginBtn')?.addEventListener('click', async function() {
            const email = document.getElementById('loginEmail').value.trim();
            const password = document.getElementById('loginPassword').value;
            const errorMsg = document.getElementById('errorMsg');
            
            if (!email || !password) {
                errorMsg.innerText = 'لطفاً ایمیل و رمز عبور را وارد کنید';
                errorMsg.style.display = 'block';
                return;
            }
            
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ورود...';
            
            let formData = new FormData();
            formData.append('action', 'login');
            formData.append('email', email);
            formData.append('password', password);
            
            try {
                let response = await fetch(window.location.href, { method: 'POST', body: formData });
                let result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    errorMsg.innerText = result.message;
                    errorMsg.style.display = 'block';
                    this.disabled = false;
                    this.innerHTML = 'ورود';
                }
            } catch(e) {
                errorMsg.innerText = 'خطا در ارتباط با سرور';
                errorMsg.style.display = 'block';
                this.disabled = false;
                this.innerHTML = 'ورود';
            }
        });
        
        // ===== ثبت‌نام =====
        document.getElementById('registerBtn')?.addEventListener('click', async function() {
            const name = document.getElementById('registerName').value.trim();
            const email = document.getElementById('registerEmail').value.trim();
            const password = document.getElementById('registerPassword').value;
            const confirmPassword = document.getElementById('registerConfirmPassword').value;
            const errorMsg = document.getElementById('errorMsg');
            
            if (!name || !email || !password || !confirmPassword) {
                errorMsg.innerText = 'لطفاً تمام فیلدها را پر کنید';
                errorMsg.style.display = 'block';
                return;
            }
            
            if (password !== confirmPassword) {
                errorMsg.innerText = 'رمز عبور و تکرار آن مطابقت ندارند';
                errorMsg.style.display = 'block';
                return;
            }
            
            if (password.length < 4) {
                errorMsg.innerText = 'رمز عبور باید حداقل ۴ کاراکتر باشد';
                errorMsg.style.display = 'block';
                return;
            }
            
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ثبت‌نام...';
            
            let formData = new FormData();
            formData.append('action', 'register');
            formData.append('name', name);
            formData.append('email', email);
            formData.append('password', password);
            
            try {
                let response = await fetch(window.location.href, { method: 'POST', body: formData });
                let result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    errorMsg.innerText = result.message;
                    errorMsg.style.display = 'block';
                    this.disabled = false;
                    this.innerHTML = 'ثبت‌نام';
                }
            } catch(e) {
                errorMsg.innerText = 'خطا در ارتباط با سرور';
                errorMsg.style.display = 'block';
                this.disabled = false;
                this.innerHTML = 'ثبت‌نام';
            }
        });
        
        // ===== خروج =====
        function logout() {
            let formData = new FormData();
            formData.append('action', 'logout');
            fetch(window.location.href, { method: 'POST', body: formData }).then(() => {
                location.reload();
            });
        }
    </script>
</body>
</html>
