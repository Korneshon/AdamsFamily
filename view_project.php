<?php

session_start();


$host = 'MySQL-8.4';
$dbname = 'project_management';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}


if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}


$project_id = $_GET['id'] ?? null;

if (!$project_id) {
    $_SESSION['error'] = 'Проект не указан';
    header('Location: projects.php');
    exit;
}


try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            s.name as service_name,
            pt.name as payment_type_name,
            ps.name as project_stage_name,
            ps.probability as stage_probability,
            u_manager.full_name as manager_name,
            u_creator.full_name as creator_name,
            seg.name as segment_name
        FROM projects p
        LEFT JOIN services s ON p.service_id = s.id
        LEFT JOIN payment_types pt ON p.payment_type_id = pt.id
        LEFT JOIN project_stages ps ON p.project_stage_id = ps.id
        LEFT JOIN users u_manager ON p.manager_id = u_manager.id
        LEFT JOIN users u_creator ON p.created_by = u_creator.id
        LEFT JOIN segments seg ON p.segment_id = seg.id
        WHERE p.id = ?
    ");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project) {
        $_SESSION['error'] = 'Проект не найден';
        header('Location: projects.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error getting project: " . $e->getMessage());
    $_SESSION['error'] = 'Ошибка при загрузке проекта';
    header('Location: projects.php');
    exit;
}


