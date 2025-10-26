<?php
// register.php - Страница регистрации
require_once 'config.php';

// Если пользователь уже авторизован, перенаправляем на главную
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Обработка формы регистрации
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'] ?? 'user'; // Получаем выбранную роль

    // Валидация данных
    if (empty($username) || empty($email) || empty($password) || empty($password_confirm) || empty($full_name)) {
        $error = 'Заполните все поля';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Введите корректный email адрес';
    } elseif ($password !== $password_confirm) {
        $error = 'Пароли не совпадают';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен содержать минимум 6 символов';
    } elseif (!in_array($role, ['user', 'analyst'])) {
        $error = 'Выбрана недопустимая роль';
    } else {
        // Проверяем, не занят ли username
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_user) {
            // Проверяем, что именно занято
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Пользователь с таким именем уже существует';
            } else {
                $error = 'Пользователь с таким email уже существует';
            }
        } else {
            // Хешируем пароль и создаем пользователя
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$username, $email, $password_hash, $full_name, $role])) {
                // Автоматически авторизуем пользователя после регистрации
                $user_id = $pdo->lastInsertId();
                
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['full_name'] = $full_name;
                $_SESSION['user_role'] = $role;
                
                header('Location: index.php');
                exit;
            } else {
                $error = 'Ошибка при создании пользователя';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация - Ростелеком</title>
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
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        padding: 20px;
        position: relative;
        overflow: hidden;
    }

    body::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 200%;
        background: radial-gradient(circle, var(--accent-light) 0%, transparent 70%);
        opacity: 0.1;
        animation: float 6s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(180deg); }
    }

    .register-container {
        width: 100%;
        max-width: 900px;
        padding: 15px;
        position: relative;
        z-index: 1;
        animation: fadeInUp 0.8s ease-out;
    }

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

    .register-card {
        background: var(--bg-white);
        border-radius: 20px;
        padding: 30px; /* Уменьшено с 40px */
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        position: relative;
        overflow: hidden;
        border: 1px solid var(--border-light);
        max-height: 85vh; /* Ограничение максимальной высоты */
        overflow-y: auto; /* Прокрутка если контент не помещается */
    }

    .register-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }

    .register-header {
        text-align: center;
        margin-bottom: 20px; /* Уменьшено с 30px */
    }

    .register-header h1 {
        color: var(--primary);
        margin-bottom: 8px; /* Уменьшено с 12px */
        font-size: 2rem; /* Уменьшено с 2.2rem */
        font-weight: 800;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .register-header p {
        color: var(--text-light);
        font-size: 1rem; /* Уменьшено с 1.1rem */
        font-weight: 500;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px; /* Уменьшено с 20px */
        margin-bottom: 15px; /* Уменьшено с 20px */
    }

    .form-group {
        margin-bottom: 0;
        position: relative;
    }

    .full-width {
        grid-column: 1 / -1;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px; /* Уменьшено с 10px */
        color: var(--text-dark);
        font-weight: 600;
        font-size: 0.9rem; /* Уменьшено с 0.95rem */
    }

    .form-control {
        width: 100%;
        padding: 12px 15px; /* Уменьшено с 15px 18px */
        border: 2px solid var(--border-light);
        border-radius: 10px; /* Уменьшено с 12px */
        font-size: 0.9rem; /* Уменьшено с 0.95rem */
        transition: all 0.3s ease;
        background: var(--bg-gray);
        font-weight: 500;
    }

    .form-control:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(120, 0, 255, 0.1);
        background: var(--bg-white);
        transform: translateY(-2px);
    }

    select.form-control {
        cursor: pointer;
    }

    .btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px; /* Уменьшено с 10px */
        width: 100%;
        padding: 14px; /* Уменьшено с 16px */
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        border: none;
        border-radius: 10px; /* Уменьшено с 12px */
        font-size: 0.95rem; /* Уменьшено с 1rem */
        font-weight: 700;
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        text-decoration: none;
        box-shadow: 0 6px 20px rgba(120, 0, 255, 0.3);
        margin-top: 8px; /* Уменьшено с 10px */
    }

    .btn:hover {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 12px 30px rgba(120, 0, 255, 0.4);
        background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
    }

    .btn:active {
        transform: translateY(-1px) scale(1.01);
    }

    .error-message {
        color: var(--danger);
        text-align: center;
        margin-bottom: 15px; /* Уменьшено с 20px */
        padding: 15px; /* Уменьшено с 18px */
        background: linear-gradient(135deg, #fef2f2, #fecaca);
        border-radius: 10px; /* Уменьшено с 12px */
        font-weight: 600;
        border-left: 4px solid var(--danger);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.1);
        animation: shake 0.5s ease-in-out;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }

    .success-message {
        color: var(--success);
        text-align: center;
        margin-bottom: 15px; /* Уменьшено с 20px */
        padding: 15px; /* Уменьшено с 18px */
        background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        border-radius: 10px; /* Уменьшено с 12px */
        font-weight: 600;
        border-left: 4px solid var(--success);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
    }

    .login-link {
        text-align: center;
        margin-top: 20px; /* Уменьшено с 25px */
        padding-top: 15px; /* Уменьшено с 20px */
        border-top: 2px solid var(--border-light);
    }

    .login-link a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .login-link a:hover {
        color: var(--primary-light);
        text-shadow: 0 0 8px rgba(120, 0, 255, 0.3);
    }

    .password-requirements {
        font-size: 0.75rem; /* Уменьшено с 0.8rem */
        color: var(--text-light);
        margin-top: 5px; /* Уменьшено с 6px */
        padding-left: 6px; /* Уменьшено с 8px */
    }

    .role-description {
        font-size: 0.75rem; /* Уменьшено с 0.8rem */
        color: var(--text-dark);
        margin-top: 6px; /* Уменьшено с 8px */
        padding: 10px; /* Уменьшено с 12px */
        background: linear-gradient(135deg, var(--bg-gray) 0%, #f0f8ff 100%);
        border-radius: 8px; /* Уменьшено с 10px */
        border-left: 3px solid var(--primary);
        font-weight: 500;
    }

    .role-option {
        padding: 6px 0; /* Уменьшено с 8px 0 */
    }

    .role-title {
        font-weight: bold;
        color: var(--primary);
    }

    .role-details {
        font-size: 0.7rem; /* Уменьшено с 0.75rem */
        color: var(--text-light);
    }

    .field-requirements {
        font-size: 0.7rem; /* Уменьшено с 0.75rem */
        color: var(--text-light);
        margin-top: 4px; /* Уменьшено с 5px */
        padding-left: 5px; /* Уменьшено с 6px */
    }

    /* Анимация для полей ввода */
    .form-group {
        animation: fadeInUp 0.6s ease-out;
    }

    .form-group:nth-child(1) { animation-delay: 0.1s; }
    .form-group:nth-child(2) { animation-delay: 0.2s; }
    .form-group:nth-child(3) { animation-delay: 0.3s; }
    .form-group:nth-child(4) { animation-delay: 0.4s; }
    .form-group:nth-child(5) { animation-delay: 0.5s; }
    .form-group:nth-child(6) { animation-delay: 0.6s; }

    /* Адаптивность для мобильных */
    @media (max-width: 768px) {
        body {
            padding: 10px;
        }
        
        .register-container {
            max-width: 100%;
            padding: 10px;
        }
        
        .register-card {
            padding: 25px 20px; /* Уменьшено с 30px 20px */
            border-radius: 18px;
            max-height: 90vh; /* Увеличено для мобильных */
        }
        
        .register-header h1 {
            font-size: 1.7rem; /* Уменьшено с 1.8rem */
        }
        
        .form-grid {
            grid-template-columns: 1fr;
            gap: 12px; /* Уменьшено с 15px */
        }
        
        .form-control {
            padding: 12px 14px; /* Уменьшено с 14px 16px */
        }
    }

    /* Для очень маленьких экранов */
    @media (max-width: 480px) {
        .register-container {
            max-width: 100%;
        }
        
        .register-card {
            padding: 20px 15px; /* Уменьшено с 25px 18px */
        }
        
        .register-header h1 {
            font-size: 1.5rem; /* Уменьшено с 1.6rem */
        }
    }
</style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <h1>Ростелеком</h1>
                <p>Регистрация в системе управления проектами</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Имя пользователя *</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                           required>
                    <div class="field-requirements">Уникальное имя для входа в систему</div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email адрес *</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                           required>
                    <div class="field-requirements">Для уведомлений и восстановления доступа</div>
                </div>
                
                <div class="form-group">
                    <label for="full_name">Полное имя *</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" 
                           value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" 
                           required>
                    <div class="field-requirements">Ваше настоящее имя и фамилия</div>
                </div>
                
                <div class="form-group">
                    <label for="role">Роль в системе *</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="user" <?php echo (isset($_POST['role']) && $_POST['role'] == 'user') ? 'selected' : ''; ?>>Пользователь</option>
                        <option value="analyst" <?php echo (isset($_POST['role']) && $_POST['role'] == 'analyst') ? 'selected' : ''; ?>>Аналитик</option>
                    </select>
                    <div class="role-description" id="role-description">
                        <?php if (isset($_POST['role']) && $_POST['role'] == 'analyst'): ?>
                            <strong>Аналитик:</strong> Может создавать и редактировать проекты, назначать задачи, анализировать данные
                        <?php else: ?>
                            <strong>Пользователь:</strong> Может просматривать проекты и выполнять назначенные задачи
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Пароль *</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                    <div class="password-requirements">Минимум 6 символов</div>
                </div>
                
                <div class="form-group">
                    <label for="password_confirm">Подтверждение пароля *</label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control" required>
                </div>
                
                <button type="submit" class="btn">Зарегистрироваться</button>
            </form>
            
            <div class="login-link">
                Уже есть аккаунт? <a href="login.php">Войдите здесь</a>
            </div>

            
        </div>
    </div>

    <script>
        // Динамическое обновление описания роли при выборе
        document.getElementById('role').addEventListener('change', function() {
            const description = document.getElementById('role-description');
            if (this.value === 'analyst') {
                description.innerHTML = '<strong>Аналитик:</strong> Может создавать и редактировать проекты, назначать задачи, анализировать данные';
            } else {
                description.innerHTML = '<strong>Пользователь:</strong> Может просматривать проекты и выполнять назначенные задачи';
            }
        });
    </script>
</body>
</html>