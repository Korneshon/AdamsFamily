<?php

require_once 'config.php';


if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';
$user = [];


try {
    $stmt = $pdo->prepare("SELECT id, username, email, full_name, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $error = 'Пользователь не найден';
    }
} catch (PDOException $e) {
    error_log("Error getting user data: " . $e->getMessage());
    $error = 'Ошибка при загрузке данных пользователя';
}


$user_stats = [
    'projects_count' => 0,
    'reports_count' => 0,
    'active_projects' => 0,
    'last_login' => $_SESSION['last_login'] ?? 'Неизвестно'
];

try {
   
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE created_by = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_stats['projects_count'] = $stmt->fetchColumn();
    
    // Количество отчетов пользователя
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE created_by = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_stats['reports_count'] = $stmt->fetchColumn();
    
    // Активные проекты
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE created_by = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    $user_stats['active_projects'] = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Error getting user stats: " . $e->getMessage());
}

// Обработка обновления профиля
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Валидация данных
    if (empty($full_name)) {
        $error = 'Полное имя обязательно для заполнения';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Введите корректный email адрес';
    } else {
        // Проверяем, не занят ли email другим пользователем
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $error = 'Пользователь с таким email уже существует';
            } else {
                // Обновляем данные пользователя
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                if ($stmt->execute([$full_name, $email, $_SESSION['user_id']])) {
                    $success = 'Профиль успешно обновлен';
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                    // Обновляем данные пользователя
                    $user['full_name'] = $full_name;
                    $user['email'] = $email;
                } else {
                    $error = 'Ошибка при обновлении профиля';
                }
            }
        } catch (PDOException $e) {
            error_log("Error updating profile: " . $e->getMessage());
            $error = 'Ошибка при обновлении профиля';
        }
    }
}

