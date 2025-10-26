<?php

require_once 'config.php';


if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';


$report_types = [
    'financial' => 'Финансовый',
    'operational' => 'Операционный',
    'analytical' => 'Аналитический'
];


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'financial';
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    
    
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
                'max_revenue' => $_POST['max_revenue'] ?? '',
                'revenue_type' => $_POST['revenue_type'] ?? ''
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
                'team_size' => $_POST['team_size'] ?? '',
                'location' => $_POST['location'] ?? ''
            ];
            break;
            
        case 'analytical':
            $criteria = [
                'analysis_type' => $_POST['analysis_type'] ?? '',
                'metrics' => $_POST['metrics'] ?? [],
                'time_period' => $_POST['time_period'] ?? '',
                'comparison_type' => $_POST['comparison_type'] ?? '',
                'depth_analysis' => $_POST['depth_analysis'] ?? '',
                'segmentation' => $_POST['segmentation'] ?? ''
            ];
            break;
    }
    
    $criteria_json = json_encode($criteria, JSON_UNESCAPED_UNICODE);
    
    
    if (empty($title)) {
        $error = 'Название отчета обязательно для заполнения';
    } else {
       
        $report_id = createReport($pdo, $title, $description, $type, $criteria_json, $_SESSION['user_id'], $is_public);
        if ($report_id) {
            $success = 'Отчет "' . htmlspecialchars($title) . '" успешно создан!';
            
            $_POST = [];
        } else {
            $error = 'Ошибка при создании отчета. Пожалуйста, попробуйте еще раз.';
        }
    }
}


