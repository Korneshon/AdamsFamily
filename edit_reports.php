<?php
// edit_reports.php - Редактирование и создание отчетов
require_once 'config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Получение списка типов отчетов
$report_types = [
    'financial' => 'Финансовый',
    'operational' => 'Операционный',
    'analytical' => 'Аналитический'
];

// Если передан ID отчета для редактирования
$editing_report = null;
$editing_criteria = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editing_report = getReportById($pdo, $_GET['edit'], $_SESSION['user_id']);
    if (!$editing_report) {
        $error = 'Отчет не найден или у вас нет прав для его редактирования';
    } else {
        // Декодируем критерии для формы
        if (!empty($editing_report['criteria'])) {
            $editing_criteria = json_decode($editing_report['criteria'], true) ?? [];
        }
    }
}

// Обработка формы создания/редактирования отчета
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'financial';
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    
    // Формируем критерии на основе типа отчета
    $criteria = [];
    switch ($type) {
        case 'financial':
            $criteria = [
                'service' => $_POST['service'] ?? '',
                'stage' => $_POST['stage'] ?? '',
                'manager' => $_POST['manager'] ?? '',
                'segment' => $_POST['segment'] ?? '',
                'date_from' => $_POST['date_from'] ?? '',
                'date_to' => $_POST['date_to'] ?? '',
                'min_revenue' => $_POST['min_revenue'] ?? '',
                'max_revenue' => $_POST['max_revenue'] ?? ''
            ];
            break;
            
        case 'operational':
            $criteria = [
                'project_type' => $_POST['project_type'] ?? '',
                'status' => $_POST['status'] ?? '',
                'priority' => $_POST['priority'] ?? '',
                'department' => $_POST['department'] ?? '',
                'date_from' => $_POST['date_from'] ?? '',
                'date_to' => $_POST['date_to'] ?? '',
                'team_size' => $_POST['team_size'] ?? ''
            ];
            break;
            
        case 'analytical':
            $criteria = [
                'analysis_type' => $_POST['analysis_type'] ?? '',
                'metrics' => $_POST['metrics'] ?? [],
                'time_period' => $_POST['time_period'] ?? '',
                'comparison_type' => $_POST['comparison_type'] ?? '',
                'depth_analysis' => $_POST['depth_analysis'] ?? ''
            ];
            break;
    }
    
    $criteria_json = json_encode($criteria, JSON_UNESCAPED_UNICODE);
    
    // Валидация данных
    if (empty($title)) {
        $error = 'Название отчета обязательно для заполнения';
    } else {
        if (isset($_POST['report_id']) && !empty($_POST['report_id'])) {
            // Редактирование существующего отчета
            $report_id = $_POST['report_id'];
            if (updateReport($pdo, $report_id, $title, $description, $type, $criteria_json, $is_public, $_SESSION['user_id'])) {
                $success = 'Отчет успешно обновлен';
                // Обновляем данные редактируемого отчета
                $editing_report = getReportById($pdo, $report_id, $_SESSION['user_id']);
                if (!empty($editing_report['criteria'])) {
                    $editing_criteria = json_decode($editing_report['criteria'], true) ?? [];
                }
            } else {
                $error = 'Ошибка при обновлении отчета';
            }
        } else {
            // Создание нового отчета
            $report_id = createReport($pdo, $title, $description, $type, $criteria_json, $_SESSION['user_id'], $is_public);
            if ($report_id) {
                $success = 'Отчет успешно создан';
                // Очищаем форму после успешного создания
                $_POST = [];
                $editing_report = null;
                $editing_criteria = [];
            } else {
                $error = 'Ошибка при создании отчета';
            }
        }
    }
}

