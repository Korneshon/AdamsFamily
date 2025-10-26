<?php

require_once 'config.php';


if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);


$report_type = $_GET['report_type'] ?? '';
$author_filter = $_GET['author'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search_query = $_GET['search'] ?? '';


try {
    $sql = "
        SELECT r.*, u.username, u.full_name as author_name 
        FROM reports r 
        JOIN users u ON r.created_by = u.id 
        WHERE (r.created_by = ? OR r.is_public = 1)
    ";
    
    $params = [$_SESSION['user_id']];
    
   
    if (!empty($report_type)) {
        $sql .= " AND r.type = ?";
        $params[] = $report_type;
    }
    
    if (!empty($author_filter)) {
        $sql .= " AND (u.username LIKE ? OR u.full_name LIKE ?)";
        $params[] = "%$author_filter%";
        $params[] = "%$author_filter%";
    }
    
    if (!empty($date_from)) {
        $sql .= " AND DATE(r.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $sql .= " AND DATE(r.created_at) <= ?";
        $params[] = $date_to;
    }
    
    if (!empty($search_query)) {
        $sql .= " AND (r.title LIKE ? OR r.description LIKE ?)";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    }
    
    $sql .= " ORDER BY r.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching reports: " . $e->getMessage());
    $error = 'Ошибка при загрузке отчетов';
    $reports = [];
}


try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_public = 1 THEN 1 ELSE 0 END) as public_count,
            SUM(CASE WHEN created_by = ? THEN 1 ELSE 0 END) as my_count
        FROM reports 
        WHERE created_by = ? OR is_public = 1
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error getting reports stats: " . $e->getMessage());
    $stats = ['total' => 0, 'public_count' => 0, 'my_count' => 0];
}


if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $report_id = $_GET['delete'];
    $report = getReportById($pdo, $report_id, $_SESSION['user_id']);
    
    if ($report && ($report['created_by'] == $_SESSION['user_id'] || $_SESSION['user_role'] == 'admin')) {
        if (deleteReport($pdo, $report_id, $_SESSION['user_id'])) {
            $_SESSION['success'] = 'Отчет успешно удален';
            header('Location: reports.php');
            exit;
        } else {
            $error = 'Ошибка при удалении отчета';
        }
    } else {
        $error = 'Отчет не найден или у вас нет прав для его удаления';
    }
}


