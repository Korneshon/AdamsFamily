<?php
// login.php - Страница авторизации
require_once 'config.php';

// Если пользователь уже авторизован, перенаправляем на главную
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// Обработка формы авторизации
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            
            header('Location: index.php');
            exit;
        } else {
            $error = 'Неверное имя пользователя или пароль';
        }
    } else {
        $error = 'Заполните все поля';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Авторизация - Ростелеком</title>
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

    .login-container {
        width: 100%;
        max-width: 450px;
        padding: 20px;
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

    .login-card {
        background: var(--bg-white);
        border-radius: 25px;
        padding: 50px 40px;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        position: relative;
        overflow: hidden;
        border: 1px solid var(--border-light);
    }

    .login-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 6px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }

    .login-header {
        text-align: center;
        margin-bottom: 40px;
    }

    .login-header h1 {
        color: var(--primary);
        margin-bottom: 15px;
        font-size: 2.5rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .login-header p {
        color: var(--text-light);
        font-size: 1.1rem;
        font-weight: 500;
    }

    .form-group {
        margin-bottom: 25px;
        position: relative;
    }

    .form-group label {
        display: block;
        margin-bottom: 12px;
        color: var(--text-dark);
        font-weight: 600;
        font-size: 1rem;
    }

    .form-control {
        width: 100%;
        padding: 18px 20px;
        border: 2px solid var(--border-light);
        border-radius: 15px;
        font-size: 1rem;
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

    .btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        width: 100%;
        padding: 18px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        border: none;
        border-radius: 15px;
        font-size: 1.1rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        text-decoration: none;
        box-shadow: 0 8px 25px rgba(120, 0, 255, 0.3);
        margin-top: 10px;
    }

    .btn:hover {
        transform: translateY(-5px) scale(1.02);
        box-shadow: 0 15px 35px rgba(120, 0, 255, 0.4);
        background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
    }

    .btn:active {
        transform: translateY(-2px) scale(1.01);
    }

    .error-message {
        color: var(--danger);
        text-align: center;
        margin-bottom: 25px;
        padding: 20px;
        background: linear-gradient(135deg, #fef2f2, #fecaca);
        border-radius: 15px;
        font-weight: 600;
        border-left: 5px solid var(--danger);
        box-shadow: 0 5px 15px rgba(239, 68, 68, 0.1);
        animation: shake 0.5s ease-in-out;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }

    .demo-accounts {
        margin-top: 35px;
        padding: 25px;
        background: linear-gradient(135deg, var(--bg-gray) 0%, #f0f8ff 100%);
        border-radius: 20px;
        font-size: 0.95rem;
        border: 2px dashed var(--border-light);
        position: relative;
        overflow: hidden;
    }

    .demo-accounts::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        
    }

    .demo-accounts h3 {
        margin-bottom: 15px;
        color: var(--primary);
        font-size: 1.2rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .demo-accounts h3::before {
        content: '';
        font-size: 1.4rem;
    }

    .demo-account {
        margin-bottom: 12px;
        padding: 12px 15px;
        background: var(--bg-white);
        border-radius: 12px;
        border-left: 4px solid var(--primary);
        transition: all 0.3s ease;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .demo-account:hover {
        transform: translateX(8px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .demo-account strong {
        color: var(--primary);
        font-weight: 700;
    }

    .demo-account span {
        color: var(--text-light);
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
        background: var(--bg-gray);
        padding: 4px 8px;
        border-radius: 6px;
    }

    /* Анимация для полей ввода */
    .form-group {
        animation: fadeInUp 0.6s ease-out;
    }

    .form-group:nth-child(1) { animation-delay: 0.1s; }
    .form-group:nth-child(2) { animation-delay: 0.2s; }

    /* Иконки в полях ввода */
    .form-group::before {
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        right: 20px;
        top: 55px;
        color: var(--text-light);
        font-size: 1.1rem;
    }

    .form-group:first-child::before {
        content: '\f007'; /* user icon */
    }

    .form-group:nth-child(2)::before {
        content: '\f023'; /* lock icon */
    }

    /* Адаптивность для мобильных */
    @media (max-width: 768px) {
        body {
            padding: 15px;
        }
        
        .login-container {
            max-width: 100%;
            padding: 10px;
        }
        
        .login-card {
            padding: 40px 25px;
            border-radius: 20px;
        }
        
        .login-header h1 {
            font-size: 2rem;
        }
        
        .demo-account {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        
        .demo-account span {
            align-self: flex-start;
        }
    }

    /* Эффект частиц (опционально) */
    .particles {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 0;
    }

    .particle {
        position: absolute;
        background: var(--accent-light);
        border-radius: 50%;
        opacity: 0.3;
        animation: float 8s infinite linear;
    }

    .particle:nth-child(1) { top: 20%; left: 10%; width: 8px; height: 8px; animation-delay: 0s; }
    .particle:nth-child(2) { top: 60%; left: 80%; width: 12px; height: 12px; animation-delay: 1s; }
    .particle:nth-child(3) { top: 80%; left: 20%; width: 6px; height: 6px; animation-delay: 2s; }
    .particle:nth-child(4) { top: 30%; left: 70%; width: 10px; height: 10px; animation-delay: 3s; }
    .particle:nth-child(5) { top: 40%; left: 10%; width: 8px; height: 8px; animation-delay: 0s; }
    .particle:nth-child(6) { top: 50%; left: 80%; width: 12px; height: 12px; animation-delay: 1s; }
    .particle:nth-child(7) { top: 90%; left: 20%; width: 6px; height: 6px; animation-delay: 2s; }
    .particle:nth-child(8) { top: 10%; left: 70%; width: 10px; height: 10px; animation-delay: 3s; }
    .particle:nth-child(9) { top: 70%; left: 80%; width: 12px; height: 12px; animation-delay: 1s; }
    .particle:nth-child(10) { top: 25%; left: 20%; width: 6px; height: 6px; animation-delay: 2s; }
    .particle:nth-child(11) { top: 55%; left: 70%; width: 10px; height: 10px; animation-delay: 3s; }
</style>
</head>
<div class="particles">
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
</div>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>Ростелеком</h1>
                <p>Система управления проектами</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Имя пользователя</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn">Войти</button>
            </form>
            
            <div class="demo-accounts">
                <h3>Тестовые учетные записи:</h3>
Еще нет аккаунта?  <a href="register.php">   Зарегестрируйтесь!</a> 
            </div>
        </div>
    </div>
</body>
</html>