// Получение списка отчетов пользователя
$user_reports = getUserReports($pdo, $_SESSION['user_id'], false);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $editing_report ? 'Редактирование отчета' : 'Создание отчета'; ?> - Ростелеком</title>
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
    .nav-bar {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        padding: 30px 40px;
        border-radius: 20px 20px 0 0;
        margin-bottom: 0;
        box-shadow: 0 20px 40px rgba(120, 0, 255, 0.15);
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
        color: white;
        font-size: 2rem;
        font-weight: 700;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }

    .breadcrumb {
        background: var(--bg-white);
        padding: 20px 30px;
        border-bottom: 1px solid var(--border-light);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }

    .breadcrumb a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .breadcrumb a:hover {
        color: var(--primary-dark);
        text-shadow: 0 0 10px var(--primary-light);
    }

    .content-area {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 30px;
        margin-top: 30px;
    }

    @media (max-width: 1200px) {
        .content-area {
            grid-template-columns: 1fr;
        }
    }

    /* Анимированные секции */
    .report-form, .reports-sidebar {
        background: var(--bg-white);
        padding: 30px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        margin-bottom: 25px;
        border: 1px solid var(--border-light);
        transition: transform 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .report-form::before, .reports-sidebar::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }

    .report-form:hover, .reports-sidebar:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(120, 0, 255, 0.15);
    }

    /* Заголовки */
    h1, h2 {
        color: var(--primary);
        margin-bottom: 25px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    h1 {
        font-size: 1.8rem;
    }

    h2 {
        font-size: 1.4rem;
    }

    /* Формы */
    .form-section {
        background: var(--bg-gray);
        padding: 25px;
        border-radius: 15px;
        margin-bottom: 25px;
        border-left: 5px solid var(--primary);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .form-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(120, 0, 255, 0.05), transparent);
        transition: left 0.6s ease;
    }

    .form-section:hover::before {
        left: 100%;
    }

    .form-section h3 {
        color: var(--primary);
        margin-bottom: 20px;
        font-size: 1.2rem;
        font-weight: 600;
    }

    .form-group {
        margin-bottom: 25px;
    }

    .form-group label {
        display: block;
        margin-bottom: 10px;
        color: var(--text-dark);
        font-weight: 600;
    }

    .required::after {
        content: " *";
        color: var(--danger);
    }

    .form-control {
        width: 100%;
        padding: 15px 20px;
        border: 2px solid var(--border-light);
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: var(--bg-white);
    }

    .form-control:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(120, 0, 255, 0.1);
        transform: translateY(-2px);
    }

    textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }

    /* Критерии */
    .criteria-section {
        display: none;
        background: linear-gradient(135deg, #f0f8ff 0%, #f8faff 100%);
        padding: 25px;
        border-radius: 15px;
        margin-top: 20px;
        border: 2px dashed var(--primary-light);
    }

    .criteria-field {
        margin-bottom: 20px;
    }

    .criteria-field label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--text-dark);
    }

    .criteria-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    @media (max-width: 768px) {
        .criteria-row {
            grid-template-columns: 1fr;
        }
    }

    .checkbox-group {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }

    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        background: var(--bg-white);
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .checkbox-item:hover {
        background: var(--primary-light);
        color: white;
        transform: translateX(5px);
    }

    .checkbox-item:hover label {
        color: white;
    }

    /* Кнопки */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 15px 25px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 1rem;
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

    .btn-success {
        background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    }

    .btn-success:hover {
        background: linear-gradient(135deg, #34d399 0%, var(--success) 100%);
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
    }

    .btn-danger {
        background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
        box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
    }

    .btn-danger:hover {
        background: linear-gradient(135deg, #f87171 0%, var(--danger) 100%);
        box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
    }

    /* Сообщения */
    .error-message {
        color: var(--danger);
        padding: 20px;
        background: linear-gradient(135deg, #fef2f2, #fecaca);
        border-radius: 15px;
        margin-bottom: 25px;
        border-left: 5px solid var(--danger);
        font-weight: 600;
        box-shadow: 0 5px 15px rgba(239, 68, 68, 0.1);
    }

    .success-message {
        color: #065f46;
        padding: 20px;
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        border-radius: 15px;
        margin-bottom: 25px;
        border-left: 5px solid var(--success);
        font-weight: 600;
        box-shadow: 0 5px 15px rgba(16, 185, 129, 0.1);
    }

    /* Список отчетов */
    .report-list {
        list-style: none;
        margin-top: 20px;
    }

    .report-item {
        background: var(--bg-gray);
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 15px;
        border-left: 5px solid var(--primary);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .report-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(120, 0, 255, 0.05), transparent);
        transition: left 0.6s ease;
    }

    .report-item:hover::before {
        left: 100%;
    }

    .report-item:hover {
        transform: translateX(8px);
        box-shadow: 0 8px 20px rgba(120, 0, 255, 0.1);
    }

    .report-item a {
        color: var(--text-dark);
        text-decoration: none;
        display: block;
    }

    .report-title {
        font-weight: 700;
        margin-bottom: 8px;
        font-size: 1.1rem;
        color: var(--text-dark);
    }

    .report-meta {
        display: flex;
        justify-content: space-between;
        font-size: 0.9rem;
        color: var(--text-light);
        flex-wrap: wrap;
        gap: 10px;
    }

    /* Состояние пустоты */
    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: var(--text-light);
    }

    .empty-state i {
        font-size: 3rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 15px;
    }

    .empty-state p {
        color: var(--text-dark);
        margin-bottom: 15px;
        font-size: 1.1rem;
    }

    .help-text {
        font-size: 0.85rem;
        color: var(--text-light);
        margin-top: 8px;
        font-style: italic;
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

    .report-form, .reports-sidebar, .form-section {
        animation: fadeInUp 0.6s ease-out;
    }

    /* Адаптивность для мобильных */
    @media (max-width: 768px) {
        body {
            padding: 15px;
        }
        
        .nav-bar {
            padding: 25px;
            text-align: center;
        }
        
        .nav-bar h1 {
            font-size: 1.6rem;
        }
        
        .breadcrumb {
            padding: 15px 20px;
        }
        
        .content-area {
            gap: 20px;
        }
        
        .report-form, .reports-sidebar {
            padding: 20px;
        }
        
        .form-section {
            padding: 20px;
        }
        
        .criteria-row {
            grid-template-columns: 1fr;
        }
        
        .checkbox-group {
            grid-template-columns: 1fr;
        }
        
        .btn {
            padding: 12px 20px;
            margin-bottom: 10px;
            width: 100%;
            justify-content: center;
        }
    }

    /* Прогресс-бар для визуализации */
    .progress-bar {
        width: 100%;
        height: 8px;
        background-color: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        margin: 15px 0;
        position: relative;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        border-radius: 10px;
        position: relative;
        transition: width 1.5s ease-in-out;
    }

    .progress-fill::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        bottom: 0;
        right: 0;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        animation: shimmer 2s infinite;
    }

    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
    </style>
</head>
<body>
    <div class="container">
        <!-- Навигационная панель -->
        <div class="nav-bar">
            <h1>Ростелеком - Управление отчетами</h1>
        </div>

        <!-- Хлебные крошки -->
        <div class="breadcrumb">
            <a href="index.php">Дашборд</a> &raquo; 
            <a href="reports.php">Просмотр отчетов</a> &raquo; 
            <span><?php echo $editing_report ? 'Редактирование отчета' : 'Создание отчета'; ?></span>
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

        <div class="content-area">
            <!-- Основная форма -->
            <div class="report-form">
                <h1><?php echo $editing_report ? 'Редактирование отчета' : 'Создание нового отчета'; ?></h1>
                
                <form method="POST" action="">
                    <?php if ($editing_report): ?>
                        <input type="hidden" name="report_id" value="<?php echo $editing_report['id']; ?>">
                    <?php endif; ?>

                    <!-- Основная информация -->
                    <div class="form-section">
                        <h3>Основная информация</h3>
                        
                        <div class="form-group">
                            <label for="title" class="required">Название отчета</label>
                            <input type="text" id="title" name="title" class="form-control" 
                                   value="<?php echo $editing_report ? htmlspecialchars($editing_report['title']) : (isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''); ?>" 
                                   placeholder="Введите название отчета" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Описание отчета</label>
                            <textarea id="description" name="description" class="form-control" 
                                      placeholder="Опишите цель и содержание отчета"><?php 
                                echo $editing_report ? htmlspecialchars($editing_report['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '');
                            ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="type" class="required">Тип отчета</label>
                            <select id="type" name="type" class="form-control" required>
                                <?php foreach ($report_types as $value => $name): ?>
                                    <option value="<?php echo $value; ?>" 
                                        <?php echo ($editing_report && $editing_report['type'] == $value) || (isset($_POST['type']) && $_POST['type'] == $value) ? 'selected' : ''; ?>>
                                        <?php echo $name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Критерии для финансовых отчетов -->
                    <div id="financial-criteria" class="criteria-section">
                        <h3>Критерии финансового отчета</h3>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Услуга:</label>
                                <input type="text" name="service" class="form-control" 
                                       value="<?php echo isset($editing_criteria['service']) ? htmlspecialchars($editing_criteria['service']) : ''; ?>">
                            </div>
                            <div class="criteria-field">
                                <label>Стадия:</label>
                                <input type="text" name="stage" class="form-control" 
                                       value="<?php echo isset($editing_criteria['stage']) ? htmlspecialchars($editing_criteria['stage']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Менеджер:</label>
                                <input type="text" name="manager" class="form-control" 
                                       value="<?php echo isset($editing_criteria['manager']) ? htmlspecialchars($editing_criteria['manager']) : ''; ?>">
                            </div>
                            <div class="criteria-field">
                                <label>Сегмент:</label>
                                <input type="text" name="segment" class="form-control" 
                                       value="<?php echo isset($editing_criteria['segment']) ? htmlspecialchars($editing_criteria['segment']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Дата с:</label>
                                <input type="date" name="date_from" class="form-control" 
                                       value="<?php echo isset($editing_criteria['date_from']) ? htmlspecialchars($editing_criteria['date_from']) : ''; ?>">
                            </div>
                            <div class="criteria-field">
                                <label>Дата по:</label>
                                <input type="date" name="date_to" class="form-control" 
                                       value="<?php echo isset($editing_criteria['date_to']) ? htmlspecialchars($editing_criteria['date_to']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Минимальная выручка:</label>
                                <input type="number" name="min_revenue" class="form-control" 
                                       value="<?php echo isset($editing_criteria['min_revenue']) ? htmlspecialchars($editing_criteria['min_revenue']) : ''; ?>">
                                <div class="help-text">В рублях</div>
                            </div>
                            <div class="criteria-field">
                                <label>Максимальная выручка:</label>
                                <input type="number" name="max_revenue" class="form-control" 
                                       value="<?php echo isset($editing_criteria['max_revenue']) ? htmlspecialchars($editing_criteria['max_revenue']) : ''; ?>">
                                <div class="help-text">В рублях</div>
                            </div>
                        </div>
                    </div>

                    <!-- Критерии для операционных отчетов -->
                    <div id="operational-criteria" class="criteria-section">
                        <h3>Критерии операционного отчета</h3>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Тип проекта:</label>
                                <input type="text" name="project_type" class="form-control" 
                                       value="<?php echo isset($editing_criteria['project_type']) ? htmlspecialchars($editing_criteria['project_type']) : ''; ?>">
                            </div>
                            <div class="criteria-field">
                                <label>Статус:</label>
                                <select name="status" class="form-control">
                                    <option value="">Все</option>
                                    <option value="active" <?php echo (isset($editing_criteria['status']) && $editing_criteria['status'] == 'active') ? 'selected' : ''; ?>>Активный</option>
                                    <option value="completed" <?php echo (isset($editing_criteria['status']) && $editing_criteria['status'] == 'completed') ? 'selected' : ''; ?>>Завершен</option>
                                    <option value="pending" <?php echo (isset($editing_criteria['status']) && $editing_criteria['status'] == 'pending') ? 'selected' : ''; ?>>Ожидание</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Приоритет:</label>
                                <select name="priority" class="form-control">
                                    <option value="">Все</option>
                                    <option value="high" <?php echo (isset($editing_criteria['priority']) && $editing_criteria['priority'] == 'high') ? 'selected' : ''; ?>>Высокий</option>
                                    <option value="medium" <?php echo (isset($editing_criteria['priority']) && $editing_criteria['priority'] == 'medium') ? 'selected' : ''; ?>>Средний</option>
                                    <option value="low" <?php echo (isset($editing_criteria['priority']) && $editing_criteria['priority'] == 'low') ? 'selected' : ''; ?>>Низкий</option>
                                </select>
                            </div>
                            <div class="criteria-field">
                                <label>Размер команды:</label>
                                <input type="number" name="team_size" class="form-control" 
                                       value="<?php echo isset($editing_criteria['team_size']) ? htmlspecialchars($editing_criteria['team_size']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Отдел:</label>
                                <input type="text" name="department" class="form-control" 
                                       value="<?php echo isset($editing_criteria['department']) ? htmlspecialchars($editing_criteria['department']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Дата с:</label>
                                <input type="date" name="date_from" class="form-control" 
                                       value="<?php echo isset($editing_criteria['date_from']) ? htmlspecialchars($editing_criteria['date_from']) : ''; ?>">
                            </div>
                            <div class="criteria-field">
                                <label>Дата по:</label>
                                <input type="date" name="date_to" class="form-control" 
                                       value="<?php echo isset($editing_criteria['date_to']) ? htmlspecialchars($editing_criteria['date_to']) : ''; ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Критерии для аналитических отчетов -->
                    <div id="analytical-criteria" class="criteria-section">
                        <h3>Критерии аналитического отчета</h3>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Тип анализа:</label>
                                <select name="analysis_type" class="form-control">
                                    <option value="trend" <?php echo (isset($editing_criteria['analysis_type']) && $editing_criteria['analysis_type'] == 'trend') ? 'selected' : ''; ?>>Анализ трендов</option>
                                    <option value="comparative" <?php echo (isset($editing_criteria['analysis_type']) && $editing_criteria['analysis_type'] == 'comparative') ? 'selected' : ''; ?>>Сравнительный анализ</option>
                                    <option value="predictive" <?php echo (isset($editing_criteria['analysis_type']) && $editing_criteria['analysis_type'] == 'predictive') ? 'selected' : ''; ?>>Прогнозный анализ</option>
                                    <option value="diagnostic" <?php echo (isset($editing_criteria['analysis_type']) && $editing_criteria['analysis_type'] == 'diagnostic') ? 'selected' : ''; ?>>Диагностический анализ</option>
                                </select>
                            </div>
                            <div class="criteria-field">
                                <label>Период времени:</label>
                                <select name="time_period" class="form-control">
                                    <option value="monthly" <?php echo (isset($editing_criteria['time_period']) && $editing_criteria['time_period'] == 'monthly') ? 'selected' : ''; ?>>Ежемесячный</option>
                                    <option value="quarterly" <?php echo (isset($editing_criteria['time_period']) && $editing_criteria['time_period'] == 'quarterly') ? 'selected' : ''; ?>>Квартальный</option>
                                    <option value="yearly" <?php echo (isset($editing_criteria['time_period']) && $editing_criteria['time_period'] == 'yearly') ? 'selected' : ''; ?>>Годовой</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="criteria-field">
                            <label>Метрики для анализа:</label>
                            <div class="checkbox-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" name="metrics[]" value="revenue" 
                                        <?php echo (isset($editing_criteria['metrics']) && in_array('revenue', $editing_criteria['metrics'])) ? 'checked' : ''; ?>>
                                    <label>Выручка</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="metrics[]" value="profit" 
                                        <?php echo (isset($editing_criteria['metrics']) && in_array('profit', $editing_criteria['metrics'])) ? 'checked' : ''; ?>>
                                    <label>Прибыль</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="metrics[]" value="customers" 
                                        <?php echo (isset($editing_criteria['metrics']) && in_array('customers', $editing_criteria['metrics'])) ? 'checked' : ''; ?>>
                                    <label>Количество клиентов</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="metrics[]" value="efficiency" 
                                        <?php echo (isset($editing_criteria['metrics']) && in_array('efficiency', $editing_criteria['metrics'])) ? 'checked' : ''; ?>>
                                    <label>Эффективность</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="metrics[]" value="conversion" 
                                        <?php echo (isset($editing_criteria['metrics']) && in_array('conversion', $editing_criteria['metrics'])) ? 'checked' : ''; ?>>
                                    <label>Конверсия</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Тип сравнения:</label>
                                <select name="comparison_type" class="form-control">
                                    <option value="period" <?php echo (isset($editing_criteria['comparison_type']) && $editing_criteria['comparison_type'] == 'period') ? 'selected' : ''; ?>>Сравнение периодов</option>
                                    <option value="segment" <?php echo (isset($editing_criteria['comparison_type']) && $editing_criteria['comparison_type'] == 'segment') ? 'selected' : ''; ?>>Сравнение сегментов</option>
                                    <option value="target" <?php echo (isset($editing_criteria['comparison_type']) && $editing_criteria['comparison_type'] == 'target') ? 'selected' : ''; ?>>Сравнение с планом</option>
                                    <option value="competitor" <?php echo (isset($editing_criteria['comparison_type']) && $editing_criteria['comparison_type'] == 'competitor') ? 'selected' : ''; ?>>Сравнение с конкурентами</option>
                                </select>
                            </div>
                            <div class="criteria-field">
                                <label>Глубина анализа:</label>
                                <select name="depth_analysis" class="form-control">
                                    <option value="basic" <?php echo (isset($editing_criteria['depth_analysis']) && $editing_criteria['depth_analysis'] == 'basic') ? 'selected' : ''; ?>>Базовый</option>
                                    <option value="detailed" <?php echo (isset($editing_criteria['depth_analysis']) && $editing_criteria['depth_analysis'] == 'detailed') ? 'selected' : ''; ?>>Детальный</option>
                                    <option value="comprehensive" <?php echo (isset($editing_criteria['depth_analysis']) && $editing_criteria['depth_analysis'] == 'comprehensive') ? 'selected' : ''; ?>>Комплексный</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Настройки доступа -->
                    <div class="form-section">
                        <h3>Настройки доступа</h3>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_public" value="1" 
                                    <?php echo ($editing_report && $editing_report['is_public']) || (isset($_POST['is_public']) && $_POST['is_public']) ? 'checked' : ''; ?>>
                                Сделать отчет публичным
                            </label>
                            <div class="help-text">Публичные отчеты видны всем пользователям системы</div>
                        </div>
                    </div>

                    <!-- Кнопки действий -->
                    <div class="form-group" style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> 
                            <?php echo $editing_report ? 'Обновить отчет' : 'Создать отчет'; ?>
                        </button>
                        
                        <a href="reports.php" class="btn">
                            <i class="fas fa-arrow-left"></i> Назад к списку отчетов
                        </a>
                        
                        <?php if ($editing_report): ?>
                            <a href="edit_reports.php" class="btn">
                                <i class="fas fa-plus"></i> Создать новый
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Боковая панель с отчетами -->
            <div class="reports-sidebar">
                <h2>Мои отчеты</h2>
                
                <?php if (empty($user_reports)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <p>У вас пока нет отчетов</p>
                    </div>
                <?php else: ?>
                    <ul class="report-list">
                        <?php foreach ($user_reports as $report): ?>
                            <li class="report-item">
                                <a href="edit_reports.php?edit=<?php echo $report['id']; ?>">
                                    <div class="report-title"><?php echo htmlspecialchars($report['title']); ?></div>
                                    <div class="report-meta">
                                        <span><?php echo $report_types[$report['type']] ?? $report['type']; ?></span>
                                        <span><?php echo date('d.m.Y', strtotime($report['created_at'])); ?></span>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <div style="margin-top: 15px; text-align: center;">
                        <a href="reports.php" class="btn" style="display: block;">
                            <i class="fas fa-list"></i> Все мои отчеты
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Функция для показа/скрытия критериев в зависимости от типа отчета
        function showCriteria() {
            // Скрыть все секции критериев
            document.querySelectorAll('.criteria-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Показать соответствующую секцию
            const type = document.getElementById('type').value;
            const criteriaSection = document.getElementById(type + '-criteria');
            if (criteriaSection) {
                criteriaSection.style.display = 'block';
            }
        }
        
        // Инициализация при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            showCriteria();
            
            // Обработчик изменения типа отчета
            document.getElementById('type').addEventListener('change', showCriteria);
            
            // Если редактируем отчет, убедимся, что правильные критерии показаны
            <?php if ($editing_report): ?>
            showCriteria();
            <?php endif; ?>
        });
        
        // Автоматическое скрытие сообщений через 5 секунд
        setTimeout(function() {
            const errorMsg = document.querySelector('.error-message');
            const successMsg = document.querySelector('.success-message');
            
            if (errorMsg) errorMsg.style.display = 'none';
            if (successMsg) successMsg.style.display = 'none';
        }, 5000);
    </script>
</body>
</html>