<?php
session_start();

require_once 'config.php';


if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    $total_projects = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
    
    $active_projects = $pdo->query("
        SELECT COUNT(*) 
        FROM projects p 
        JOIN project_stages ps ON p.project_stage_id = ps.id 
        WHERE ps.probability BETWEEN 10 AND 90
    ")->fetchColumn();
    
    $completed_projects = $pdo->query("
        SELECT COUNT(*) 
        FROM projects p 
        JOIN project_stages ps ON p.project_stage_id = ps.id 
        WHERE ps.probability = 100
    ")->fetchColumn();
    
    $planning_projects = $pdo->query("
        SELECT COUNT(*) 
        FROM projects p 
        JOIN project_stages ps ON p.project_stage_id = ps.id 
        WHERE ps.probability < 10
    ")->fetchColumn();
    
    $avg_probability = $pdo->query("SELECT AVG(probability) FROM projects")->fetchColumn();
    $avg_probability = round($avg_probability, 1);
    
} catch (PDOException $e) {
    error_log("Error getting stats: " . $e->getMessage());
    $total_projects = $active_projects = $completed_projects = $planning_projects = $avg_probability = 0;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            s.name as service_name,
            pt.name as payment_type,
            ps.name as project_stage,
            ps.probability as stage_probability,
            u.full_name as manager_name,
            seg.name as segment_name
        FROM projects p
        LEFT JOIN services s ON p.service_id = s.id
        LEFT JOIN payment_types pt ON p.payment_type_id = pt.id
        LEFT JOIN project_stages ps ON p.project_stage_id = ps.id
        LEFT JOIN users u ON p.manager_id = u.id
        LEFT JOIN segments seg ON p.segment_id = seg.id
        ORDER BY p.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_projects = [];
    error_log("Error getting recent projects: " . $e->getMessage());
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            s.name as service_name,
            pt.name as payment_type,
            ps.name as project_stage,
            ps.probability as stage_probability,
            u.full_name as manager_name,
            seg.name as segment_name
        FROM projects p
        LEFT JOIN services s ON p.service_id = s.id
        LEFT JOIN payment_types pt ON p.payment_type_id = pt.id
        LEFT JOIN project_stages ps ON p.project_stage_id = ps.id
        LEFT JOIN users u ON p.manager_id = u.id
        LEFT JOIN segments seg ON p.segment_id = seg.id
        WHERE ps.probability BETWEEN 50 AND 90
        ORDER BY ps.probability DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $active_projects_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $active_projects_list = [];
    error_log("Error getting active projects: " . $e->getMessage());
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            s.name as service_name,
            pt.name as payment_type,
            ps.name as project_stage,
            ps.probability as stage_probability,
            u.full_name as manager_name,
            seg.name as segment_name
        FROM projects p
        LEFT JOIN services s ON p.service_id = s.id
        LEFT JOIN payment_types pt ON p.payment_type_id = pt.id
        LEFT JOIN project_stages ps ON p.project_stage_id = ps.id
        LEFT JOIN users u ON p.manager_id = u.id
        LEFT JOIN segments seg ON p.segment_id = seg.id
        WHERE p.requires_management_control = 1
        ORDER BY p.created_at DESC 
        LIMIT 3
    ");
    $stmt->execute();
    $management_control_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $management_control_projects = [];
    error_log("Error getting management control projects: " . $e->getMessage());
}


try {
    $stmt = $pdo->query("
        SELECT 
            ps.name as stage_name,
            COUNT(p.id) as project_count
        FROM project_stages ps
        LEFT JOIN projects p ON ps.id = p.project_stage_id
        GROUP BY ps.id, ps.name
        ORDER BY ps.probability
    ");
    $stage_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stage_stats = [];
    error_log("Error getting stage stats: " . $e->getMessage());
}


try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_reports FROM reports WHERE created_by = ? OR is_public = 1");
    $stmt->execute([$_SESSION['user_id']]);
    $total_reports = $stmt->fetchColumn();
} catch (PDOException $e) {
    $total_reports = 0;
    error_log("Error getting reports count: " . $e->getMessage());
}


try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.username, u.full_name 
        FROM reports r 
        JOIN users u ON r.created_by = u.id 
        WHERE r.created_by = ? OR r.is_public = 1 
        ORDER BY r.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_reports = [];
    error_log("Error getting recent reports: " . $e->getMessage());
}


try {
    $stmt = $pdo->prepare("SELECT username, full_name, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_info) {
        $_SESSION['username'] = $user_info['username'];
        $_SESSION['full_name'] = $user_info['full_name'];
        $_SESSION['user_role'] = $user_info['role'];
    }
} catch (PDOException $e) {
    error_log("Error getting user info: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Дашборд - Ростелеком</title>
    <link rel="stylesheet" href="all.min.css">
    <script src="chart.js"></script>
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
    .dashboard-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        padding: 40px;
        border-radius: 20px;
        margin-bottom: 30px;
        box-shadow: 0 20px 40px rgba(120, 0, 255, 0.15);
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
        overflow: hidden;
    }

    .dashboard-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 200%;
        background: radial-gradient(circle, var(--accent-light) 0%, transparent 70%);
        opacity: 0.1;
    }

    .welcome-message h1 {
        color: white;
        font-size: 2.5rem;
        margin-bottom: 10px;
        font-weight: 700;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }

    .welcome-message p {
        color: rgba(255, 255, 255, 0.9);
        font-size: 1.2rem;
        margin-bottom: 8px;
    }

    .welcome-message a {
        color: var(--accent-light);
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .welcome-message a:hover {
        color: white;
        text-shadow: 0 0 10px var(--accent-light);
    }

    .user-info {
        text-align: right;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        padding: 15px 25px;
        border-radius: 15px;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .user-info div:first-child {
        color: white;
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .user-role {
        background: var(--secondary);
        color: white;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 15px rgba(254, 78, 18, 0.3);
    }

    /* Анимированные карточки статистики */
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

    /* Основная сетка */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 30px;
    }

    @media (max-width: 1200px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Секции */
    .dashboard-section {
        background: var(--bg-white);
        padding: 30px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        margin-bottom: 25px;
        border: 1px solid var(--border-light);
        transition: transform 0.3s ease;
    }

    .dashboard-section:hover {
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

    /* Карточки проектов и отчетов */
    .project-mini-card, .report-mini-card {
        background: var(--bg-gray);
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 20px;
        border-left: 5px solid var(--primary);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .project-mini-card::before, .report-mini-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(120, 0, 255, 0.05), transparent);
        transition: left 0.6s ease;
    }

    .project-mini-card:hover::before, .report-mini-card:hover::before {
        left: 100%;
    }

    .project-mini-card:hover, .report-mini-card:hover {
        transform: translateX(8px);
        box-shadow: 0 8px 20px rgba(120, 0, 255, 0.1);
    }

    .project-mini-title, .report-mini-title {
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 8px;
        font-size: 1.1rem;
    }

    .project-mini-meta, .report-mini-meta {
        display: flex;
        justify-content: space-between;
        font-size: 0.9rem;
        color: var(--text-light);
        flex-wrap: wrap;
        gap: 10px;
    }

    /* Статус-бейджи */
    .status-badge {
        padding: 6px 12px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-low { 
        background: linear-gradient(135deg, #fef3c7, #f59e0b); 
        color: #92400e; 
    }
    .status-medium { 
        background: linear-gradient(135deg, #dbeafe, #3b82f6); 
        color: #1e40af; 
    }
    .status-high { 
        background: linear-gradient(135deg, #d1fae5, #10b981); 
        color: #065f46; 
    }
    .status-completed { 
        background: linear-gradient(135deg, #e5e7eb, #6b7280); 
        color: #374151; 
    }

    .priority-high { color: var(--danger); font-weight: 700; }
    .priority-medium { color: var(--warning); font-weight: 700; }
    .priority-low { color: var(--success); font-weight: 700; }

    /* Прогресс-бар */
    .progress-bar {
        width: 100%;
        height: 10px;
        background-color: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        margin: 12px 0;
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

    /* Кнопки */
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
        background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    }

    .btn-success:hover {
        background: linear-gradient(135deg, #34d399 0%, var(--success) 100%);
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
    }

    /* Состояние пустоты */
    .empty-state {
        text-align: center;
        padding: 50px 20px;
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
        margin-bottom: 10px;
        font-size: 1.3rem;
    }

    /* Быстрые действия */
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 20px;
        margin-top: 25px;
    }

    .quick-action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 25px 15px;
        background: var(--bg-white);
        border: 2px solid var(--border-light);
        border-radius: 15px;
        text-decoration: none;
        color: var(--text-dark);
        transition: all 0.3s ease;
        text-align: center;
    }

    .quick-action-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-5px) scale(1.05);
        box-shadow: 0 15px 30px rgba(120, 0, 255, 0.15);
    }

    .quick-action-btn i {
        font-size: 2.5rem;
        margin-bottom: 15px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* Уведомления для руководства */
    .management-alert {
        background: linear-gradient(135deg, #fef2f2, #fecaca);
        border-left: 5px solid var(--danger);
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 20px;
        position: relative;
        overflow: hidden;
    }

    .management-alert::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 8px;
        height: 100%;
        background: var(--danger);
    }

    .alert-header {
        display: flex;
        align-items: center;
        margin-bottom: 12px;
    }

    .alert-header i {
        color: var(--danger);
        margin-right: 12px;
        font-size: 1.4rem;
    }


    .notification-badge {
        background: linear-gradient(135deg, var(--secondary) 0%, var(--secondary-dark) 100%);
        color: white;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: 700;
        margin-left: 8px;
        box-shadow: 0 4px 10px rgba(254, 78, 18, 0.3);
    }

    /* Детали проектов */
    .project-details {
        font-size: 0.85rem;
        color: var(--text-light);
        margin-top: 8px;
        line-height: 1.4;
    }

    .project-client {
        font-weight: 600;
        color: var(--primary);
    }

    a.user-role {
        text-decoration: none;
    }

    /* График */
    .chart-container {
        height: 320px;
        margin-top: 25px;
        position: relative;
    }

    /* Адаптивность для мобильных */
    @media (max-width: 768px) {
        body {
            padding: 15px;
        }
        
        .dashboard-header {
            padding: 25px;
            flex-direction: column;
            text-align: center;
            gap: 20px;
        }
        
        .welcome-message h1 {
            font-size: 2rem;
        }
        
        .user-info {
            text-align: center;
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
        
        .project-mini-meta, .report-mini-meta {
            flex-direction: column;
            gap: 8px;
        }
        
        .quick-actions {
            grid-template-columns: repeat(2, 1fr);
        }
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

    .stat-card, .dashboard-section {
        animation: fadeInUp 0.6s ease-out;
    }

    .stat-card:nth-child(2) { animation-delay: 0.1s; }
    .stat-card:nth-child(3) { animation-delay: 0.2s; }
    .stat-card:nth-child(4) { animation-delay: 0.3s; }
    @media (max-width: 360px) {
        .stats-cards {
            grid-template-columns: 1fr;
        }
        
        .quick-actions {
            grid-template-columns: 1fr;
        }
        
        .mini-stats {
            grid-template-columns: 1fr;
        }
    }
    
    /* Портретная ориентация */
    @media (max-width: 768px) and (orientation: portrait) {
        .dashboard-section {
            margin-bottom: 12px;
        }
    }
    
    /* Ландшафтная ориентация на мобильных */
    @media (max-width: 768px) and (orientation: landscape) {
        .dashboard-header {
            flex-direction: row;
            text-align: left;
        }
        
        .user-info {
            text-align: right;
        }
        
        .stats-cards {
            grid-template-columns: repeat(4, 1fr);
        }
    }
}
/* Мобильная версия - основные стили */
@media (max-width: 768px) {
    body {
        padding: 10px;
        font-size: 14px;
        line-height: 1.4;
        -webkit-text-size-adjust: 100%;
    }
    
    .container {
        max-width: 100%;
        padding: 0 5px;
    }
    
    /* Заголовок дашборда */
    .dashboard-header {
        padding: 20px 15px;
        border-radius: 12px;
        margin-bottom: 15px;
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
    
    .welcome-message h1 {
        font-size: 1.5rem;
        margin-bottom: 5px;
    }
    
    .welcome-message p {
        font-size: 0.9rem;
        margin-bottom: 5px;
    }
    
    .user-info {
        text-align: center;
        padding: 10px 15px;
    }
    
    .user-info div:first-child {
        font-size: 0.95rem;
    }
    
    .user-role {
        padding: 5px 10px;
        font-size: 0.75rem;
    }
    
    /* Карточки статистики */
    .stats-cards {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        padding: 15px 10px;
        border-radius: 10px;
        margin-bottom: 0;
    }
    
    .stat-number {
        font-size: 1.8rem;
        margin-bottom: 5px;
    }
    
    .stat-label {
        font-size: 0.8rem;
    }
    
    /* Основная сетка */
    .dashboard-grid {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    /* Секции */
    .dashboard-section {
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 15px;
    }
    
    .section-header {
        flex-direction: column;
        gap: 10px;
        margin-bottom: 15px;
        padding-bottom: 12px;
    }
    
    .section-header h2 {
        font-size: 1.2rem;
        text-align: center;
    }
    
    /* Карточки проектов и отчетов */
    .project-mini-card, 
    .report-mini-card {
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 12px;
        border-left-width: 4px;
    }
    
    .project-mini-title, 
    .report-mini-title {
        font-size: 0.95rem;
        margin-bottom: 5px;
    }
    
    .project-mini-meta, 
    .report-mini-meta {
        flex-direction: column;
        gap: 5px;
        font-size: 0.8rem;
    }
    
    /* Статус-бейджи */
    .status-badge {
        padding: 4px 8px;
        border-radius: 8px;
        font-size: 0.7rem;
        align-self: flex-start;
    }
    
    /* Прогресс-бар */
    .progress-bar {
        height: 6px;
        border-radius: 6px;
        margin: 8px 0;
    }
    
    /* Кнопки */
    .btn {
        padding: 10px 15px;
        border-radius: 8px;
        font-size: 0.85rem;
        width: 100%;
        text-align: center;
        justify-content: center;
    }
    
    /* Состояние пустоты */
    .empty-state {
        padding: 25px 10px;
    }
    
    .empty-state i {
        font-size: 2.5rem;
        margin-bottom: 10px;
    }
    
    .empty-state h3 {
        font-size: 1.1rem;
        margin-bottom: 8px;
    }
    
    /* Быстрые действия */
    .quick-actions {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-top: 15px;
    }
    
    .quick-action-btn {
        padding: 15px 8px;
        border-radius: 8px;
        min-height: 80px;
    }
    
    .quick-action-btn i {
        font-size: 1.5rem;
        margin-bottom: 8px;
    }
    
    .quick-action-btn span {
        font-size: 0.8rem;
    }
    
    /* Уведомления для руководства */
    .management-alert {
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 12px;
    }
    
    .alert-header i {
        font-size: 1.1rem;
        margin-right: 8px;
    }
    
    .notification-badge {
        width: 18px;
        height: 18px;
        font-size: 0.65rem;
    }
    
    /* Детали проектов */
    .project-details {
        font-size: 0.75rem;
        margin-top: 5px;
    }
    
    /* График */
    .chart-container {
        height: 200px;
        margin-top: 15px;
    }
    
    /* Общая статистика */
    .mini-stats {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
        margin-top: 12px;
    }
    
    .mini-stat-item {
        padding: 10px 5px;
        border-radius: 8px;
    }
    
    .mini-stat-number {
        font-size: 1.1rem;
        margin-bottom: 2px;
    }
    
    .mini-stat-label {
        font-size: 0.7rem;
    }
    
    /* Отключение hover-эффектов на мобильных */
    .stat-card:hover,
    .dashboard-section:hover,
    .project-mini-card:hover,
    .report-mini-card:hover,
    .btn:hover,
    .quick-action-btn:hover {
        transform: none;
    }
    
    /* Улучшенная поддержка касаний */
    .btn, 
    .quick-action-btn, 
    .project-mini-card, 
    .report-mini-card {
        -webkit-tap-highlight-color: transparent;
        cursor: pointer;
    }
    
    .btn:active, 
    .quick-action-btn:active {
        transform: scale(0.98);
        opacity: 0.9;
    }
    
    /* Оптимизация для очень маленьких экранов */
    @media (max-width: 360px) {
        .stats-cards {
            grid-template-columns: 1fr;
        }
        
        .quick-actions {
            grid-template-columns: 1fr;
        }
        
        .mini-stats {
            grid-template-columns: 1fr;
        }
    }
    
    /* Портретная ориентация */
    @media (max-width: 768px) and (orientation: portrait) {
        .dashboard-section {
            margin-bottom: 12px;
        }
    }
    
    /* Ландшафтная ориентация на мобильных */
    @media (max-width: 768px) and (orientation: landscape) {
        .dashboard-header {
            flex-direction: row;
            text-align: left;
        }
        
        .user-info {
            text-align: right;
        }
        
        .stats-cards {
            grid-template-columns: repeat(4, 1fr);
        }
    }
}

/* Дополнительные улучшения для очень старых мобильных устройств */
@media (max-width: 320px) {
    body {
        padding: 5px;
    }
    
    .dashboard-header {
        padding: 15px 10px;
    }
    
    .welcome-message h1 {
        font-size: 1.3rem;
    }
    
    .stat-card {
        padding: 10px 5px;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
}
/* Дополнительные улучшения для очень старых мобильных устройств */
@media (max-width: 320px) {
    body {
        padding: 5px;
    }
    
    .dashboard-header {
        padding: 15px 10px;
    }
    
    .welcome-message h1 {
        font-size: 1.3rem;
    }
    
    .stat-card {
        padding: 10px 5px;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
}

</style>
</head>
<body>
    <div class="container">
  
        <div class="dashboard-header">
            <div class="welcome-message">
                <h1>Добро пожаловать, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
                <p>Главная страница управления проектами Ростелеком</p>
                <p>Также посетите <a href="profile.php">личный кабинет</a></p>
            </div>
            <div class="user-info">
                <div><strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></div>
                <a href="profile.php" class="user-role">
                    <?php 
                        $role_names = [
                            'admin' => 'Администратор',
                            'analyst' => 'Аналитик',
                            'user' => 'Пользователь'
                        ];
                        echo $role_names[$_SESSION['user_role']] ?? $_SESSION['user_role'];
                    ?>
                </a>
            </div>
        </div>


        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_projects; ?></div>
                <div class="stat-label">Всего проектов</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $active_projects; ?></div>
                <div class="stat-label">Активных проектов</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $completed_projects; ?></div>
                <div class="stat-label">Завершенных проектов</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $avg_probability; ?>%</div>
                <div class="stat-label">Средняя вероятность</div>
            </div>
        </div>


        <div class="dashboard-grid">
   
            <div>

                <?php if (!empty($management_control_projects)): ?>
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2><i class="fas fa-exclamation-triangle" style="color: var(--rtk-red);"></i> Требуют контроля руководства</h2>
                        <span class="notification-badge"><?php echo count($management_control_projects); ?></span>
                    </div>
                    
                    <?php foreach ($management_control_projects as $project): ?>
                        <div class="management-alert">
                            <div class="alert-header">
                                <i class="fas fa-exclamation-circle"></i>
                                <strong><?php echo htmlspecialchars($project['project_name']); ?></strong>
                            </div>
                            <div class="project-mini-meta">
                                <span>Клиент: <?php echo htmlspecialchars($project['client_name']); ?></span>
                                <span>Вероятность: <?php echo $project['probability']; ?>%</span>
                            </div>
                            <div class="project-details">
                                Этап: <?php echo htmlspecialchars($project['project_stage']); ?> | 
                                Менеджер: <?php echo htmlspecialchars($project['manager_name'] ?? 'Не назначен'); ?>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $project['probability']; ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>Последние проекты</h2>
                        <a href="projects.php" class="btn">Все проекты</a>
                    </div>
                    
                    <?php if (empty($recent_projects)): ?>
                        <div class="empty-state">
                            <i class="fas fa-project-diagram"></i>
                            <h3>Проекты не найдены</h3>
                            <p>Создайте первый проект для начала работы</p>
                            <a href="add_project.php" class="btn btn-success">Создать проект</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_projects as $project): ?>
                            <div class="project-mini-card">
                                <div class="project-mini-title"><?php echo htmlspecialchars($project['project_name']); ?></div>
                                <div class="project-client"><?php echo htmlspecialchars($project['client_name']); ?></div>
                                <div class="project-mini-meta">
                                    <span class="status-badge 
                                        <?php 
                                        if ($project['probability'] >= 80) echo 'status-high';
                                        elseif ($project['probability'] >= 50) echo 'status-medium';
                                        else echo 'status-low';
                                        ?>
                                    ">
                                        <?php echo htmlspecialchars($project['project_stage']); ?> (<?php echo $project['probability']; ?>%)
                                    </span>
                                    <span><?php echo date('d.m.Y', strtotime($project['created_at'])); ?></span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $project['probability']; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>Активные проекты</h2>
                        <a href="projects.php" class="btn">Все проекты</a>
                    </div>
                    
                    <?php if (empty($active_projects_list)): ?>
                        <div class="empty-state">
                            <i class="fas fa-running"></i>
                            <h3>Нет активных проектов</h3>
                            <p>Все проекты завершены или в планировании</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($active_projects_list as $project): ?>
                            <div class="project-mini-card">
                                <div class="project-mini-title"><?php echo htmlspecialchars($project['project_name']); ?></div>
                                <div class="project-client"><?php echo htmlspecialchars($project['client_name']); ?></div>
                                <div class="project-mini-meta">
                                    <span>Менеджер: <?php echo htmlspecialchars($project['manager_name'] ?? 'Не назначен'); ?></span>
                                    <span><?php echo $project['probability']; ?>%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $project['probability']; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <div class="dashboard-section">
                    <h2>Быстрые действия</h2>
                    <div class="quick-actions">
                        <a href="add_project.php" class="quick-action-btn">
                            <i class="fas fa-plus-circle"></i>
                            <span>Новый проект</span>
                        </a>
                        <a href="projects.php" class="quick-action-btn">
                            <i class="fas fa-project-diagram"></i>
                            <span>Все проекты</span>
                        </a>
                        <a href="reports.php" class="quick-action-btn">
                            <i class="fas fa-file-alt"></i>
                            <span>Все отчеты</span>
                        </a>
                        <?php if ($_SESSION['user_role'] == 'admin'): ?>
                        <a href="users.php" class="quick-action-btn">
                            <i class="fas fa-users"></i>
                            <span>Пользователи</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>Последние отчеты</h2>
                        <a href="reports.php" class="btn">Все отчеты</a>
                    </div>
                    
                    <?php if (empty($recent_reports)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <h3>Отчеты не найдены</h3>
                            <p>Создайте первый отчет</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_reports as $report): ?>
                            <div class="report-mini-card">
                                <div class="report-mini-title"><?php echo htmlspecialchars($report['title']); ?></div>
                                <div class="report-mini-meta">
                                    <span>
                                        <?php 
                                            $type_names = [
                                                'financial' => '📊 Финансовый',
                                                'operational' => '⚙️ Операционный',
                                                'analytical' => '📈 Аналитический'
                                            ];
                                            echo $type_names[$report['type']] ?? $report['type'];
                                        ?>
                                    </span>
                                    <span><?php echo date('d.m.Y', strtotime($report['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="dashboard-section">
                    <h2>Статистика проектов</h2>
                    <div class="chart-container">
                        <canvas id="projectsChart"></canvas>
                    </div>
                </div>

                <div class="dashboard-section">
                    <h2>Общая статистика</h2>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                        <div style="text-align: center;">
                            <div style="font-size: 1.5rem; font-weight: bold; color: var(--rtk-blue);">
                                <?php echo $total_reports; ?>
                            </div>
                            <div style="font-size: 0.8rem; color: var(--rtk-dark-gray);">Всего отчетов</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 1.5rem; font-weight: bold; color: var(--rtk-blue);">
                                <?php echo count($management_control_projects); ?>
                            </div>
                            <div style="font-size: 0.8rem; color: var(--rtk-dark-gray);">Требуют контроля</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 1.5rem; font-weight: bold; color: var(--rtk-blue);">
                                <?php echo $avg_probability; ?>%
                            </div>
                            <div style="font-size: 0.8rem; color: var(--rtk-dark-gray);">Средняя вероятность</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 1.5rem; font-weight: bold; color: var(--rtk-blue);">
                                <?php echo $planning_projects; ?>
                            </div>
                            <div style="font-size: 0.8rem; color: var(--rtk-dark-gray);">В планировании</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('projectsChart').getContext('2d');
        const projectsChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [<?php 
                    $labels = [];
                    foreach ($stage_stats as $stat) {
                        if ($stat['project_count'] > 0) {
                            $labels[] = "'" . addslashes($stat['stage_name']) . "'";
                        }
                    }
                    echo implode(', ', $labels);
                ?>],
                datasets: [{
                    data: [<?php 
                        $data = [];
                        foreach ($stage_stats as $stat) {
                            if ($stat['project_count'] > 0) {
                                $data[] = $stat['project_count'];
                            }
                        }
                        echo implode(', ', $data);
                    ?>],
                    backgroundColor: [
                        '#0033a0', '#00a0e3', '#4caf50', '#ff9800', '#e91e63',
                        '#9c27b0', '#673ab7', '#3f51b5', '#2196f3', '#03a9f4'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 11
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        setTimeout(function() {
            window.location.reload();
        }, 120000);
    </script>
</body>
</html>