$report_types = [
    'financial' => 'Финансовый',
    'operational' => 'Операционный',
    'analytical' => 'Аналитический'
];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Просмотр отчетов - Ростелеком</title>
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

   
    .nav-bar {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        padding: 30px 40px;
        border-radius: 20px;
        margin-bottom: 20px;
        box-shadow: 0 20px 40px rgba(120, 0, 255, 0.15);
        display: flex;
        justify-content: space-between;
        align-items: center;
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
        border-radius: 15px;
        margin-bottom: 25px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
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

    
    .error-message, .success-message {
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 25px;
        font-weight: 500;
        border-left: 5px solid;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .error-message {
        background: linear-gradient(135deg, #fef2f2, #fecaca);
        color: var(--danger);
        border-left-color: var(--danger);
    }

    .success-message {
        background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        color: var(--success);
        border-left-color: var(--success);
    }

    
    .stats-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .stat-card {
        background: var(--bg-white);
        padding: 30px;
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
        transform: translateY(-10px) scale(1.02);
        box-shadow: 0 20px 40px rgba(120, 0, 255, 0.15);
    }

    .stat-number {
        font-size: 3rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 10px;
        line-height: 1;
    }

    .stat-label {
        color: var(--text-light);
        font-size: 1.1rem;
        font-weight: 500;
    }

    
    .filters-section, .reports-list {
        background: var(--bg-white);
        padding: 30px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        margin-bottom: 25px;
        border: 1px solid var(--border-light);
        transition: transform 0.3s ease;
    }

    .filters-section:hover, .reports-list:hover {
        transform: translateY(-5px);
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 2px solid var(--border-light);
    }

    .section-header h2 {
        color: var(--primary);
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
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

    .form-control {
        width: 100%;
        padding: 15px 20px;
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

    .filter-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    
    .report-card {
        background: var(--bg-white);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 25px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        border-left: 5px solid var(--primary);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        overflow: hidden;
    }

    .report-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(120, 0, 255, 0.05), transparent);
        transition: left 0.6s ease;
    }

    .report-card:hover::before {
        left: 100%;
    }

    .report-card:hover {
        transform: translateY(-8px) scale(1.01);
        box-shadow: 0 20px 40px rgba(120, 0, 255, 0.15);
    }

    .report-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
    }

    .report-title {
        font-size: 1.5rem;
        color: var(--primary);
        margin-bottom: 12px;
        font-weight: 700;
    }

    .report-meta {
        color: var(--text-light);
        font-size: 0.95rem;
        margin-bottom: 15px;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }

    .report-description {
        margin-bottom: 25px;
        line-height: 1.6;
        color: var(--text-dark);
    }

    
    .status-badge {
        padding: 8px 16px;
        border-radius: 15px;
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .type-financial { 
        background: linear-gradient(135deg, #dbeafe, #3b82f6); 
        color: #1e40af; 
    }
    .type-operational { 
        background: linear-gradient(135deg, #d1fae5, #10b981); 
        color: #065f46; 
    }
    .type-analytical { 
        background: linear-gradient(135deg, #fef3c7, #f59e0b); 
        color: #92400e; 
    }

    .visibility-public { 
        background: linear-gradient(135deg, #d1fae5, #10b981); 
        color: #065f46; 
    }
    .visibility-private { 
        background: linear-gradient(135deg, #fef3c7, #f59e0b); 
        color: #92400e; 
    }

    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 0.95rem;
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
        background: linear-gradient(135deg, var#fe4e12 0%, #fe4e12 100%);
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    }

    .btn-success:hover {
        background: linear-gradient(135deg, #ff784bff 0%, #fe4e12 100%);
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

    .btn-warning {
        background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
    }

    .btn-warning:hover {
        background: linear-gradient(135deg, #fbbf24 0%, var(--warning) 100%);
        box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
    }

    
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

    
    .criteria-preview {
        background: var(--bg-gray);
        padding: 20px;
        border-radius: 15px;
        margin: 20px 0;
        border-left: 4px solid var(--primary);
    }

    .report-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 2px solid var(--border-light);
    }

    .report-actions {
        display: flex;
        gap: 12px;
    }

    .actions-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }

    .quick-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 20px;
        margin-top: 25px;
    }

    .quick-stat {
        text-align: center;
        padding: 20px;
        background: var(--bg-gray);
        border-radius: 15px;
        transition: all 0.3s ease;
    }

    .quick-stat:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .quick-stat-number {
        font-size: 2rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 8px;
    }

    .quick-stat-label {
        font-size: 0.9rem;
        color: var(--text-light);
        font-weight: 500;
    }

    
    .pagination {
        display: flex;
        justify-content: center;
        margin-top: 40px;
        gap: 12px;
    }

    .page-link {
        padding: 12px 20px;
        border: 2px solid var(--border-light);
        background: var(--bg-white);
        color: var(--text-dark);
        text-decoration: none;
        border-radius: 12px;
        transition: all 0.3s ease;
        font-weight: 600;
    }

    .page-link:hover, .page-link.active {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(120, 0, 255, 0.3);
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

    .stat-card, .filters-section, .reports-list, .report-card {
        animation: fadeInUp 0.6s ease-out;
    }

    .stat-card:nth-child(2) { animation-delay: 0.1s; }
    .stat-card:nth-child(3) { animation-delay: 0.2s; }
    .stat-card:nth-child(4) { animation-delay: 0.3s; }

    
    @media (max-width: 768px) {
        body {
            padding: 15px;
        }
        
        .nav-bar {
            padding: 25px;
            flex-direction: column;
            text-align: center;
            gap: 20px;
        }
        
        .nav-bar h1 {
            font-size: 1.8rem;
        }
        
        .filter-row {
            grid-template-columns: 1fr;
        }
        
        .report-header {
            flex-direction: column;
            gap: 15px;
        }
        
        .report-footer {
            flex-direction: column;
            gap: 20px;
            align-items: flex-start;
        }
        
        .report-actions {
            flex-wrap: wrap;
        }
        
        .actions-header {
            flex-direction: column;
            gap: 20px;
            align-items: flex-start;
        }
        
        .stats-cards {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .section-header {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }
    }
</style>
</head>
<body>
    <div class="container">
        
        <div class="nav-bar">
            <h1><i class="fas fa-chart-bar"></i> Ростелеком - Просмотр отчетов</h1>
        </div>

        
        <div class="breadcrumb">
            <a href="index.php"><i class="fas fa-home"></i> Дашборд</a> &raquo; 
            <span><i class="fas fa-file-alt"></i> Просмотр отчетов</span>
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

        
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-label">Всего отчетов</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['my_count'] ?? 0; ?></div>
                <div class="stat-label">Мои отчеты</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['public_count'] ?? 0; ?></div>
                <div class="stat-label">Публичные отчеты</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($reports); ?></div>
                <div class="stat-label">Показано отчетов</div>
            </div>
        </div>

        
        <div class="filters-section">
            <div class="actions-header">
                <h2>Фильтры отчетов</h2>
                <a href="add_report.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Создать отчет
                </a>
            </div>

            <form method="GET" action="">
                <div class="filter-row">
                    <div class="form-group">
                        <label>Тип отчета:</label>
                        <select name="report_type" class="form-control">
                            <option value="">Все типы</option>
                            <?php foreach ($report_types as $value => $name): ?>
                                <option value="<?php echo $value; ?>" <?php echo $report_type == $value ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Автор:</label>
                        <input type="text" name="author" class="form-control" 
                               value="<?php echo htmlspecialchars($author_filter); ?>" 
                               placeholder="Имя пользователя или ФИО">
                    </div>
                    
                    <div class="form-group">
                        <label>Дата с:</label>
                        <input type="date" name="date_from" class="form-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Дата по:</label>
                        <input type="date" name="date_to" class="form-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Поиск по отчетам:</label>
                    <input type="text" name="search" class="form-control" 
                           value="<?php echo htmlspecialchars($search_query); ?>" 
                           placeholder="Поиск по названию или описанию...">
                </div>
                
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="submit" class="btn">
                        <i class="fas fa-filter"></i> Применить фильтры
                    </button>
                    <a href="reports.php" class="btn">
                        <i class="fas fa-times"></i> Сбросить
                    </a>
                    <?php if (!empty($report_type) || !empty($author_filter) || !empty($date_from) || !empty($date_to) || !empty($search_query)): ?>
                        <span style="display: flex; align-items: center; color: var(--rtk-dark-gray); font-size: 0.9rem;">
                            Активные фильтры: 
                            <?php 
                                $active_filters = [];
                                if (!empty($report_type)) $active_filters[] = "Тип: " . ($report_types[$report_type] ?? $report_type);
                                if (!empty($author_filter)) $active_filters[] = "Автор: " . $author_filter;
                                if (!empty($date_from)) $active_filters[] = "С: " . $date_from;
                                if (!empty($date_to)) $active_filters[] = "По: " . $date_to;
                                if (!empty($search_query)) $active_filters[] = "Поиск: " . $search_query;
                                echo implode(', ', $active_filters);
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        
        <div class="reports-list">
            <div class="actions-header">
                <h2>Список отчетов (<?php echo count($reports); ?>)</h2>
                
               
                <?php if (!empty($reports)): ?>
                    <div class="quick-stats">
                        <?php
                        $type_counts = [];
                        foreach ($reports as $report) {
                            $type = $report['type'];
                            $type_counts[$type] = ($type_counts[$type] ?? 0) + 1;
                        }
                        ?>
                        <?php foreach ($report_types as $type => $name): ?>
                            <?php if (isset($type_counts[$type])): ?>
                                <div class="quick-stat">
                                    <div class="quick-stat-number"><?php echo $type_counts[$type]; ?></div>
                                    <div class="quick-stat-label"><?php echo $name; ?></div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($reports)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h3>Отчеты не найдены</h3>
                    <p><?php echo (!empty($report_type) || !empty($author_filter) || !empty($date_from) || !empty($date_to) || !empty($search_query)) ? 'Попробуйте изменить параметры фильтрации' : 'Создайте первый отчет для начала работы'; ?></p>
                    <div style="margin-top: 20px;">
                        <a href="add_report.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> Создать отчет
                        </a>
                        <?php if (!empty($report_type) || !empty($author_filter) || !empty($date_from) || !empty($date_to) || !empty($search_query)): ?>
                            <a href="reports.php" class="btn" style="margin-left: 10px;">
                                <i class="fas fa-list"></i> Показать все отчеты
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="reports-grid">
                    <?php foreach ($reports as $report): ?>
                        <?php 
                            
                            $report_id = $report['id'] ?? '';
                            $report_title = $report['title'] ?? 'Без названия';
                            $report_description = $report['description'] ?? '';
                            $report_type_name = $report['type'] ?? '';
                            $author_name = $report['author_name'] ?? 'Неизвестный автор';
                            $created_at = $report['created_at'] ?? '';
                            $is_public = $report['is_public'] ?? 0;
                            $is_owner = $report['created_by'] == $_SESSION['user_id'];
                            
                           
                            $formatted_date = '';
                            if (!empty($created_at)) {
                                try {
                                    $date = new DateTime($created_at);
                                    $formatted_date = $date->format('d.m.Y H:i');
                                } catch (Exception $e) {
                                    $formatted_date = 'Некорректная дата';
                                }
                            }
                            
                           
                            $criteria_preview = '';
                            if (!empty($report['criteria'])) {
                                try {
                                    $criteria = json_decode($report['criteria'], true);
                                    if (is_array($criteria)) {
                                        $criteria_items = [];
                                        foreach ($criteria as $key => $value) {
                                            if (!empty($value) && $value !== '') {
                                                if (is_array($value)) {
                                                    $value = implode(', ', $value);
                                                }
                                                $criteria_items[] = $key . ': ' . $value;
                                            }
                                        }
                                        if (!empty($criteria_items)) {
                                            $criteria_preview = implode(' • ', array_slice($criteria_items, 0, 3));
                                            if (count($criteria_items) > 3) {
                                                $criteria_preview .= ' • ...';
                                            }
                                        }
                                    }
                                } catch (Exception $e) {
                                   
                                }
                            }
                        ?>
                        
                        <div class="report-card">
                            <div class="report-header">
                                <div style="flex: 1;">
                                    <div class="report-title"><?php echo htmlspecialchars($report_title); ?></div>
                                    <div class="report-meta">
                                        <span><strong>Автор:</strong> <?php echo htmlspecialchars($author_name); ?></span>
                                        <span><strong>Создан:</strong> <?php echo $formatted_date; ?></span>
                                        <span class="status-badge type-<?php echo $report_type_name; ?>">
                                            <?php echo $report_types[$report_type_name] ?? $report_type_name; ?>
                                        </span>
                                        <span class="status-badge visibility-<?php echo $is_public ? 'public' : 'private'; ?>">
                                            <?php echo $is_public ? 'Публичный' : 'Приватный'; ?>
                                            <?php if ($is_owner): ?>
                                                <i class="fas fa-user" style="margin-left: 5px;"></i>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($report_description)): ?>
                                <div class="report-description">
                                    <?php echo htmlspecialchars($report_description); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($criteria_preview)): ?>
                                <div class="criteria-preview">
                                    <strong>Критерии:</strong> <?php echo htmlspecialchars($criteria_preview); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="report-footer">
                                <div class="report-info">
                                    <small style="color: var(--rtk-dark-gray);">
                                        <?php if ($is_owner): ?>
                                            <i class="fas fa-crown" style="color: #ffc107;"></i> Ваш отчет
                                        <?php else: ?>
                                            <i class="fas fa-eye"></i> Доступ предоставлен автором
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="report-actions">
                                    <a href="view_report.php?id=<?php echo $report_id; ?>" class="btn">
                                        <i class="fas fa-eye"></i> Просмотреть
                                    </a>
                                    
                                    <?php if ($is_owner || $_SESSION['user_role'] == 'admin'): ?>
                                        <a href="edit_reports.php?edit=<?php echo $report_id; ?>" class="btn btn-success">
                                            <i class="fas fa-edit"></i> Редактировать
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($is_owner || $_SESSION['user_role'] == 'admin'): ?>
                                        <a href="reports.php?delete=<?php echo $report_id; ?>" 
                                           class="btn btn-danger" 
                                           onclick="return confirm('Вы уверены, что хотите удалить отчет \"<?php echo htmlspecialchars($report_title); ?>\"?')">
                                            <i class="fas fa-trash"></i> Удалить
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($_SESSION['user_role'] == 'admin' && !$is_owner): ?>
                                        <span class="btn btn-warning" title="Администратор может просматривать и редактировать все отчеты">
                                            <i class="fas fa-shield-alt"></i> Админ
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
               
                <div class="pagination">
                    <a href="#" class="page-link active">1</a>
                    
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
       
        setTimeout(function() {
            const errorMsg = document.querySelector('.error-message');
            const successMsg = document.querySelector('.success-message');
            
            if (errorMsg) errorMsg.style.display = 'none';
            if (successMsg) successMsg.style.display = 'none';
        }, 5000);
        
        
        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-danger')) {
                const reportCard = e.target.closest('.report-card');
                if (reportCard) {
                    const reportTitle = reportCard.querySelector('.report-title').textContent;
                    return confirm('Внимание! Вы собираетесь удалить отчет \"' + reportTitle + '\". Это действие нельзя отменить. Продолжить?');
                }
            }
        });
        
        
        const filterForm = document.querySelector('form');
        filterForm.addEventListener('submit', function() {
          
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Применение фильтров...';
            submitBtn.disabled = true;
        });
        
        
        const quickFilters = document.querySelectorAll('select[name="report_type"], input[name="author"]');
        quickFilters.forEach(filter => {
            filter.addEventListener('change', function() {
                
                
            });
        });
    </script>
</body>
</html>