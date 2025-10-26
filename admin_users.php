<?php
// admin_users.php - Управление пользователями (только для администраторов)
require_once 'config.php';
checkAuth();
checkRole(['admin']);

$message = '';
$error = '';

// Обработка добавления пользователя
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    
    if (!empty($username) && !empty($password) && !empty($full_name) && !empty($email) && !empty($role)) {
        // Проверяем, не существует ли уже пользователь с таким именем
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            $error = 'Пользователь с таким именем уже существует';
        } else {
            if (registerUser($pdo, $username, $password, $full_name, $email, $role)) {
                $message = 'Пользователь успешно зарегистрирован';
            } else {
                $error = 'Ошибка при регистрации пользователя';
            }
        }
    } else {
        $error = 'Заполните все поля';
    }
}

// Обработка удаления пользователя
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    
    if (deleteUser($pdo, $user_id)) {
        $message = 'Пользователь успешно удален';
    } else {
        $error = 'Нельзя удалить последнего администратора';
    }
}

// Получение списка всех пользователей
$users = getAllUsers($pdo);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление пользователями - Ростелеком</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Стили из предыдущего примера остаются без изменений */
        :root {
            --rtk-blue: #0033a0;
            --rtk-light-blue: #00a0e3;
            --rtk-red: #e4002b;
            --rtk-gray: #f5f5f5;
            --rtk-dark-gray: #333333;
            --rtk-white: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            display: flex;
            min-height: 100vh;
            background-color: var(--rtk-gray);
        }

        .sidebar {
            width: 250px;
            background-color: var(--rtk-blue);
            color: var(--rtk-white);
            transition: all 0.3s;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 20px;
            background-color: var(--rtk-light-blue);
            text-align: center;
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .sidebar-menu {
            padding: 15px 0;
        }

        .sidebar-menu ul {
            list-style: none;
        }

        .sidebar-menu li {
            padding: 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: var(--rtk-white);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .sidebar-menu a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-left: 4px solid var(--rtk-red);
        }

        .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.15);
            border-left: 4px solid var(--rtk-red);
        }

        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }

        .header h1 {
            color: var(--rtk-blue);
            font-size: 1.8rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--rtk-light-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .admin-panel {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
        }

        .form-card, .users-card {
            background-color: var(--rtk-white);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .form-card h2, .users-card h2 {
            color: var(--rtk-blue);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--rtk-dark-gray);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .form-control:focus {
            border-color: var(--rtk-blue);
            outline: none;
        }

        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background-color: var(--rtk-blue);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--rtk-light-blue);
        }

        .btn-danger {
            background-color: var(--rtk-red);
            color: white;
        }

        .btn-danger:hover {
            background-color: #ff6b6b;
        }

        .message {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        table th {
            background-color: var(--rtk-gray);
            color: var(--rtk-blue);
            font-weight: 600;
        }

        .role-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .role-admin {
            background-color: #ff4d4f;
            color: white;
        }

        .role-analyst {
            background-color: #1890ff;
            color: white;
        }

        .role-user {
            background-color: #52c41a;
            color: white;
        }

        @media (max-width: 992px) {
            .admin-panel {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .sidebar-menu ul {
                display: flex;
                overflow-x: auto;
            }
            
            .sidebar-menu li {
                flex: 1;
                min-width: 70px;
            }
            
            .sidebar-menu a {
                justify-content: center;
                border-left: none;
                border-bottom: 4px solid transparent;
            }
            
            .sidebar-menu a:hover, .sidebar-menu a.active {
                border-left: none;
                border-bottom: 4px solid var(--rtk-red);
            }
        }
    </style>
</head>
<body>
    <!-- Боковое меню -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Ростелеком</h2>
            <p>Управление проектами</p>
        </div>
        <nav class="sidebar-menu">
            <ul>
                <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> <span class="menu-text">Дашборд</span></a></li>
                <li><a href="profile.php"><i class="fas fa-user-circle"></i> <span class="menu-text">Личный кабинет</span></a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> <span class="menu-text">Просмотр отчетов</span></a></li>
                <li><a href="edit_reports.php"><i class="fas fa-edit"></i> <span class="menu-text">Редактирование отчетов</span></a></li>
                <li><a href="directory.php"><i class="fas fa-book"></i> <span class="menu-text">Справочник</span></a></li>
                <li><a href="admin_users.php" class="active"><i class="fas fa-users-cog"></i> <span class="menu-text">Управление пользователями</span></a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span class="menu-text">Выход</span></a></li>
            </ul>
        </nav>
    </div>

    <!-- Основная область -->
    <div class="main-content">
        <div class="header">
            <h1>Управление пользователями</h1>
            <div class="user-info">
                <div class="user-avatar"><?php echo substr($_SESSION['full_name'], 0, 2); ?></div>
                <div>
                    <div><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                    <div style="font-size: 0.8rem; color: #777;">Администратор</div>
                </div>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="admin-panel">
            <div class="form-card">
                <h2>Добавить пользователя</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Имя пользователя</label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Пароль</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">Полное имя</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Роль</label>
                        <select id="role" name="role" class="form-control" required>
                            <option value="user">Пользователь</option>
                            <option value="analyst">Аналитик</option>
                            <option value="admin">Администратор</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="add_user" class="btn btn-primary">Добавить пользователя</button>
                </form>
            </div>

            <div class="users-card">
                <h2>Список пользователей</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Имя пользователя</th>
                            <th>Полное имя</th>
                            <th>Email</th>
                            <th>Роль</th>
                            <th>Дата регистрации</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                    <?php 
                                    $role_names = [
                                        'admin' => 'Админ',
                                        'analyst' => 'Аналитик',
                                        'user' => 'Пользователь'
                                    ];
                                    echo $role_names[$user['role']];
                                    ?>
                                </span>
                            </td>
                            <td><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="delete_user" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите удалить пользователя?')">Удалить</button>
                                </form>
                                <?php else: ?>
                                <span style="color: #999;">Текущий пользователь</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>