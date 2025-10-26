<?php

session_start();

require_once 'config.php';


if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}


if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}


try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_user || $current_user['role'] !== 'admin') {
        $_SESSION['error'] = 'У вас нет прав для доступа к этой странице';
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error getting user role: " . $e->getMessage());
    $_SESSION['error'] = 'Ошибка проверки прав доступа';
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_POST['user_id'] ?? '';
    
    try {
        switch ($action) {
            case 'delete':
                
                if ($user_id == $_SESSION['user_id']) {
                    $error = 'Вы не можете удалить свой собственный аккаунт';
                    break;
                }
                
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE created_by = ? OR manager_id = ?");
                $stmt->execute([$user_id, $user_id]);
                $project_count = $stmt->fetchColumn();
                
                if ($project_count > 0) {
                    $error = 'Нельзя удалить пользователя, у которого есть связанные проекты';
                    break;
                }
                
                
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                
                if ($stmt->rowCount() > 0) {
                    $success = 'Пользователь успешно удален';
                } else {
                    $error = 'Пользователь не найден';
                }
                break;
                
            case 'change_role':
                $new_role = $_POST['new_role'] ?? '';
                
                
                if (!in_array($new_role, ['admin', 'analyst', 'user'])) {
                    $error = 'Некорректная роль';
                    break;
                }
                
                
                if ($user_id == $_SESSION['user_id']) {
                    $error = 'Вы не можете изменить свою собственную роль';
                    break;
                }
                
                
                $stmt = $pdo->prepare("UPDATE users SET role = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$new_role, $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    $role_names = [
                        'admin' => 'Администратор',
                        'analyst' => 'Аналитик', 
                        'user' => 'Пользователь'
                    ];
                    $success = "Роль пользователя изменена на: " . ($role_names[$new_role] ?? $new_role);
                } else {
                    $error = 'Пользователь не найден';
                }
                break;
                
            default:
                $error = 'Неизвестное действие';
                break;
        }
    } catch (PDOException $e) {
        error_log("Error processing user action: " . $e->getMessage());
        $error = 'Ошибка при выполнении операции: ' . $e->getMessage();
    }
}

