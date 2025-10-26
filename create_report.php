<?php
// create_report.php

session_start();

require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Проверяем права доступа
$user_role = 'user';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $user_role = $user['role'];
    }
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// Проверяем, имеет ли пользователь права на создание отчетов
if ($user_role !== 'admin' && $user_role !== 'analyst') {
    header('Location: view_report.php');
    exit;
}

$error = '';
$success = '';

// Обработка формы создания отчета
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'analytical';
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    
    // Собираем критерии фильтрации
    $criteria = [
        'date_from' => $_POST['date_from'] ?? '',
        'date_to' => $_POST['date_to'] ?? '',
        'segment' => $_POST['segment'] ?? '',
        'status' => $_POST['status'] ?? '',
        'manager' => $_POST['manager'] ?? '',
        'service' => $_POST['service'] ?? '',
        'probability_min' => $_POST['probability_min'] ?? '',
        'probability_max' => $_POST['probability_max'] ?? ''
    ];
    
    // Валидация
    if (empty($title)) {
        $error = 'Название отчета обязательно для заполнения';
    } else {
        try {
            // Сохраняем отчет в базу данных
            $stmt = $pdo->prepare("
                INSERT INTO reports (title, description, type, criteria, created_by, is_public, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $title,
                $description,
                $type,
                json_encode($criteria, JSON_UNESCAPED_UNICODE),
                $_SESSION['user_id'],
                $is_public
            ]);
            
            $report_id = $pdo->lastInsertId();
            $success = 'Отчет успешно создан! <a href="view_report.php?id=' . $report_id . '">Просмотреть отчет</a>';
            
            // Очищаем поля формы после успешного сохранения
            $title = $description = '';
            $criteria = array_fill_keys(array_keys($criteria), '');
            
        } catch (PDOException $e) {
            $error = 'Ошибка при сохранении отчета: ' . $e->getMessage();
        }
    }
}