try {
    $stmt = $pdo->prepare("
        SELECT 
            pr.*,
            rs.name as status_name
        FROM project_revenue pr
        LEFT JOIN revenue_statuses rs ON pr.revenue_status_id = rs.id
        WHERE pr.project_id = ?
        ORDER BY pr.year DESC, pr.month DESC
    ");
    $stmt->execute([$project_id]);
    $revenues = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error getting project revenues: " . $e->getMessage());
    $revenues = [];
}


try {
    $stmt = $pdo->prepare("
        SELECT 
            pc.*,
            ct.name as cost_type_name,
            cs.name as cost_status_name
        FROM project_costs pc
        LEFT JOIN cost_types ct ON pc.cost_type_id = ct.id
        LEFT JOIN cost_statuses cs ON pc.cost_status_id = cs.id
        WHERE pc.project_id = ?
        ORDER BY pc.year DESC, pc.month DESC
    ");
    $stmt->execute([$project_id]);
    $costs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error getting project costs: " . $e->getMessage());
    $costs = [];
}


try {
    $stmt = $pdo->prepare("
        SELECT 
            ph.*,
            u.full_name as changed_by_name
        FROM project_history ph
        LEFT JOIN users u ON ph.changed_by = u.id
        WHERE ph.project_id = ?
        ORDER BY ph.changed_at DESC
    ");
    $stmt->execute([$project_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error getting project history: " . $e->getMessage());
    $history = [];
}


try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_role = $current_user['role'] ?? 'user';
} catch (PDOException $e) {
    error_log("Error getting user role: " . $e->getMessage());
    $user_role = 'user';
}


$can_edit = in_array($user_role, ['admin', 'analyst']) || $project['created_by'] == $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Просмотр проекта - Ростелеком</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

    /* Заголовок проекта */
    .project-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 40px;
        padding-bottom: 25px;
        border-bottom: 2px solid var(--border-light);
    }

    .project-title h1 {
        color: var(--primary);
        margin-bottom: 15px;
        font-size: 2.2rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .project-meta {
        color: var(--text-light);
        font-size: 1.1rem;
        font-weight: 500;
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

    /* Основная сетка */
    .info-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 30px;
    }

    @media (max-width: 1200px) {
        .info-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Секции */
    .info-section {
        background: var(--bg-white);
        padding: 30px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        margin-bottom: 25px;
        border: 1px solid var(--border-light);
        transition: transform 0.3s ease;
    }

    .info-section:hover {
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

    /* Карточки информации */
    .info-card {
        background: var(--bg-gray);
        padding: 25px;
        border-radius: 15px;
        margin-bottom: 20px;
        border-left: 5px solid var(--primary);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .info-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(120, 0, 255, 0.05), transparent);
        transition: left 0.6s ease;
    }

    .info-card:hover::before {
        left: 100%;
    }

    .info-card:hover {
        transform: translateX(8px);
        box-shadow: 0 8px 20px rgba(120, 0, 255, 0.1);
    }

    .info-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .info-item {
        margin-bottom: 15px;
    }

    .info-label {
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 8px;
        font-size: 1rem;
    }

    .info-value {
        color: var(--text-dark);
        font-size: 1rem;
        line-height: 1.5;
    }

    /* Флаги проекта */
    .flag {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 15px;
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-right: 10px;
        margin-bottom: 10px;
    }

    .flag-true {
        background: linear-gradient(135deg, #d1fae5, #10b981);
        color: #065f46;
    }

    .flag-false {
        background: linear-gradient(135deg, #fef3c7, #f59e0b);
        color: #92400e;
    }

    /* Текстовые блоки */
    .text-block {
        background: var(--bg-gray);
        padding: 25px;
        border-radius: 15px;
        margin-bottom: 20px;
        border-left: 5px solid var(--primary);
        transition: all 0.3s ease;
    }

    .text-block:hover {
        transform: translateX(5px);
    }

    .text-block h4 {
        color: var(--primary);
        margin-bottom: 15px;
        font-size: 1.2rem;
        font-weight: 700;
    }

    .text-content {
        line-height: 1.6;
        color: var(--text-dark);
    }

    /* Прогресс-бар */
    .progress-container {
        margin-top: 10px;
    }

    .progress-bar {
        width: 100%;
        height: 12px;
        background-color: var(--border-light);
        border-radius: 10px;
        overflow: hidden;
        margin: 8px 0;
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

    .progress-text {
        font-size: 0.9rem;
        color: var(--text-light);
        text-align: right;
        font-weight: 600;
    }

    /* История изменений */
    .history-item {
        background: var(--bg-gray);
        padding: 25px;
        border-radius: 15px;
        margin-bottom: 20px;
        border-left: 5px solid var(--accent);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .history-item:hover {
        transform: translateX(8px);
        box-shadow: 0 8px 20px rgba(0, 229, 255, 0.1);
    }

    .history-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .history-field {
        font-weight: 700;
        color: var(--primary);
        font-size: 1.1rem;
    }

    .history-meta {
        font-size: 0.9rem;
        color: var(--text-light);
        font-weight: 500;
    }

    .history-changes {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-top: 15px;
    }

    .history-old, .history-new {
        padding: 15px;
        border-radius: 10px;
        font-weight: 500;
    }

    .history-old {
        background: linear-gradient(135deg, #fef2f2, #fecaca);
        color: #dc2626;
        border-left: 4px solid #ef4444;
    }

    .history-new {
        background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        color: #16a34a;
        border-left: 4px solid #22c55e;
    }

    /* Таблицы */
    .data-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-top: 20px;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }

    .data-table th,
    .data-table td {
        padding: 16px 20px;
        text-align: left;
        border-bottom: 1px solid var(--border-light);
    }

    .data-table th {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        font-weight: 700;
        font-size: 1rem;
    }

    .data-table tr:hover {
        background-color: var(--bg-gray);
    }

    .data-table tfoot tr {
        background: linear-gradient(135deg, var(--bg-gray) 0%, #f0f0f0 100%);
        font-weight: 800;
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

    .btn-warning {
        background: linear-gradient(135deg, #fe4e12 0%, #fe4e12 100%);
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
    }

    .btn-warning:hover {
        background: linear-gradient(135deg, #fbbf24 0%, var(--warning) 100%);
        box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
    }

    .btn-danger {
        background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
        box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
    }

    .btn-danger:hover {
        background: linear-gradient(135deg, #f87171 0%, var(--danger) 100%);
        box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
    }

    /* Состояние пустоты */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
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

    .stat-card, .info-section, .info-card, .history-item {
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
        
        .project-header {
            flex-direction: column;
            gap: 20px;
        }
        
        .project-actions {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .info-row {
            grid-template-columns: 1fr;
        }
        
        .history-changes {
            grid-template-columns: 1fr;
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

    .month-name {
        text-transform: lowercase;
        font-style: italic;
        color: var(--text-light);
    }

    .project-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
</style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-project-diagram"></i> Ростелеком - Просмотр проекта</h1>
        </div>

        <div class="breadcrumb">
            <a href="index.php"><i class="fas fa-home"></i> Дашборд</a> &raquo; 
            <a href="projects.php"><i class="fas fa-list"></i> Проекты</a> &raquo; 
            <span><i class="fas fa-eye"></i> Просмотр проекта</span>
        </div>
        
        <div class="content-container">
            
            <div class="project-header">
                <div class="project-title">
                    <h1><?php echo htmlspecialchars($project['project_name']); ?></h1>
                    <div class="project-meta">
                        Клиент: <strong><?php echo htmlspecialchars($project['client_name']); ?></strong> | 
                        ИНН: <?php echo htmlspecialchars($project['client_inn']); ?> |
                        № проекта: <?php echo htmlspecialchars($project['project_number'] ?? 'Не указан'); ?>
                    </div>
                </div>
                <div class="project-actions">
                    <a href="projects.php" class="btn">
                        <i class="fas fa-arrow-left"></i> Назад
                    </a>
                    <?php if ($can_edit): ?>
                        <a href="edit_project.php?id=<?php echo $project_id; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Редактировать
                        </a>
                    <?php endif; ?>
                    <button class="btn" onclick="window.print()">
                        <i class="fas fa-print"></i> Печать
                    </button>
                </div>
            </div>

            
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $project['probability']; ?>%</div>
                    <div class="stat-label">Вероятность реализации</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($revenues); ?></div>
                    <div class="stat-label">Периодов выручки</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($costs); ?></div>
                    <div class="stat-label">Периодов затрат</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($history); ?></div>
                    <div class="stat-label">Записей в истории</div>
                </div>
            </div>

            <div class="info-grid">
                
                <div>
                    
                    <div class="info-section">
                        <div class="section-header">
                            <h2><i class="fas fa-info-circle"></i> Общая информация</h2>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-row">
                                <div class="info-item">
                                    <div class="info-label">Клиент</div>
                                    <div class="info-value"><?php echo htmlspecialchars($project['client_name']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">ИНН</div>
                                    <div class="info-value"><?php echo htmlspecialchars($project['client_inn']); ?></div>
                                </div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-item">
                                    <div class="info-label">Услуга</div>
                                    <div class="info-value"><?php echo htmlspecialchars($project['service_name'] ?? 'Не указана'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Тип платежа</div>
                                    <div class="info-value"><?php echo htmlspecialchars($project['payment_type_name'] ?? 'Не указан'); ?></div>
                                </div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-item">
                                    <div class="info-label">Этап проекта</div>
                                    <div class="info-value">
                                        <?php echo htmlspecialchars($project['project_stage_name'] ?? 'Не указан'); ?>
                                        <div class="progress-container">
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo $project['probability']; ?>%;"></div>
                                            </div>
                                            <div class="progress-text">Вероятность: <?php echo $project['probability']; ?>%</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Менеджер</div>
                                    <div class="info-value"><?php echo htmlspecialchars($project['manager_name'] ?? 'Не назначен'); ?></div>
                                </div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-item">
                                    <div class="info-label">Сегмент бизнеса</div>
                                    <div class="info-value"><?php echo htmlspecialchars($project['segment_name'] ?? 'Не указан'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Год реализации</div>
                                    <div class="info-value"><?php echo htmlspecialchars($project['implementation_year'] ?? 'Не указан'); ?></div>
                                </div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-item">
                                    <div class="info-label">Флаги проекта</div>
                                    <div class="info-value">
                                        <span class="flag flag-<?php echo $project['is_industry_solution'] ? 'true' : 'false'; ?>">
                                            Отраслевое решение
                                        </span>
                                        <span class="flag flag-<?php echo $project['is_forecast_accepted'] ? 'true' : 'false'; ?>">
                                            Принимаемый к прогнозу
                                        </span>
                                        <span class="flag flag-<?php echo $project['is_dzo_implementation'] ? 'true' : 'false'; ?>">
                                            Реализация через ДЗО
                                        </span>
                                        <span class="flag flag-<?php echo $project['requires_management_control'] ? 'true' : 'false'; ?>">
                                            Контроль руководства
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-item">
                                    <div class="info-label">Статус оценки</div>
                                    <div class="info-value"><?php echo htmlspecialchars($project['evaluation_status'] ?? 'Не указан'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Отраслевой менеджер</div>
                                    <div class="info-value"><?php echo htmlspecialchars($project['industry_manager'] ?? 'Не назначен'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    
                    <div class="info-section">
                        <div class="section-header">
                            <h2><i class="fas fa-comments"></i> Дополнительная информация</h2>
                        </div>
                        
                        <?php if ($project['current_status']): ?>
                        <div class="text-block">
                            <h4>Текущий статус по проекту</h4>
                            <div class="text-content"><?php echo nl2br(htmlspecialchars($project['current_status'])); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($project['completed_in_period']): ?>
                        <div class="text-block">
                            <h4>Что сделано за период</h4>
                            <div class="text-content"><?php echo nl2br(htmlspecialchars($project['completed_in_period'])); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($project['plans_for_next_period']): ?>
                        <div class="text-block">
                            <h4>Планы на следующий период</h4>
                            <div class="text-content"><?php echo nl2br(htmlspecialchars($project['plans_for_next_period'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                   
                    <div class="info-section">
                        <div class="section-header">
                            <h2><i class="fas fa-history"></i> История изменений</h2>
                        </div>
                        
                        <?php if (empty($history)): ?>
                            <div class="empty-state">
                                <i class="fas fa-history"></i>
                                <h3>История изменений отсутствует</h3>
                                <p>Изменения в проекте будут отображаться здесь</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($history as $record): ?>
                                <div class="history-item">
                                    <div class="history-header">
                                        <span class="history-field"><?php echo htmlspecialchars($record['changed_field']); ?></span>
                                        <span class="history-meta">
                                            <?php echo date('d.m.Y H:i', strtotime($record['changed_at'])); ?> | 
                                            <?php echo htmlspecialchars($record['changed_by_name']); ?>
                                        </span>
                                    </div>
                                    <?php if ($record['old_value'] || $record['new_value']): ?>
                                        <div class="history-changes">
                                            <?php if ($record['old_value']): ?>
                                                <div class="history-old">
                                                    <strong>Было:</strong><br>
                                                    <?php echo htmlspecialchars($record['old_value']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($record['new_value']): ?>
                                                <div class="history-new">
                                                    <strong>Стало:</strong><br>
                                                    <?php echo htmlspecialchars($record['new_value']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                
                <div>
                   
                    <div class="info-section">
                        <div class="section-header">
                            <h2><i class="fas fa-chart-line"></i> Выручка проекта</h2>
                        </div>
                        
                        <?php if (empty($revenues)): ?>
                            <div class="empty-state">
                                <i class="fas fa-chart-line"></i>
                                <h3>Данные о выручке отсутствуют</h3>
                                <p>Информация о выручке будет отображаться здесь</p>
                            </div>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Период</th>
                                        <th>Сумма</th>
                                        <th>Статус</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_revenue = 0;
                                    $months = [
                                        1 => 'январь', 2 => 'февраль', 3 => 'март', 4 => 'апрель',
                                        5 => 'май', 6 => 'июнь', 7 => 'июль', 8 => 'август',
                                        9 => 'сентябрь', 10 => 'октябрь', 11 => 'ноябрь', 12 => 'декабрь'
                                    ];
                                    ?>
                                    <?php foreach ($revenues as $revenue): ?>
                                        <?php 
                                        $total_revenue += floatval($revenue['amount']);
                                        $month_name = $revenue['month'] ? $months[$revenue['month']] : '';
                                        ?>
                                        <tr>
                                            <td>
                                                <?php echo $revenue['year']; ?>
                                                <?php if ($month_name): ?>
                                                    <br><span class="month-name"><?php echo $month_name; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo number_format($revenue['amount'], 2, ',', ' '); ?> ₽</td>
                                            <td><?php echo htmlspecialchars($revenue['status_name'] ?? 'Не указан'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background-color: #f8f9fa; font-weight: bold;">
                                        <td>Итого:</td>
                                        <td><?php echo number_format($total_revenue, 2, ',', ' '); ?> ₽</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        <?php endif; ?>
                    </div>

                    
                    <div class="info-section">
                        <div class="section-header">
                            <h2><i class="fas fa-money-bill-wave"></i> Затраты проекта</h2>
                        </div>
                        
                        <?php if (empty($costs)): ?>
                            <div class="empty-state">
                                <i class="fas fa-money-bill-wave"></i>
                                <h3>Данные о затратах отсутствуют</h3>
                                <p>Информация о затратах будет отображаться здесь</p>
                            </div>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Период</th>
                                        <th>Сумма</th>
                                        <th>Вид затрат</th>
                                        <th>Статус</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_costs = 0;
                                    ?>
                                    <?php foreach ($costs as $cost): ?>
                                        <?php 
                                        $total_costs += floatval($cost['amount']);
                                        $month_name = $cost['month'] ? $months[$cost['month']] : '';
                                        ?>
                                        <tr>
                                            <td>
                                                <?php echo $cost['year']; ?>
                                                <?php if ($month_name): ?>
                                                    <br><span class="month-name"><?php echo $month_name; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo number_format($cost['amount'], 2, ',', ' '); ?> ₽</td>
                                            <td><?php echo htmlspecialchars($cost['cost_type_name'] ?? 'Не указан'); ?></td>
                                            <td><?php echo htmlspecialchars($cost['cost_status_name'] ?? 'Не указан'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background-color: #f8f9fa; font-weight: bold;">
                                        <td>Итого:</td>
                                        <td><?php echo number_format($total_costs, 2, ',', ' '); ?> ₽</td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        <?php endif; ?>
                    </div>

                    
                    <div class="info-section">
                        <div class="section-header">
                            <h2><i class="fas fa-cog"></i> Системная информация</h2>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-item">
                                <div class="info-label">Создатель проекта</div>
                                <div class="info-value"><?php echo htmlspecialchars($project['creator_name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Дата создания</div>
                                <div class="info-value"><?php echo date('d.m.Y H:i', strtotime($project['created_at'])); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Последнее обновление</div>
                                <div class="info-value">
                                    <?php 
                                    if ($project['updated_at'] && $project['updated_at'] != $project['created_at']) {
                                        echo date('d.m.Y H:i', strtotime($project['updated_at']));
                                    } else {
                                        echo 'Не обновлялся';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
       
        setTimeout(function() {
            const messages = document.querySelectorAll('.error-message, .success-message');
            messages.forEach(message => {
                message.style.display = 'none';
            });
        }, 5000);

        
        document.addEventListener('DOMContentLoaded', function() {
            const sections = document.querySelectorAll('.info-section');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.backgroundColor = '#f8f9fa';
                        setTimeout(() => {
                            entry.target.style.backgroundColor = '';
                        }, 1000);
                    }
                });
            }, { threshold: 0.5 });

            sections.forEach(section => {
                observer.observe(section);
            });
        });
    </script>
</body>
</html>