try {
    $stmt = $pdo->query("
        SELECT 
            id, 
            username, 
            full_name, 
            email, 
            role, 
            created_at,
            updated_at
        FROM users 
        ORDER BY 
            CASE role 
                WHEN 'admin' THEN 1 
                WHEN 'analyst' THEN 2 
                WHEN 'user' THEN 3 
            END,
            created_at DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error getting users list: " . $e->getMessage());
    $users = [];
    $error = 'Ошибка при загрузке списка пользователей';
}


try {
    $stats_stmt = $pdo->query("
        SELECT 
            role,
            COUNT(*) as count
        FROM users 
        GROUP BY role
    ");
    $role_stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_users = array_sum(array_column($role_stats, 'count'));
} catch (PDOException $e) {
    error_log("Error getting user stats: " . $e->getMessage());
    $role_stats = [];
    $total_users = 0;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление пользователями - Ростелеком</title>
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
        max-width: 1400px;
        margin: 0 auto;
    }

    /* Анимированный заголовок */
    .header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        padding: 30px 40px;
        border-radius: 20px 20px 0 0;
        margin-bottom: 0;
        box-shadow: 0 20px 40px rgba(120, 0, 255, 0.15);
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
        overflow: hidden;
    }

    .header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 200%;
        background: radial-gradient(circle, var(--accent-light) 0%, transparent 70%);
        opacity: 0.1;
    }

    .header h1 {
        color: white;
        font-size: 1.8rem;
        font-weight: 700;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }

    /* Хлебные крошки */
    .breadcrumb {
        background: var(--bg-white);
        padding: 20px 30px;
        border-radius: 0;
        margin-bottom: 0;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border-light);
    }

    .breadcrumb a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .breadcrumb a:hover {
        color: var(--primary-light);
    }

    /* Основной контент */
    .content-container {
        background: var(--bg-white);
        padding: 40px;
        border-radius: 0 0 20px 20px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        border: 1px solid var(--border-light);
        margin-bottom: 25px;
    }

    /* Заголовок страницы */
    h1 {
        color: var(--primary);
        font-size: 2.2rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        -webkit-background-clip: text;
    }

    /* Анимированные карточки статистики */
    .stats-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .stat-card {
        background: var(--bg-white);
        padding: 25px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        text-align: center;
        position: relative;
        overflow: hidden;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 1px solid var(--border-light);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }

    .stat-card:hover {
        transform: translateY(-8px) scale(1.03);
        box-shadow: 0 20px 40px rgba(120, 0, 255, 0.15);
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 8px;
        line-height: 1;
    }

    .stat-label {
        color: var(--text-light);
        font-size: 1rem;
        font-weight: 500;
    }

    /* Сообщения */
    .error-message {
        color: var(--danger);
        padding: 20px;
        background: linear-gradient(135deg, #fef2f2, #fecaca);
        border-radius: 15px;
        margin-bottom: 25px;
        border-left: 5px solid var(--danger);
        box-shadow: 0 5px 15px rgba(239, 68, 68, 0.1);
        font-weight: 600;
    }

    .success-message {
        color: var(--success);
        padding: 20px;
        background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        border-radius: 15px;
        margin-bottom: 25px;
        border-left: 5px solid var(--success);
        box-shadow: 0 5px 15px rgba(16, 185, 129, 0.1);
        font-weight: 600;
    }

    /* Таблица пользователей */
    .users-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-top: 20px;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        background: var(--bg-white);
    }

    .users-table th,
    .users-table td {
        padding: 16px 20px;
        text-align: left;
        border-bottom: 1px solid var(--border-light);
    }

    .users-table th {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        font-weight: 700;
        font-size: 1rem;
        position: sticky;
        top: 0;
    }

    .users-table tr:hover {
        background-color: var(--bg-gray);
        transform: scale(1.01);
        transition: all 0.2s ease;
    }

    /* Бейджи ролей */
    .role-badge {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 15px;
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .role-admin {
        background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
        color: white;
    }

    .role-analyst {
        background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
        color: white;
    }

    .role-user {
        background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
        color: white;
    }

    /* Кнопки */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        box-shadow: 0 4px 15px rgba(120, 0, 255, 0.3);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(120, 0, 255, 0.4);
        background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
    }

    .btn-sm {
        padding: 8px 14px;
        font-size: 0.8rem;
    }

    .btn-danger {
        background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
        box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
    }

    .btn-danger:hover {
        background: linear-gradient(135deg, #f87171 0%, var(--danger) 100%);
        box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
    }

    .btn-warning {
        background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
    }

    .btn-warning:hover {
        background: linear-gradient(135deg, #fbbf24 0%, var(--warning) 100%);
        box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        box-shadow: 0 4px 15px rgba(120, 0, 255, 0.3);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
        box-shadow: 0 8px 25px rgba(120, 0, 255, 0.4);
    }

    .btn:disabled {
        background: linear-gradient(135deg, #9ca3af, #6b7280);
        box-shadow: 0 4px 15px rgba(107, 114, 128, 0.3);
        cursor: not-allowed;
        transform: none;
    }

    .btn:disabled:hover {
        transform: none;
        box-shadow: 0 4px 15px rgba(107, 114, 128, 0.3);
    }

    /* Модальные окна */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        backdrop-filter: blur(5px);
    }

    .modal-content {
        background-color: var(--bg-white);
        margin: 10% auto;
        padding: 30px;
        border-radius: 25px;
        width: 450px;
        max-width: 90%;
        box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        border: 1px solid var(--border-light);
        position: relative;
        overflow: hidden;
        animation: modalSlideIn 0.3s ease-out;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px) scale(0.9);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .modal-content::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 6px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 2px solid var(--border-light);
    }

    .modal-title {
        color: var(--primary);
        margin: 0;
        font-size: 1.4rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .close {
        color: var(--text-light);
        font-size: 1.8rem;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .close:hover {
        color: var(--primary);
        transform: rotate(90deg);
    }

    .modal-body {
        margin-bottom: 25px;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }

    /* Формы */
    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: var(--text-dark);
        font-weight: 600;
    }

    .form-control {
        width: 100%;
        padding: 15px 18px;
        border: 2px solid var(--border-light);
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: var(--bg-gray);
    }

    .form-control:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(120, 0, 255, 0.1);
        background: var(--bg-white);
    }

    /* Текущий пользователь */
    .current-user {
        background: linear-gradient(135deg, #e0f2fe, #bae6fd) !important;
        position: relative;
    }

    .current-user::before {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.2rem;
    }

    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* Состояние пустоты */
    .empty-state {
        text-align: center;
        padding: 80px 20px;
        color: var(--text-light);
    }

    .empty-state i {
        font-size: 4rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 20px;
    }

    .empty-state h3 {
        color: var(--text-dark);
        margin-bottom: 15px;
        font-size: 1.5rem;
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

    .stat-card, .users-table {
        animation: fadeInUp 0.6s ease-out;
    }

    .stat-card:nth-child(2) { animation-delay: 0.1s; }
    .stat-card:nth-child(3) { animation-delay: 0.2s; }
    .stat-card:nth-child(4) { animation-delay: 0.3s; }

    /* Адаптивность для мобильных */
    @media (max-width: 768px) {
        body {
            padding: 15px;
        }
        
        .header {
            padding: 25px;
            flex-direction: column;
            text-align: center;
            gap: 20px;
        }
        
        .header h1 {
            font-size: 1.5rem;
        }
        
        .users-table {
            display: block;
            overflow-x: auto;
        }
        
        .stats-cards {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .action-buttons {
            flex-direction: column;
            gap: 8px;
        }
        
        .modal-content {
            width: 95%;
            margin: 5% auto;
            padding: 25px 20px;
        }
        
        .modal-footer {
            flex-direction: column;
        }
    }
</style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-users-cog"></i> Ростелеком - Управление пользователями</h1>
        </div>

        <div class="breadcrumb">
            <a href="index.php"><i class="fas fa-home"></i> Дашборд</a> &raquo; 
            <span><i class="fas fa-users"></i> Пользователи</span>
        </div>
        
        <div class="content-container">
            <h1 style="color: var(--rtk-blue); margin-bottom: 20px;">Управление пользователями</h1>
            
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

            
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_users; ?></div>
                    <div class="stat-label">Всего пользователей</div>
                </div>
                <?php foreach ($role_stats as $stat): ?>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stat['count']; ?></div>
                        <div class="stat-label">
                            <?php 
                                $role_names = [
                                    'admin' => 'Администраторы',
                                    'analyst' => 'Аналитики',
                                    'user' => 'Пользователи'
                                ];
                                echo $role_names[$stat['role']] ?? $stat['role'];
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            
            <div style="overflow-x: auto;">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Логин</th>
                            <th>ФИО</th>
                            <th>Email</th>
                            <th>Роль</th>
                            <th>Дата регистрации</th>
                            <th>Последнее обновление</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <h3>Пользователи не найдены</h3>
                                    <p>В системе нет зарегистрированных пользователей</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr class="<?php echo $user['id'] == $_SESSION['user_id'] ? 'current-user' : ''; ?>">
                                    <td><?php echo $user['id']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                            <span style="color: var(--rtk-blue); font-weight: bold;">(Вы)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php 
                                                $role_names = [
                                                    'admin' => 'Администратор',
                                                    'analyst' => 'Аналитик',
                                                    'user' => 'Пользователь'
                                                ];
                                                echo $role_names[$user['role']] ?? $user['role'];
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <?php 
                                            if ($user['updated_at'] && $user['updated_at'] != $user['created_at']) {
                                                echo date('d.m.Y H:i', strtotime($user['updated_at']));
                                            } else {
                                                echo '—';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            
                                            <button class="btn btn-warning btn-sm" 
                                                    onclick="openChangeRoleModal(<?php echo $user['id']; ?>, '<?php echo $user['username']; ?>', '<?php echo $user['role']; ?>')"
                                                    <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                                <i class="fas fa-user-edit"></i> Роль
                                            </button>
                                            
                                            
                                            <button class="btn btn-danger btn-sm" 
                                                    onclick="openDeleteModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                                    <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                                <i class="fas fa-trash"></i> Удалить
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

   
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Подтверждение удаления</h3>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Вы уверены, что хотите удалить пользователя <strong id="deleteUserName"></strong>?</p>
                <p class="error-message" style="margin-top: 10px; display: none;" id="deleteError">
                    <i class="fas fa-exclamation-circle"></i> <span id="deleteErrorText"></span>
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeDeleteModal()">Отмена</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="submit" class="btn btn-danger">Удалить</button>
                </form>
            </div>
        </div>
    </div>

    
    <div id="changeRoleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-user-edit"></i> Изменение роли пользователя</h3>
                <span class="close" onclick="closeChangeRoleModal()">&times;</span>
            </div>
            <form id="changeRoleForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_role">
                    <input type="hidden" name="user_id" id="changeRoleUserId">
                    
                    <div class="form-group">
                        <label for="changeRoleUserName">Пользователь:</label>
                        <input type="text" id="changeRoleUserName" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_role">Новая роль:</label>
                        <select id="new_role" name="new_role" class="form-control" required>
                            <option value="user">Пользователь</option>
                            <option value="analyst">Аналитик</option>
                            <option value="admin">Администратор</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeChangeRoleModal()">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>

    <script>
       
        function openDeleteModal(userId, userName) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserName').textContent = userName;
            document.getElementById('deleteModal').style.display = 'block';
            document.getElementById('deleteError').style.display = 'none';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        
        function openChangeRoleModal(userId, userName, currentRole) {
            document.getElementById('changeRoleUserId').value = userId;
            document.getElementById('changeRoleUserName').value = userName;
            document.getElementById('new_role').value = currentRole;
            document.getElementById('changeRoleModal').style.display = 'block';
        }

        function closeChangeRoleModal() {
            document.getElementById('changeRoleModal').style.display = 'none';
        }

        
        window.onclick = function(event) {
            const deleteModal = document.getElementById('deleteModal');
            const changeRoleModal = document.getElementById('changeRoleModal');
            
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
            if (event.target == changeRoleModal) {
                closeChangeRoleModal();
            }
        }

        
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const userId = document.getElementById('deleteUserId').value;
            const formData = new FormData(this);
            
            fetch('users.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('deleteErrorText').textContent = 'Ошибка при удалении пользователя';
                document.getElementById('deleteError').style.display = 'block';
            });
        });
    </script>
</body>
</html>