<?php

require_once 'config.php';

try {
   
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    
    $tables = ['users', 'services', 'payment_types', 'project_stages', 'segments', 
               'cost_types', 'revenue_statuses', 'cost_statuses', 'projects', 
               'project_revenue', 'project_costs', 'project_history', 'reports', 'report_access', 'report_history'];
    
    foreach ($tables as $table) {
        $pdo->exec("TRUNCATE TABLE $table");
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    
    
    $users = [
        ['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Администратор Системы', 'admin@rostelecom.ru', 'admin'],
        ['analyst', password_hash('analyst123', PASSWORD_DEFAULT), 'Иванов Иван', 'ivanov@rostelecom.ru', 'analyst'],
        ['user', password_hash('user123', PASSWORD_DEFAULT), 'Петров Петр', 'petrov@rostelecom.ru', 'user'],
        ['smirnov', password_hash('user123', PASSWORD_DEFAULT), 'Смирнов Алексей', 'smirnov@rostelecom.ru', 'user'],
        ['kuznetsov', password_hash('user123', PASSWORD_DEFAULT), 'Кузнецов Дмитрий', 'kuznetsov@rostelecom.ru', 'analyst']
    ];
    
    $userStmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
    foreach ($users as $user) {
        $userStmt->execute($user);
    }
    
    
    $services = ['Интернет', 'Телефония', 'Инфобез', 'Цифровые сервисы', 'Облачные сервисы', 'Отраслевые решения'];
    $serviceStmt = $pdo->prepare("INSERT INTO services (name) VALUES (?)");
    foreach ($services as $service) {
        $serviceStmt->execute([$service]);
    }
    
    
    $paymentTypes = ['Инсталляции', 'Сервисная', 'Оборудование', 'Разовые', 'Интеграционные проекты'];
    $paymentStmt = $pdo->prepare("INSERT INTO payment_types (name) VALUES (?)");
    foreach ($paymentTypes as $type) {
        $paymentStmt->execute([$type]);
    }
    
    
    $stages = [
        ['Лид', 0.1],
        ['Проработка лида', 0.1],
        ['КП', 0.3],
        ['Пилот', 0.4],
        ['Выделение финансирования', 0.4],
        ['Закупка/торги', 0.5],
        ['Заключение ДД', 0.7],
        ['Заключение РД', 0.8],
        ['Реализация', 0.9],
        ['Успех', 1.0]
    ];
    
    $stageStmt = $pdo->prepare("INSERT INTO project_stages (name, probability) VALUES (?, ?)");
    foreach ($stages as $stage) {
        $stageStmt->execute($stage);
    }
    
    
    $segments = ['Крупный сегмент', 'Госсектор', 'Средний сегмент', 'Малые предприятия'];
    $segmentStmt = $pdo->prepare("INSERT INTO segments (name) VALUES (?)");
    foreach ($segments as $segment) {
        $segmentStmt->execute([$segment]);
    }
    
    
    $projects = [
        ['Альфа-Рост', '123456789012', 'Волна Коммуникаций', 2, 2, 2, 0.2, 2, 3, 2025, 0, 1, 0, 0, 'ДАШ_ПКМ', NULL, NULL, 'Текущий статус по проекту', 'Что сделано за период', 'Планы на следующий период', 2],
        ['Бета-Сила', '234567890123', 'Цифровой Мост', 4, 4, 10, 1.0, 3, 2, 2025, 0, 0, 0, 0, 'ОТТОК', NULL, NULL, 'Текущий статус по проекту', 'Что сделано за период', 'Планы на следующий период', 2],
        ['Гамма-Свет', '345678901234', 'Сеть Будущего', 5, 1, 3, 0.3, 4, 2, 2025, 0, 0, 0, 0, 'Delete', NULL, NULL, 'Текущий статус по проекту', 'Что сделано за период', 'Планы на следующий период', 2],
        ['Дельта-Труд', '456789012345', 'Эхо Соединения', 3, 2, 3, 0.3, 5, 3, 2025, 0, 0, 0, 0, 'ПКМ', NULL, NULL, 'Текущий статус по проекту', 'Что сделано за период', 'Планы на следующий период', 2],
        ['Эпсилон-Мир', '567890123456', 'Голос Онлайн', 4, 2, 3, 0.3, 2, 1, 2026, 1, 1, 0, 0, 'ОЦЕНКА', 'Иванов', '61/123/2023', 'Текущий статус по проекту', 'Что сделано за период', 'Планы на следующий период', 2]
    ];
    
    $projectStmt = $pdo->prepare("
        INSERT INTO projects (client_name, client_inn, project_name, service_id, payment_type_id, project_stage_id, 
        probability, manager_id, segment_id, implementation_year, is_industry_solution, is_forecast_accepted, 
        is_dzo_implementation, requires_management_control, evaluation_status, industry_manager, project_number, 
        current_status, completed_in_period, plans_for_next_period, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($projects as $project) {
        $projectStmt->execute($project);
    }
    
    
    $revenueData = [
        [1, 2025, 1, 4841000, 1],
        [1, 2025, 2, 2068000, 1],
        [1, 2025, 3, 982000, 1],
        [2, 2025, 2, 2477000, 1],
        [2, 2025, 3, 1256000, 1],
        [3, 2025, 9, 42000, 2],
        [4, 2025, 2, 1517000, 2],
        [4, 2025, 4, 4231000, 2],
        [4, 2025, 6, 3502000, 2],
        [4, 2025, 8, 3235000, 2],
        [4, 2025, 10, 1805000, 2],
        [4, 2025, 11, 1359000, 2],
        [5, 2025, 8, 482000, 2]
    ];
    
    $revenueStmt = $pdo->prepare("
        INSERT INTO project_revenue (project_id, year, month, amount, revenue_status_id) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($revenueData as $revenue) {
        $revenueStmt->execute($revenue);
    }
    
    
    $reports = [
        ['Финансовый отчет за 2025 год', 'Общий финансовый отчет по всем проектам за 2025 год', 'financial', '{"date_from": "2025-01-01", "date_to": "2025-12-31"}', 1, 1],
        ['Активные проекты по инфобезопасности', 'Отчет по текущим проектам в области информационной безопасности', 'project_status', '{"service": 3, "stage": [1,2,3,4,5,6,7,8]}', 2, 1],
        ['Анализ выручки по менеджерам', 'Сравнительный анализ эффективности менеджеров по выручке', 'analytical', '{}', 3, 0],
        ['Публичный отчет по облачным сервисам', 'Обзор проектов в области облачных сервисов', 'project_status', '{"service": 5}', 1, 1]
    ];
    
    $reportStmt = $pdo->prepare("
        INSERT INTO reports (title, description, report_type, filters, created_by, is_public) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($reports as $report) {
        $reportStmt->execute($report);
    }
    
    echo "База данных успешно заполнена тестовыми данными!<br>";
    echo "<a href='login.php'>Перейти к авторизации</a>";
    
} catch(PDOException $e) {
    die("Ошибка при заполнении базы данных: " . $e->getMessage());
}
?>