// Обработка смены пароля
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Валидация пароля
    if (empty($current_password)) {
        $error = 'Текущий пароль обязателен';
    } elseif (empty($new_password)) {
        $error = 'Новый пароль обязателен';
    } elseif (strlen($new_password) < 6) {
        $error = 'Новый пароль должен содержать минимум 6 символов';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Новый пароль и подтверждение не совпадают';
    } else {
        // Проверяем текущий пароль
        try {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($current_user && password_verify($current_password, $current_user['password'])) {
                // Обновляем пароль
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$new_password_hash, $_SESSION['user_id']])) {
                    $success = 'Пароль успешно изменен';
                    // Очищаем поля пароля
                    $_POST['current_password'] = $_POST['new_password'] = $_POST['confirm_password'] = '';
                } else {
                    $error = 'Ошибка при изменении пароля';
                }
            } else {
                $error = 'Текущий пароль неверен';
            }
        } catch (PDOException $e) {
            error_log("Error changing password: " . $e->getMessage());
            $error = 'Ошибка при изменении пароля';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль пользователя - Ростелеком</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --primary: #7800ff;
        --primary-light: #9d4dff;
        --primary-dark: #5a00cc;
        --secondary: #fe4e12;
        --secondary-light: #ff7a4d;
        --secondary-dark: #cc3e0e;
        --accent: #00e5ff;
        --accent-light: #66f0ff;
        --accent-dark: #00b8cc;
        --bg-white: #ffffff;
        --bg-gray: #f8fafc;
        --text-dark: #1e293b;
        --text-light: #64748b;
        --border-light: #e2e8f0;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
        background: linear-gradient(135deg, var(--bg-white) 0%, var(--bg-gray) 100%);
        min-height: 100vh;
        padding: 20px;
        color: var(--text-dark);
    }

    .container {
        max-width: 1000px;
        margin: 0 auto;
    }

    /* Анимированный заголовок */
    .nav-bar {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        padding: 25px 30px;
        border-radius: 20px 20px 0 0;
        box-shadow: 0 10px 30px rgba(120, 0, 255, 0.2);
        position: relative;
        overflow: hidden;
    }

    .nav-bar::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 200%;
        background: radial-gradient(circle, var(--accent-light) 0%, transparent 70%);
        opacity: 0.1;
    }

    .nav-bar h1 {
        margin: 0;
        font-size: 1.8rem;
        font-weight: 700;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }

    .breadcrumb {
        background: var(--bg-white);
        padding: 18px 30px;
        border-bottom: 1px solid var(--border-light);
    }

    .breadcrumb a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s ease;
    }

    .breadcrumb a:hover {
        color: var(--secondary);
        text-decoration: none;
    }

    .profile-content {
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 30px;
        margin-top: 25px;
    }

    @media (max-width: 768px) {
        .profile-content {
            grid-template-columns: 1fr;
        }
    }

    .profile-sidebar {
        background: var(--bg-white);
        padding: 30px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        height: fit-content;
        border: 1px solid var(--border-light);
        transition: transform 0.3s ease;
    }

    .profile-sidebar:hover {
        transform: translateY(-2px);
    }

    .profile-main {
        background: var(--bg-white);
        padding: 35px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        border: 1px solid var(--border-light);
        transition: transform 0.3s ease;
    }

    .profile-main:hover {
        transform: translateY(-2px);
    }

    .user-avatar {
        text-align: center;
        margin-bottom: 30px;
    }

    .avatar-circle {
        width: 140px;
        height: 140px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        color: white;
        font-size: 3.5rem;
        font-weight: bold;
        box-shadow: 0 8px 25px rgba(120, 0, 255, 0.3);
        transition: transform 0.3s ease;
    }

    .avatar-circle:hover {
        transform: scale(1.05);
    }

    .user-info h2 {
        color: var(--primary);
        margin-bottom: 8px;
        text-align: center;
        font-size: 1.5rem;
        font-weight: 700;
    }

    .user-role {
        background: linear-gradient(135deg, var(--secondary) 0%, var(--secondary-dark) 100%);
        color: white;
        padding: 8px 20px;
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 600;
        display: inline-block;
        margin-bottom: 25px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 15px rgba(254, 78, 18, 0.3);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 18px;
        margin-top: 25px;
    }

    .stat-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 18px 20px;
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        border-radius: 12px;
        border-left: 4px solid var(--primary);
        transition: transform 0.3s ease;
    }

    .stat-item:hover {
        transform: translateX(5px);
    }

    .stat-label {
        color: var(--text-dark);
        font-size: 0.95rem;
        font-weight: 500;
    }

    .stat-value {
        font-weight: 700;
        color: var(--primary);
        font-size: 1.2rem;
    }

    .form-section {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        padding: 30px;
        border-radius: 20px;
        margin-bottom: 30px;
        border-left: 5px solid var(--primary);
        transition: transform 0.3s ease;
    }

    .form-section:hover {
        transform: translateY(-2px);
    }

    .form-section h3 {
        color: var(--primary);
        margin-bottom: 25px;
        font-size: 1.4rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .form-group {
        margin-bottom: 25px;
    }

    .form-group label {
        display: block;
        margin-bottom: 10px;
        color: var(--text-dark);
        font-weight: 600;
        font-size: 0.95rem;
    }

    .required::after {
        content: " *";
        color: var(--danger);
    }

    .form-control {
        width: 100%;
        padding: 16px 20px;
        border: 2px solid var(--border-light);
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: var(--bg-white);
    }

    .form-control:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 4px rgba(120, 0, 255, 0.1);
        transform: translateY(-1px);
    }

    .form-control:disabled {
        background-color: var(--bg-gray);
        color: var(--text-light);
        cursor: not-allowed;
    }

    /* Улучшенные кнопки */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 16px 32px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        margin-right: 12px;
        box-shadow: 0 6px 20px rgba(120, 0, 255, 0.3);
    }

    .btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(120, 0, 255, 0.4);
        background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
    }

    .btn-success {
        background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
    }

    .btn-success:hover {
        background: linear-gradient(135deg, #34d399 0%, var(--success) 100%);
    }

    .btn-outline {
        background: transparent;
        border: 2px solid var(--primary);
        color: var(--primary);
        box-shadow: none;
    }

    .btn-outline:hover {
        background: var(--primary);
        color: white;
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(120, 0, 255, 0.3);
    }

    .error-message {
        color: var(--danger);
        padding: 20px;
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        border-radius: 12px;
        margin-bottom: 25px;
        border-left: 5px solid var(--danger);
        font-weight: 500;
    }

    .success-message {
        color: #065f46;
        padding: 20px;
        background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        border-radius: 12px;
        margin-bottom: 25px;
        border-left: 5px solid var(--success);
        font-weight: 500;
    }

    h1 {
        margin-bottom: 25px;
        font-size: 2.2rem;
        font-weight: 700;
    }

    .help-text {
        font-size: 0.9rem;
        color: var(--text-light);
        margin-top: 8px;
    }

    .password-strength {
        height: 6px;
        background: var(--border-light);
        border-radius: 3px;
        margin-top: 8px;
        overflow: hidden;
    }

    .password-strength-fill {
        height: 100%;
        width: 0%;
        transition: width 0.3s, background-color 0.3s;
        border-radius: 3px;
    }

    .strength-weak { background: var(--danger); width: 33%; }
    .strength-medium { background: var(--warning); width: 66%; }
    .strength-strong { background: var(--success); width: 100%; }

    .security-info {
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        padding: 25px;
        border-radius: 15px;
        margin-top: 25px;
        border-left: 4px solid var(--primary);
    }

    .security-info h4 {
        color: var(--primary);
        margin-bottom: 15px;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .security-list {
        list-style: none;
        padding-left: 0;
    }

    .security-list li {
        padding: 8px 0;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
    }

    .security-list li i {
        color: var(--success);
        font-size: 1.1rem;
    }

    .activity-log {
        margin-top: 35px;
    }

    .activity-item {
        padding: 20px 0;
        border-bottom: 2px solid var(--border-light);
        display: flex;
        align-items: center;
        gap: 15px;
        transition: background-color 0.3s ease;
    }

    .activity-item:hover {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        border-radius: 12px;
        padding: 20px;
        margin: 0 -20px;
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .activity-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
        box-shadow: 0 4px 15px rgba(120, 0, 255, 0.3);
    }

    .activity-content {
        flex: 1;
    }

    .activity-title {
        font-weight: 600;
        color: var(--text-dark);
        font-size: 1rem;
    }

    .activity-time {
        font-size: 0.9rem;
        color: var(--text-light);
        margin-top: 4px;
    }

    .tab-navigation {
        display: flex;
        border-bottom: 2px solid var(--border-light);
        margin-bottom: 30px;
        border-radius: 12px 12px 0 0;
        overflow: hidden;
    }

    .tab-button {
        padding: 16px 30px;
        background: var(--bg-gray);
        border: none;
        border-bottom: 3px solid transparent;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        color: var(--text-light);
        flex: 1;
        text-align: center;
    }

    .tab-button.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
        background: var(--bg-white);
    }

    .tab-button:hover:not(.active) {
        color: var(--primary);
        background: #f1f5f9;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .user-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }

    @media (max-width: 480px) {
        .user-details {
            grid-template-columns: 1fr;
        }
    }

    .detail-item {
        padding: 20px;
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        border-radius: 12px;
        border-left: 4px solid var(--primary);
        transition: transform 0.3s ease;
    }

    .detail-item:hover {
        transform: translateX(5px);
    }

    .detail-label {
        font-size: 0.9rem;
        color: var(--text-light);
        margin-bottom: 8px;
        font-weight: 500;
    }

    .detail-value {
        font-weight: 600;
        color: var(--text-dark);
        font-size: 1.1rem;
    }

    /* Анимации загрузки */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .profile-sidebar, .profile-main {
        animation: fadeInUp 0.6s ease-out;
    }

    .profile-main { animation-delay: 0.1s; }

    /* Адаптивность */
    @media (max-width: 768px) {
        body {
            padding: 15px;
        }
        
        .nav-bar {
            padding: 20px;
        }
        
        .profile-sidebar, .profile-main {
            padding: 25px;
        }
        
        .tab-navigation {
            flex-direction: column;
        }
        
        .tab-button {
            border-bottom: none;
            border-right: 3px solid transparent;
        }
        
        .tab-button.active {
            border-right-color: var(--primary);
            border-bottom: none;
        }
    }

    @media (max-width: 480px) {
        .avatar-circle {
            width: 120px;
            height: 120px;
            font-size: 2.8rem;
        }
        
        .form-section {
            padding: 20px;
        }
        
        .btn {
            width: 100%;
            justify-content: center;
            margin-bottom: 10px;
        }
       
    }

</style>
</head>
<body>
    <div class="container">

        <div class="nav-bar">
            <h1><i color = "white"class="fas fa-user-circle"></i> Ростелеком - Профиль пользователя</h1>
        </div>

        <div class="breadcrumb">
            <a href="index.php"><i class="fas fa-home"></i> Дашборд</a> &raquo; 
            <span><i class="fas fa-user"></i> Мой профиль</span>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="profile-content">

            <div class="profile-sidebar">
                <div class="user-avatar">
                    <div class="avatar-circle">
                        <?php 
  
                            echo strtoupper(mb_substr($user['full_name'] ?? 'U', 0, 1)); 
                        ?>
                    </div>
                    <h2><?php echo htmlspecialchars($user['full_name'] ?? 'Пользователь'); ?></h2>
                    <div class="user-role">
                        <?php 
                            $role_names = [
                                'admin' => 'Администратор',
                                'analyst' => 'Аналитик',
                                'user' => 'Пользователь'
                            ];
                            echo $role_names[$user['role']] ?? $user['role'];
                        ?>
                     
                    </div><br>
                       <a href="logout.php">Выйти из аккаунта</a>
                </div>

                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-label">Проекты</span>
                        <span class="stat-value"><?php echo $user_stats['projects_count']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Отчеты</span>
                        <span class="stat-value"><?php echo $user_stats['reports_count']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Активные проекты</span>
                        <span class="stat-value"><?php echo $user_stats['active_projects']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">В системе с</span>
                        <span class="stat-value">
                            <?php 
                                if (!empty($user['created_at'])) {
                                    echo date('d.m.Y', strtotime($user['created_at']));
                                } else {
                                    echo 'Неизвестно';
                                }
                            ?>
                        </span>
                    </div>
                </div>

                <div class="security-info">
                    <h4><i class="fas fa-shield-alt"></i> Безопасность аккаунта</h4>
                    <ul class="security-list">
                        <li><i class="fas fa-check"></i> Email подтвержден</li>
                        <li><i class="fas fa-check"></i> Пароль установлен</li>
                        <li><i class="fas fa-check"></i> Аккаунт активен</li>
                    </ul>
                </div>

                <div style="margin-top: 20px; text-align: center;">
                    <a href="index.php" class="btn btn-outline" style="width: 100%; justify-content: center;">
                        <i class="fas fa-arrow-left"></i> Назад к дашборду
                    </a>
                </div>
            </div>


            <div class="profile-main">
                <h1>Управление профилем</h1>

       
                <div class="tab-navigation">
                    <button class="tab-button active" onclick="openTab('profile-tab')">
                        <i class="fas fa-user-edit"></i> Редактирование профиля
                    </button>
                    <button class="tab-button" onclick="openTab('password-tab')">
                        <i class="fas fa-key"></i> Смена пароля
                    </button>
                    <button class="tab-button" onclick="openTab('info-tab')">
                        <i class="fas fa-info-circle"></i> Информация
                    </button>
                </div>

         
                <div id="profile-tab" class="tab-content active">
                    <div class="form-section">
                        <h3><i class="fas fa-user-edit"></i> Основная информация</h3>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="user-details">
                                <div class="detail-item">
                                    <div class="detail-label">Имя пользователя</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($user['username'] ?? ''); ?></div>
                                    <div class="help-text">Имя пользователя нельзя изменить</div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Роль в системе</div>
                                    <div class="detail-value">
                                        <?php 
                                            echo $role_names[$user['role']] ?? $user['role'];
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="full_name" class="required">Полное имя</label>
                                <input type="text" id="full_name" name="full_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" 
                                       placeholder="Введите ваше полное имя" required>
                                <div class="help-text">Это имя будет отображаться в системе</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="required">Email адрес</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                       placeholder="your@email.com" required>
                                <div class="help-text">На этот email будут приходить уведомления</div>
                            </div>

                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Сохранить изменения
                            </button>
                        </form>
                    </div>
                </div>

                <div id="password-tab" class="tab-content">
                    <div class="form-section">
                        <h3><i class="fas fa-key"></i> Смена пароля</h3>
                        
                        <form method="POST" action="" id="passwordForm">
                            <input type="hidden" name="change_password" value="1">
                            
                            <div class="form-group">
                                <label for="current_password" class="required">Текущий пароль</label>
                                <input type="password" id="current_password" name="current_password" class="form-control" 
                                       placeholder="Введите текущий пароль" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password" class="required">Новый пароль</label>
                                <input type="password" id="new_password" name="new_password" class="form-control" 
                                       placeholder="Введите новый пароль" required minlength="6">
                                <div class="password-strength">
                                    <div class="password-strength-fill" id="password-strength"></div>
                                </div>
                                <div class="help-text">Пароль должен содержать минимум 6 символов</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password" class="required">Подтверждение пароля</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                       placeholder="Повторите новый пароль" required>
                                <div class="help-text" id="password-match"></div>
                            </div>

                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-key"></i> Сменить пароль
                            </button>
                        </form>
                    </div>
                </div>

 
                <div id="info-tab" class="tab-content">
                    <div class="form-section">
                        <h3><i class="fas fa-info-circle"></i> Информация об аккаунте</h3>
                        
                        <div class="user-details">
                            <div class="detail-item">
                                <div class="detail-label">ID пользователя</div>
                                <div class="detail-value">#<?php echo htmlspecialchars($user['id'] ?? ''); ?></div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Имя пользователя</div>
                                <div class="detail-value"><?php echo htmlspecialchars($user['username'] ?? ''); ?></div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Email</div>
                                <div class="detail-value"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Полное имя</div>
                                <div class="detail-value"><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Роль</div>
                                <div class="detail-value">
                                    <?php echo $role_names[$user['role']] ?? $user['role']; ?>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Дата регистрации</div>
                                <div class="detail-value">
                                    <?php 
                                        if (!empty($user['created_at'])) {
                                            echo date('d.m.Y H:i', strtotime($user['created_at']));
                                        } else {
                                            echo 'Неизвестно';
                                        }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="activity-log">
                        <h3><i class="fas fa-history"></i> Последняя активность</h3>
                        
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-sign-in-alt"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">Последний вход в систему</div>
                                <div class="activity-time"><?php echo $user_stats['last_login']; ?></div>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-project-diagram"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">Создано проектов</div>
                                <div class="activity-time">Всего: <?php echo $user_stats['projects_count']; ?>, Активных: <?php echo $user_stats['active_projects']; ?></div>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">Создано отчетов</div>
                                <div class="activity-time">Всего: <?php echo $user_stats['reports_count']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    
        function openTab(tabName) {

            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
     
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            document.getElementById(tabName).classList.add('active');
            
            event.currentTarget.classList.add('active');
        }
        
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('password-strength');
            
            strengthBar.className = 'password-strength-fill';
            
            if (password.length === 0) {
                strengthBar.style.width = '0%';
            } else if (password.length < 6) {
                strengthBar.classList.add('strength-weak');
            } else if (password.length < 10) {
                strengthBar.classList.add('strength-medium');
            } else {
                const hasUpperCase = /[A-Z]/.test(password);
                const hasLowerCase = /[a-z]/.test(password);
                const hasNumbers = /\d/.test(password);
                const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
                
                let strength = 0;
                if (hasUpperCase) strength++;
                if (hasLowerCase) strength++;
                if (hasNumbers) strength++;
                if (hasSpecial) strength++;
                
                if (strength >= 3) {
                    strengthBar.classList.add('strength-strong');
                } else {
                    strengthBar.classList.add('strength-medium');
                }
            }
        });
        
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            const matchText = document.getElementById('password-match');
            
            if (confirmPassword.length === 0) {
                matchText.textContent = '';
                matchText.style.color = '';
            } else if (newPassword === confirmPassword) {
                matchText.textContent = 'Пароли совпадают';
                matchText.style.color = '#28a745';
            } else {
                matchText.textContent = 'Пароли не совпадают';
                matchText.style.color = '#dc3545';
            }
        });
        
        setTimeout(function() {
            const errorMsg = document.querySelector('.error-message');
            const successMsg = document.querySelector('.success-message');
            
            if (errorMsg) errorMsg.style.display = 'none';
            if (successMsg) successMsg.style.display = 'none';
        }, 5000);
        
        let formChanged = false;
        const forms = document.querySelectorAll('form');
        
        forms.forEach(form => {
            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    formChanged = true;
                });
            });
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'У вас есть несохраненные изменения. Вы уверены, что хотите покинуть страницу?';
            }
        });
        
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                formChanged = false;
            });
        });
    </script>
</body>
</html>