$recent_reports = getUserReports($pdo, $_SESSION['user_id'], false);
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
            background-color: var(--rtk-gray);
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .nav-bar {
            background: var(--rtk-blue);
            color: white;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
        }

        .nav-bar h1 {
            margin: 0;
            font-size: 1.5rem;
        }

        .breadcrumb {
            background: var(--rtk-white);
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
        }

        .breadcrumb a {
            color: var(--rtk-blue);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .content-area {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 25px;
            margin-top: 20px;
        }

        @media (max-width: 968px) {
            .content-area {
                grid-template-columns: 1fr;
            }
        }

        .report-form {
            background: var(--rtk-white);
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }

        .info-sidebar {
            background: var(--rtk-white);
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            height: fit-content;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--rtk-dark-gray);
            font-weight: 600;
        }

        .required::after {
            content: " *";
            color: var(--rtk-red);
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--rtk-blue);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 51, 160, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
            line-height: 1.5;
        }

        .form-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 5px solid var(--rtk-blue);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .form-section h3 {
            color: var(--rtk-blue);
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section h3 i {
            font-size: 1.2rem;
        }

        .criteria-section {
            display: none;
            background: #f0f8ff;
            padding: 25px;
            border-radius: 8px;
            margin-top: 20px;
            border: 2px solid #d1ecf1;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .criteria-field {
            margin-bottom: 20px;
        }

        .criteria-field label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--rtk-dark-gray);
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
            gap: 12px;
            margin-top: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: white;
            border-radius: 6px;
            border: 1px solid #e1e5e9;
            transition: all 0.2s;
        }

        .checkbox-item:hover {
            border-color: var(--rtk-blue);
            background: #f8f9ff;
        }

        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            background-color: var(--rtk-blue);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            margin-right: 12px;
        }

        .btn:hover {
            background-color: var(--rtk-light-blue);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 51, 160, 0.2);
        }

        .btn-success {
            background-color: #28a745;
        }

        .btn-success:hover {
            background-color: #218838;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--rtk-blue);
            color: var(--rtk-blue);
        }

        .btn-outline:hover {
            background: var(--rtk-blue);
            color: white;
        }

        .error-message {
            color: var(--rtk-red);
            padding: 18px;
            background: linear-gradient(135deg, #ffe6e6 0%, #ffcccc 100%);
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 5px solid var(--rtk-red);
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(228, 0, 43, 0.1);
        }

        .success-message {
            color: #155724;
            padding: 18px;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 5px solid #28a745;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.1);
        }

        h1, h2 {
            color: var(--rtk-blue);
            margin-bottom: 25px;
        }

        .report-list {
            list-style: none;
            margin-top: 20px;
        }

        .report-item {
            padding: 16px;
            border-bottom: 1px solid #eee;
            transition: all 0.2s;
            border-radius: 6px;
            margin-bottom: 8px;
        }

        .report-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }

        .report-item:last-child {
            border-bottom: none;
        }

        .report-item a {
            color: var(--rtk-dark-gray);
            text-decoration: none;
            display: block;
        }

        .report-item a:hover {
            color: var(--rtk-blue);
        }

        .report-title {
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 1rem;
        }

        .report-meta {
            font-size: 0.85rem;
            color: #666;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .report-type {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .type-financial { background: #e3f2fd; color: #1976d2; }
        .type-operational { background: #e8f5e8; color: #388e3c; }
        .type-analytical { background: #fff3e0; color: #f57c00; }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--rtk-dark-gray);
        }

        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 15px;
        }

        .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 6px;
            line-height: 1.4;
        }

        .info-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid var(--rtk-blue);
        }

        .info-card h3 {
            color: var(--rtk-blue);
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .form-actions {
            text-align: center;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #eee;
        }

        .type-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .type-option {
            padding: 20px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }

        .type-option:hover {
            border-color: var(--rtk-blue);
            transform: translateY(-3px);
        }

        .type-option.selected {
            border-color: var(--rtk-blue);
            background: var(--rtk-blue);
            color: white;
        }

        .type-option i {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
        }

        input[type="radio"] {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Навигационная панель -->
        <div class="nav-bar">
            <h1><i class="fas fa-chart-bar"></i> Ростелеком - Создание отчета</h1>
        </div>

        <!-- Хлебные крошки -->
        <div class="breadcrumb">
            <a href="index.php"><i class="fas fa-home"></i> Дашборд</a> &raquo; 
            <a href="reports.php"><i class="fas fa-file-alt"></i> Просмотр отчетов</a> &raquo; 
            <span><i class="fas fa-plus"></i> Создание отчета</span>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <div><?php echo htmlspecialchars($success); ?></div>
            </div>
        <?php endif; ?>

        <div class="content-area">
            <!-- Основная форма -->
            <div class="report-form">
                <h1><i class="fas fa-plus-circle"></i> Создание нового отчета</h1>
                
                <form method="POST" action="" id="reportForm">
                    <!-- Основная информация -->
                    <div class="form-section">
                        <h3><i class="fas fa-info-circle"></i> Основная информация</h3>
                        
                        <div class="form-group">
                            <label for="title" class="required">Название отчета</label>
                            <input type="text" id="title" name="title" class="form-control" 
                                   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                                   placeholder="Введите краткое и понятное название отчета" required>
                            <div class="help-text">Например: "Финансовый отчет за Q1 2024" или "Анализ эффективности проектов"</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Описание отчета</label>
                            <textarea id="description" name="description" class="form-control" 
                                      placeholder="Опишите цель, содержание и ключевые моменты отчета"><?php 
                                echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '';
                            ?></textarea>
                            <div class="help-text">Это описание поможет другим пользователям понять суть вашего отчета</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Тип отчета</label>
                            <div class="type-selector">
                                <label class="type-option" for="type_financial">
                                    <input type="radio" id="type_financial" name="type" value="financial" 
                                           <?php echo (isset($_POST['type']) && $_POST['type'] == 'financial') || !isset($_POST['type']) ? 'checked' : ''; ?>>
                                    <i class="fas fa-money-bill-wave"></i>
                                    <div>Финансовый</div>
                                </label>
                                <label class="type-option" for="type_operational">
                                    <input type="radio" id="type_operational" name="type" value="operational"
                                           <?php echo isset($_POST['type']) && $_POST['type'] == 'operational' ? 'checked' : ''; ?>>
                                    <i class="fas fa-cogs"></i>
                                    <div>Операционный</div>
                                </label>
                                <label class="type-option" for="type_analytical">
                                    <input type="radio" id="type_analytical" name="type" value="analytical"
                                           <?php echo isset($_POST['type']) && $_POST['type'] == 'analytical' ? 'checked' : ''; ?>>
                                    <i class="fas fa-chart-line"></i>
                                    <div>Аналитический</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Критерии для финансовых отчетов -->
                    <div id="financial-criteria" class="criteria-section">
                        <h3><i class="fas fa-money-bill-wave"></i> Критерии финансового отчета</h3>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Услуга:</label>
                                <input type="text" name="service" class="form-control" 
                                       value="<?php echo isset($_POST['service']) ? htmlspecialchars($_POST['service']) : ''; ?>" 
                                       placeholder="Например: Интернет-провайдинг">
                            </div>
                            <div class="criteria-field">
                                <label>Стадия проекта:</label>
                                <select name="stage" class="form-control">
                                    <option value="">Все стадии</option>
                                    <option value="planning" <?php echo isset($_POST['stage']) && $_POST['stage'] == 'planning' ? 'selected' : ''; ?>>Планирование</option>
                                    <option value="execution" <?php echo isset($_POST['stage']) && $_POST['stage'] == 'execution' ? 'selected' : ''; ?>>Выполнение</option>
                                    <option value="completion" <?php echo isset($_POST['stage']) && $_POST['stage'] == 'completion' ? 'selected' : ''; ?>>Завершение</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Ответственный менеджер:</label>
                                <input type="text" name="manager" class="form-control" 
                                       value="<?php echo isset($_POST['manager']) ? htmlspecialchars($_POST['manager']) : ''; ?>" 
                                       placeholder="ФИО менеджера">
                            </div>
                            <div class="criteria-field">
                                <label>Сегмент рынка:</label>
                                <input type="text" name="segment" class="form-control" 
                                       value="<?php echo isset($_POST['segment']) ? htmlspecialchars($_POST['segment']) : ''; ?>" 
                                       placeholder="Например: B2B, B2C">
                            </div>
                        </div>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Период с:</label>
                                <input type="date" name="date_from" class="form-control" 
                                       value="<?php echo isset($_POST['date_from']) ? htmlspecialchars($_POST['date_from']) : ''; ?>">
                            </div>
                            <div class="criteria-field">
                                <label>Период по:</label>
                                <input type="date" name="date_to" class="form-control" 
                                       value="<?php echo isset($_POST['date_to']) ? htmlspecialchars($_POST['date_to']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Минимальная выручка (руб.):</label>
                                <input type="number" name="min_revenue" class="form-control" 
                                       value="<?php echo isset($_POST['min_revenue']) ? htmlspecialchars($_POST['min_revenue']) : ''; ?>" 
                                       placeholder="0">
                            </div>
                            <div class="criteria-field">
                                <label>Максимальная выручка (руб.):</label>
                                <input type="number" name="max_revenue" class="form-control" 
                                       value="<?php echo isset($_POST['max_revenue']) ? htmlspecialchars($_POST['max_revenue']) : ''; ?>" 
                                       placeholder="1000000">
                            </div>
                        </div>
                        
                        <div class="criteria-field">
                            <label>Тип выручки:</label>
                            <select name="revenue_type" class="form-control">
                                <option value="">Все типы</option>
                                <option value="recurring" <?php echo isset($_POST['revenue_type']) && $_POST['revenue_type'] == 'recurring' ? 'selected' : ''; ?>>Регулярная</option>
                                <option value="one_time" <?php echo isset($_POST['revenue_type']) && $_POST['revenue_type'] == 'one_time' ? 'selected' : ''; ?>>Разовая</option>
                                <option value="project" <?php echo isset($_POST['revenue_type']) && $_POST['revenue_type'] == 'project' ? 'selected' : ''; ?>>Проектная</option>
                            </select>
                        </div>
                    </div>

                    <!-- Критерии для операционных отчетов -->
                    <div id="operational-criteria" class="criteria-section">
                        <h3><i class="fas fa-cogs"></i> Критерии операционного отчета</h3>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Тип проекта:</label>
                                <input type="text" name="project_type" class="form-control" 
                                       value="<?php echo isset($_POST['project_type']) ? htmlspecialchars($_POST['project_type']) : ''; ?>" 
                                       placeholder="Например: IT, Строительство">
                            </div>
                            <div class="criteria-field">
                                <label>Статус проекта:</label>
                                <select name="status" class="form-control">
                                    <option value="">Все статусы</option>
                                    <option value="active" <?php echo isset($_POST['status']) && $_POST['status'] == 'active' ? 'selected' : ''; ?>>Активный</option>
                                    <option value="completed" <?php echo isset($_POST['status']) && $_POST['status'] == 'completed' ? 'selected' : ''; ?>>Завершен</option>
                                    <option value="pending" <?php echo isset($_POST['status']) && $_POST['status'] == 'pending' ? 'selected' : ''; ?>>Ожидание</option>
                                    <option value="cancelled" <?php echo isset($_POST['status']) && $_POST['status'] == 'cancelled' ? 'selected' : ''; ?>>Отменен</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Приоритет:</label>
                                <select name="priority" class="form-control">
                                    <option value="">Все приоритеты</option>
                                    <option value="high" <?php echo isset($_POST['priority']) && $_POST['priority'] == 'high' ? 'selected' : ''; ?>>Высокий</option>
                                    <option value="medium" <?php echo isset($_POST['priority']) && $_POST['priority'] == 'medium' ? 'selected' : ''; ?>>Средний</option>
                                    <option value="low" <?php echo isset($_POST['priority']) && $_POST['priority'] == 'low' ? 'selected' : ''; ?>>Низкий</option>
                                </select>
                            </div>
                            <div class="criteria-field">
                                <label>Размер команды:</label>
                                <input type="number" name="team_size" class="form-control" 
                                       value="<?php echo isset($_POST['team_size']) ? htmlspecialchars($_POST['team_size']) : ''; ?>" 
                                       placeholder="Количество человек">
                            </div>
                        </div>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Отдел/Направление:</label>
                                <input type="text" name="department" class="form-control" 
                                       value="<?php echo isset($_POST['department']) ? htmlspecialchars($_POST['department']) : ''; ?>" 
                                       placeholder="Например: Разработка, Маркетинг">
                            </div>
                            <div class="criteria-field">
                                <label>Локация:</label>
                                <input type="text" name="location" class="form-control" 
                                       value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>" 
                                       placeholder="Например: Москва, Регионы">
                            </div>
                        </div>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Период с:</label>
                                <input type="date" name="date_from" class="form-control" 
                                       value="<?php echo isset($_POST['date_from']) ? htmlspecialchars($_POST['date_from']) : ''; ?>">
                            </div>
                            <div class="criteria-field">
                                <label>Период по:</label>
                                <input type="date" name="date_to" class="form-control" 
                                       value="<?php echo isset($_POST['date_to']) ? htmlspecialchars($_POST['date_to']) : ''; ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Критерии для аналитических отчетов -->
                    <div id="analytical-criteria" class="criteria-section">
                        <h3><i class="fas fa-chart-line"></i> Критерии аналитического отчета</h3>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Тип анализа:</label>
                                <select name="analysis_type" class="form-control">
                                    <option value="trend" <?php echo isset($_POST['analysis_type']) && $_POST['analysis_type'] == 'trend' ? 'selected' : ''; ?>>Анализ трендов</option>
                                    <option value="comparative" <?php echo isset($_POST['analysis_type']) && $_POST['analysis_type'] == 'comparative' ? 'selected' : ''; ?>>Сравнительный анализ</option>
                                    <option value="predictive" <?php echo isset($_POST['analysis_type']) && $_POST['analysis_type'] == 'predictive' ? 'selected' : ''; ?>>Прогнозный анализ</option>
                                    <option value="diagnostic" <?php echo isset($_POST['analysis_type']) && $_POST['analysis_type'] == 'diagnostic' ? 'selected' : ''; ?>>Диагностический анализ</option>
                                </select>
                            </div>
                            <div class="criteria-field">
                                <label>Период времени:</label>
                                <select name="time_period" class="form-control">
                                    <option value="monthly" <?php echo isset($_POST['time_period']) && $_POST['time_period'] == 'monthly' ? 'selected' : ''; ?>>Ежемесячный</option>
                                    <option value="quarterly" <?php echo isset($_POST['time_period']) && $_POST['time_period'] == 'quarterly' ? 'selected' : ''; ?>>Квартальный</option>
                                    <option value="yearly" <?php echo isset($_POST['time_period']) && $_POST['time_period'] == 'yearly' ? 'selected' : ''; ?>>Годовой</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="criteria-field">
                            <label>Метрики для анализа:</label>
                            <div class="checkbox-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" name="metrics[]" value="revenue" 
                                        <?php echo isset($_POST['metrics']) && in_array('revenue', $_POST['metrics']) ? 'checked' : ''; ?>>
                                    <label>Выручка</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="metrics[]" value="profit" 
                                        <?php echo isset($_POST['metrics']) && in_array('profit', $_POST['metrics']) ? 'checked' : ''; ?>>
                                    <label>Прибыль</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="metrics[]" value="customers" 
                                        <?php echo isset($_POST['metrics']) && in_array('customers', $_POST['metrics']) ? 'checked' : ''; ?>>
                                    <label>Количество клиентов</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="metrics[]" value="efficiency" 
                                        <?php echo isset($_POST['metrics']) && in_array('efficiency', $_POST['metrics']) ? 'checked' : ''; ?>>
                                    <label>Эффективность</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="metrics[]" value="conversion" 
                                        <?php echo isset($_POST['metrics']) && in_array('conversion', $_POST['metrics']) ? 'checked' : ''; ?>>
                                    <label>Конверсия</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="metrics[]" value="satisfaction" 
                                        <?php echo isset($_POST['metrics']) && in_array('satisfaction', $_POST['metrics']) ? 'checked' : ''; ?>>
                                    <label>Удовлетворенность</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="criteria-row">
                            <div class="criteria-field">
                                <label>Тип сравнения:</label>
                                <select name="comparison_type" class="form-control">
                                    <option value="period" <?php echo isset($_POST['comparison_type']) && $_POST['comparison_type'] == 'period' ? 'selected' : ''; ?>>Сравнение периодов</option>
                                    <option value="segment" <?php echo isset($_POST['comparison_type']) && $_POST['comparison_type'] == 'segment' ? 'selected' : ''; ?>>Сравнение сегментов</option>
                                    <option value="target" <?php echo isset($_POST['comparison_type']) && $_POST['comparison_type'] == 'target' ? 'selected' : ''; ?>>Сравнение с планом</option>
                                    <option value="competitor" <?php echo isset($_POST['comparison_type']) && $_POST['comparison_type'] == 'competitor' ? 'selected' : ''; ?>>Сравнение с конкурентами</option>
                                </select>
                            </div>
                            <div class="criteria-field">
                                <label>Глубина анализа:</label>
                                <select name="depth_analysis" class="form-control">
                                    <option value="basic" <?php echo isset($_POST['depth_analysis']) && $_POST['depth_analysis'] == 'basic' ? 'selected' : ''; ?>>Базовый</option>
                                    <option value="detailed" <?php echo isset($_POST['depth_analysis']) && $_POST['depth_analysis'] == 'detailed' ? 'selected' : ''; ?>>Детальный</option>
                                    <option value="comprehensive" <?php echo isset($_POST['depth_analysis']) && $_POST['depth_analysis'] == 'comprehensive' ? 'selected' : ''; ?>>Комплексный</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="criteria-field">
                            <label>Сегментация данных:</label>
                            <select name="segmentation" class="form-control">
                                <option value="">Без сегментации</option>
                                <option value="region" <?php echo isset($_POST['segmentation']) && $_POST['segmentation'] == 'region' ? 'selected' : ''; ?>>По регионам</option>
                                <option value="product" <?php echo isset($_POST['segmentation']) && $_POST['segmentation'] == 'product' ? 'selected' : ''; ?>>По продуктам</option>
                                <option value="department" <?php echo isset($_POST['segmentation']) && $_POST['segmentation'] == 'department' ? 'selected' : ''; ?>>По отделам</option>
                                <option value="customer_type" <?php echo isset($_POST['segmentation']) && $_POST['segmentation'] == 'customer_type' ? 'selected' : ''; ?>>По типам клиентов</option>
                            </select>
                        </div>
                    </div>

                    <!-- Настройки доступа -->
                    <div class="form-section">
                        <h3><i class="fas fa-shield-alt"></i> Настройки доступа</h3>
                        
                        <div class="form-group">
                            <label class="checkbox-item" style="display: inline-flex; padding: 0;">
                                <input type="checkbox" name="is_public" value="1" 
                                    <?php echo isset($_POST['is_public']) && $_POST['is_public'] ? 'checked' : ''; ?>>
                                <span style="margin-left: 8px;">Сделать отчет публичным</span>
                            </label>
                            <div class="help-text">Публичные отчеты видны всем пользователям системы. Если не отмечено, отчет будет доступен только вам.</div>
                        </div>
                    </div>

                    <!-- Кнопки действий -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus-circle"></i> Создать отчет
                        </button>
                        
                        <a href="reports.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Назад к отчетам
                        </a>
                        
                        <button type="reset" class="btn btn-outline">
                            <i class="fas fa-redo"></i> Очистить форму
                        </button>
                    </div>
                </form>
            </div>

            <!-- Боковая панель с информацией -->
            <div class="info-sidebar">
                <!-- Информационная карточка -->
                <div class="info-card">
                    <h3><i class="fas fa-lightbulb"></i> Советы по созданию отчетов</h3>
                    <ul style="padding-left: 20px; color: #666; line-height: 1.5;">
                        <li>Используйте понятные и описательные названия</li>
                        <li>Указывайте точные периоды для анализа</li>
                        <li>Выбирайте релевантные метрики для вашего типа отчета</li>
                        <li>Публичные отчеты видны всем пользователям системы</li>
                    </ul>
                </div>

                <!-- Последние отчеты -->
                <h2><i class="fas fa-history"></i> Последние отчеты</h2>
                
                <?php if (empty($recent_reports)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <p>У вас пока нет созданных отчетов</p>
                    </div>
                <?php else: ?>
                    <ul class="report-list">
                        <?php 
                        $displayed_reports = array_slice($recent_reports, 0, 5);
                        foreach ($displayed_reports as $report): 
                            $type_class = 'type-' . $report['type'];
                        ?>
                            <li class="report-item">
                                <a href="edit_reports.php?edit=<?php echo $report['id']; ?>">
                                    <div class="report-title"><?php echo htmlspecialchars($report['title']); ?></div>
                                    <div class="report-meta">
                                        <span class="report-type <?php echo $type_class; ?>">
                                            <?php echo $report_types[$report['type']] ?? $report['type']; ?>
                                        </span>
                                        <span><?php echo date('d.m.Y', strtotime($report['created_at'])); ?></span>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <div style="margin-top: 15px; text-align: center;">
                        <a href="reports.php" class="btn" style="display: block; width: 100%;">
                            <i class="fas fa-list"></i> Все мои отчеты
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Статистика -->
                <div class="info-card" style="margin-top: 25px;">
                    <h3><i class="fas fa-chart-pie"></i> Статистика</h3>
                    <div style="color: #666; line-height: 1.5;">
                        <div>Всего отчетов: <strong><?php echo count($recent_reports); ?></strong></div>
                        <div>Создано сегодня: <strong><?php 
                            $today = date('Y-m-d');
                            $today_reports = array_filter($recent_reports, function($report) use ($today) {
                                return date('Y-m-d', strtotime($report['created_at'])) === $today;
                            });
                            echo count($today_reports);
                        ?></strong></div>
                    </div>
                </div>
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
            const type = document.querySelector('input[name="type"]:checked').value;
            const criteriaSection = document.getElementById(type + '-criteria');
            if (criteriaSection) {
                criteriaSection.style.display = 'block';
            }
            
            // Обновить классы выбранных опций
            document.querySelectorAll('.type-option').forEach(option => {
                option.classList.remove('selected');
            });
            document.querySelector('input[name="type"]:checked').parentElement.classList.add('selected');
        }
        
        // Инициализация при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            showCriteria();
            
            // Обработчики изменения типа отчета
            document.querySelectorAll('input[name="type"]').forEach(radio => {
                radio.addEventListener('change', showCriteria);
            });
            
            // Обработчик сброса формы
            document.querySelector('button[type="reset"]').addEventListener('click', function() {
                setTimeout(showCriteria, 100);
            });
        });
        
        // Автоматическое скрытие сообщений через 5 секунд
        setTimeout(function() {
            const errorMsg = document.querySelector('.error-message');
            const successMsg = document.querySelector('.success-message');
            
            if (errorMsg) errorMsg.style.display = 'none';
            if (successMsg) successMsg.style.display = 'none';
        }, 5000);
        
        // Подтверждение при уходе со страницы с несохраненными данными
        let formChanged = false;
        const form = document.getElementById('reportForm');
        const formInputs = form.querySelectorAll('input, textarea, select');
        
        formInputs.forEach(input => {
            input.addEventListener('input', function() {
                formChanged = true;
            });
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'У вас есть несохраненные изменения. Вы уверены, что хотите покинуть страницу?';
            }
        });
        

        form.addEventListener('submit', function() {
            formChanged = false;
        });
    </script>
</body>
</html>