// Получаем данные для выпадающих списков
try {
    // Сегменты
    $segments_stmt = $pdo->query("SELECT id, name FROM segments ORDER BY name");
    $segments = $segments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Статусы проектов
    $statuses_stmt = $pdo->query("SELECT id, name FROM project_stages ORDER BY name");
    $statuses = $statuses_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Менеджеры
    $managers_stmt = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('manager', 'admin', 'analyst') ORDER BY full_name");
    $managers = $managers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Услуги
    $services_stmt = $pdo->query("SELECT id, name FROM services ORDER BY name");
    $services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error getting filter data: " . $e->getMessage());
    $segments = $statuses = $managers = $services = [];
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создание отчета - Ростелеком</title>
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
            line-height: 1.6;
            color: var(--text-dark);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 20px 20px 0 0;
            box-shadow: 0 10px 30px rgba(120, 0, 255, 0.2);
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
        }

        .content-container {
            background: var(--bg-white);
            padding: 35px;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid var(--border-light);
        }

        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
        }

        input, select, textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border-light);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--bg-white);
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(120, 0, 255, 0.1);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input {
            width: auto;
        }

        .form-section {
            background: var(--bg-gray);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            border-left: 4px solid var(--primary);
        }

        .form-section h3 {
            margin-bottom: 20px;
            color: var(--primary);
            font-size: 1.3rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
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

        .btn-secondary {
            background: linear-gradient(135deg, var(--text-light) 0%, #475569 100%);
            box-shadow: 0 6px 20px rgba(100, 116, 139, 0.3);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #94a3b8 0%, var(--text-light) 100%);
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #7f1d1d;
            border-left: 4px solid var(--danger);
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-financial {
            background: linear-gradient(135deg, #e8f5e8, var(--success));
            color: #065f46;
        }

        .badge-operational {
            background: linear-gradient(135deg, #e3f2fd, var(--primary));
            color: #1e40af;
        }

        .badge-analytical {
            background: linear-gradient(135deg, #f3e5f5, #9333ea);
            color: #6b21a8;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .content-container {
                padding: 25px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chart-bar"></i> Ростелеком - Создание отчета</h1>
        </div>

        <div class="breadcrumb">
            <a href="index.php"><i class="fas fa-home"></i> Дашборд</a> &raquo; 
            <a href="reports.php"><i class="fas fa-chart-pie"></i> Отчеты</a> &raquo; 
            <span><i class="fas fa-plus"></i> Создание отчета</span>
        </div>
        
        <div class="content-container">
            <div class="form-container">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-section">
                        <h3><i class="fas fa-info-circle"></i> Основная информация</h3>
                        
                        <div class="form-group">
                            <label for="title">Название отчета *</label>
                            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($title ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Описание отчета</label>
                            <textarea id="description" name="description"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="type">Тип отчета</label>
                                <select id="type" name="type">
                                    <option value="analytical" <?php echo ($type ?? 'analytical') === 'analytical' ? 'selected' : ''; ?>>Аналитический</option>
                                    <option value="financial" <?php echo ($type ?? '') === 'financial' ? 'selected' : ''; ?>>Финансовый</option>
                                    <option value="operational" <?php echo ($type ?? '') === 'operational' ? 'selected' : ''; ?>>Операционный</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="is_public" name="is_public" <?php echo isset($is_public) && $is_public ? 'checked' : 'checked'; ?>>
                                    <label for="is_public" style="margin-bottom: 0;">Публичный отчет</label>
                                </div>
                                <small style="color: var(--text-light);">Публичные отчеты видны всем пользователям</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-filter"></i> Критерии фильтрации</h3>
                        <p style="color: var(--text-light); margin-bottom: 20px;">Укажите критерии для фильтрации данных в отчете. Оставьте поля пустыми, чтобы не применять фильтр.</p>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="date_from">Дата с</label>
                                <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($criteria['date_from'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="date_to">Дата по</label>
                                <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($criteria['date_to'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="segment">Сегмент</label>
                                <select id="segment" name="segment">
                                    <option value="">Все сегменты</option>
                                    <?php foreach ($segments as $segment): ?>
                                        <option value="<?php echo htmlspecialchars($segment['name']); ?>" 
                                            <?php echo ($criteria['segment'] ?? '') === $segment['name'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($segment['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Статус проекта</label>
                                <select id="status" name="status">
                                    <option value="">Все статусы</option>
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?php echo htmlspecialchars($status['name']); ?>" 
                                            <?php echo ($criteria['status'] ?? '') === $status['name'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($status['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="manager">Менеджер</label>
                                <select id="manager" name="manager">
                                    <option value="">Все менеджеры</option>
                                    <?php foreach ($managers as $manager): ?>
                                        <option value="<?php echo htmlspecialchars($manager['full_name']); ?>" 
                                            <?php echo ($criteria['manager'] ?? '') === $manager['full_name'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($manager['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="service">Услуга</label>
                                <select id="service" name="service">
                                    <option value="">Все услуги</option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?php echo htmlspecialchars($service['name']); ?>" 
                                            <?php echo ($criteria['service'] ?? '') === $service['name'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($service['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="probability_min">Вероятность от (%)</label>
                                <input type="number" id="probability_min" name="probability_min" min="0" max="100" 
                                       value="<?php echo htmlspecialchars($criteria['probability_min'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="probability_max">Вероятность до (%)</label>
                                <input type="number" id="probability_max" name="probability_max" min="0" max="100" 
                                       value="<?php echo htmlspecialchars($criteria['probability_max'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Создать отчет
                        </button>
                        <a href="view_report.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Отмена
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Простая валидация дат
        document.addEventListener('DOMContentLoaded', function() {
            const dateFrom = document.getElementById('date_from');
            const dateTo = document.getElementById('date_to');
            
            if (dateFrom && dateTo) {
                dateFrom.addEventListener('change', function() {
                    if (this.value && dateTo.value && this.value > dateTo.value) {
                        dateTo.value = this.value;
                    }
                });
                
                dateTo.addEventListener('change', function() {
                    if (this.value && dateFrom.value && this.value < dateFrom.value) {
                        dateFrom.value = this.value;
                    }
                });
            }
            
            // Валидация вероятности
            const probMin = document.getElementById('probability_min');
            const probMax = document.getElementById('probability_max');
            
            if (probMin && probMax) {
                probMin.addEventListener('change', function() {
                    const min = parseInt(this.value) || 0;
                    const max = parseInt(probMax.value) || 100;
                    
                    if (min > max) {
                        probMax.value = min;
                    }
                });
                
                probMax.addEventListener('change', function() {
                    const min = parseInt(probMin.value) || 0;
                    const max = parseInt(this.value) || 100;
                    
                    if (max < min) {
                        probMin.value = max;
                    }
                });
            }
        });
    </script>
</body>
</html>