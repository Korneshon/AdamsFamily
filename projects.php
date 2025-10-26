<?php
// projects.php - Страница управления проектами
require_once 'config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

// Получение параметров фильтрации
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$search_query = $_GET['search'] ?? '';

// Построение запроса с фильтрами
try {
    $sql = "
        SELECT p.*, u.username as manager_username, u.full_name as manager_name,
               creator.username as created_by_username, creator.full_name as created_by_name
        FROM projects p
        LEFT JOIN users u ON p.manager_id = u.id
        LEFT JOIN users creator ON p.created_by = creator.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Добавляем фильтры
    if (!empty($status_filter)) {
        $sql .= " AND p.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($priority_filter)) {
        $sql .= " AND p.priority = ?";
        $params[] = $priority_filter;
    }
    
    if (!empty($search_query)) {
        $sql .= " AND (p.title LIKE ? OR p.description LIKE ?)";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    }
    
    $sql .= " ORDER BY p.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching projects: " . $e->getMessage());
    $error = 'Ошибка при загрузке проектов';
    $projects = [];
}

// Получение статистики проектов
$stats = getProjectsStats($pdo);

// Обработка удаления проекта
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'analyst') {
        if (deleteProject($pdo, $_GET['delete'])) {
            $_SESSION['success'] = 'Проект успешно удален';
            header('Location: projects.php');
            exit;
        } else {
            $error = 'Ошибка при удалении проекта';
        }
    } else {
        $error = 'У вас нет прав для удаления проектов';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление проектами - Ростелеком</title>
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
        --success: #fe4e12;
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
        color: white;
        padding: 25px 30px;
        border-radius: 20px 20px 0 0;
        margin-bottom: 0;
        box-shadow: 0 10px 30px rgba(120, 0, 255, 0.2);
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
        margin: 0;
        font-size: 1.8rem;
        font-weight: 700;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }

    .breadcrumb {
        background: var(--bg-white);
        padding: 18px 30px;
        border-bottom: 1px solid var(--border-light);
        font-size: 0.95rem;
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

    /* Секции с улучшенным дизайном */
    .filters-section, .projects-list {
        background: var(--bg-white);
        padding: 35px;
        border-radius: 20px;
        margin-bottom: 25px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        border: 1px solid var(--border-light);
        transition: transform 0.3s ease;
    }

    .filters-section:hover, .projects-list:hover {
        transform: translateY(-2px);
    }

    .form-group {
        margin-bottom: 25px;
    }

    .form-group label {
        display: block;
        margin-bottom: 10px;
        color: var(--text-dark);
        font-weight: 600;
        font-size: 0.95rem;
    }

    .form-control {
        width: 100%;
        padding: 14px 18px;
        border: 2px solid var(--border-light);
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: var(--bg-white);
    }

    .form-control:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 4px rgba(120, 0, 255, 0.1);
        transform: translateY(-1px);
    }

    /* Улучшенные кнопки */
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
        margin-right: 12px;
        box-shadow: 0 6px 20px rgba(120, 0, 255, 0.3);
    }

    .btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(120, 0, 255, 0.4);
        background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
    }

    .btn-success {
        background: linear-gradient(135deg, var(--success) 0%, #fe4e12 100%);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
    }

    .btn-success:hover {
        background: linear-gradient(135deg, #7800ff 0%, var#7800ff 100%);
    }

    .btn-danger {
        background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
        box-shadow: 0 6px 20px rgba(239, 68, 68, 0.3);
    }

    .btn-danger:hover {
        background: linear-gradient(135deg, #f87171 0%, var(--danger) 100%);
    }

    .btn-warning {
        background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);
        color: #1e293b;
        box-shadow: 0 6px 20px rgba(245, 158, 11, 0.3);
    }

    .btn-warning:hover {
        background: linear-gradient(135deg, #fbbf24 0%, var(--warning) 100%);
    }

    /* Сообщения */
    .error-message {
        color: var(--danger);
        padding: 20px;
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        border-radius: 12px;
        margin-bottom: 25px;
        border-left: 5px solid var(--danger);
        font-weight: 500;
    }

    .success-message {
        color: #065f46;
        padding: 20px;
        background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        border-radius: 12px;
        margin-bottom: 25px;
        border-left: 5px solid var(--success);
        font-weight: 500;
    }

    /* Карточки статистики */
    .stats-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 25px;
        margin-bottom: 35px;
    }

    .stat-card {
        background: var(--bg-white);
        padding: 30px;
        border-radius: 20px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
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
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 15px 40px rgba(120, 0, 255, 0.15);
    }

    .stat-number {
        font-size: 2.8rem;
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

    /* Сетка проектов */
    .projects-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
        gap: 25px;
        margin-top: 25px;
    }

    .project-card-compact {
        background: var(--bg-white);
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        border-left: 5px solid var(--primary);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        height: 100%;
        display: flex;
        flex-direction: column;
        position: relative;
        overflow: hidden;
    }

    .project-card-compact::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(120, 0, 255, 0.03), transparent);
        transition: left 0.6s ease;
    }

    .project-card-compact:hover::before {
        left: 100%;
    }

    .project-card-compact:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 15px 40px rgba(120, 0, 255, 0.15);
    }

    .project-header-compact {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
    }

    .project-title-compact {
        font-size: 1.3rem;
        color: var(--primary);
        margin-bottom: 10px;
        font-weight: 700;
        line-height: 1.3;
    }

    .project-meta-compact {
        color: var(--text-light);
        font-size: 0.9rem;
        margin-bottom: 15px;
    }

    .project-meta-compact span {
        display: block;
        margin-bottom: 6px;
    }

    .project-description-compact {
        margin-bottom: 20px;
        line-height: 1.5;
        color: var(--text-dark);
        font-size: 0.95rem;
        flex-grow: 1;
    }

    /* Статус-бейджи */
    .status-badge {
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }

    .status-planning { 
        background: linear-gradient(135deg, #fff3cd, var(--warning)); 
        color: #92400e; 
    }
    .status-active { 
        background: linear-gradient(135deg, #d1ecf1, var(--accent)); 
        color: #0c5460; 
    }
    .status-on_hold { 
        background: linear-gradient(135deg, #f8d7da, var(--danger)); 
        color: #721c24; 
    }
    .status-completed { 
        background: linear-gradient(135deg, #d4edda, var(--success)); 
        color: #155724; 
    }
    .status-cancelled { 
        background: linear-gradient(135deg, #e2e3e5, #6b7280); 
        color: #374151; 
    }

    .priority-high { color: var(--danger); font-weight: 700; }
    .priority-medium { color: var(--warning); font-weight: 700; }
    .priority-low { color: var(--success); font-weight: 700; }

    /* Прогресс-бар */
    .progress-container-compact {
        margin: 20px 0;
    }

    .progress-bar-compact {
        width: 100%;
        height: 10px;
        background-color: var(--border-light);
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 8px;
        position: relative;
    }

    .progress-fill-compact {
        height: 100%;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        border-radius: 10px;
        transition: width 1.5s ease-in-out;
        position: relative;
    }

    .progress-fill-compact::after {
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

    .progress-text-compact {
        font-size: 0.85rem;
        color: var(--text-light);
        display: flex;
        justify-content: space-between;
        font-weight: 500;
    }

    .project-footer-compact {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid var(--border-light);
    }

    .project-actions-compact {
        display: flex;
        gap: 10px;
    }

    .btn-compact {
        padding: 10px 14px;
        font-size: 0.9rem;
        border-radius: 10px;
    }

    /* Фильтры */
    .filter-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    h1, h2 {
        margin-bottom: 25px;
        font-weight: 700;
    }

    .empty-state {
        text-align: center;
        padding: 80px 20px;
        color: var(--text-light);
        grid-column: 1 / -1;
    }

    .empty-state i {
        font-size: 5rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 25px;
    }

    .empty-state h3 {
        margin-bottom: 15px;
        color: var(--text-dark);
        font-size: 1.5rem;
    }

    .budget-info {
        background: var(--bg-gray);
        padding: 12px 18px;
        border-radius: 10px;
        font-size: 0.9rem;
        margin-top: 12px;
        border-left: 3px solid var(--primary);
    }

    .view-toggle {
        display: flex;
        gap: 12px;
        margin-bottom: 25px;
    }

    .view-toggle-btn {
        padding: 12px 20px;
        border: 2px solid var(--border-light);
        background: var(--bg-white);
        border-radius: 10px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s ease;
        color: var(--text-light);
    }

    .view-toggle-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 4px 15px rgba(120, 0, 255, 0.3);
    }

    .view-toggle-btn:hover:not(.active) {
        border-color: var(--primary);
        color: var(--primary);
    }

    /* Детальный просмотр */
    .project-card-detailed {
        background: var(--bg-white);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 25px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        border-left: 5px solid var(--primary);
        transition: all 0.3s ease;
    }

    .project-card-detailed:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 35px rgba(120, 0, 255, 0.15);
    }

    .project-header-detailed {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
    }

    .project-title-detailed {
        font-size: 1.5rem;
        color: var(--primary);
        margin-bottom: 10px;
        font-weight: 700;
    }

    .project-meta-detailed {
        color: var(--text-light);
        font-size: 0.95rem;
        margin-bottom: 15px;
    }

    .project-meta-detailed span {
        margin-right: 20px;
    }

    .project-description-detailed {
        margin-bottom: 25px;
        line-height: 1.6;
        color: var(--text-dark);
    }

    .progress-bar-detailed {
        width: 100%;
        height: 12px;
        background-color: var(--border-light);
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 8px;
    }

    .progress-fill-detailed {
        height: 100%;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        border-radius: 10px;
        transition: width 1.5s ease-in-out;
    }

    .progress-text-detailed {
        font-size: 0.9rem;
        color: var(--text-light);
        display: flex;
        justify-content: space-between;
        font-weight: 500;
    }

    .project-footer-detailed {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 2px solid var(--border-light);
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

    .stat-card, .project-card-compact, .project-card-detailed {
        animation: fadeInUp 0.6s ease-out;
    }

    /* Адаптивность */
    @media (max-width: 1200px) {
        .projects-grid {
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        }
    }

    @media (max-width: 768px) {
        body {
            padding: 15px;
        }
        
        .nav-bar {
            padding: 20px;
        }
        
        .nav-bar h1 {
            font-size: 1.5rem;
        }
        
        .filters-section, .projects-list {
            padding: 25px;
        }
        
        .projects-grid {
            grid-template-columns: 1fr;
        }
        
        .project-header-compact, .project-header-detailed {
            flex-direction: column;
            gap: 15px;
        }
        
        .project-footer-compact, .project-footer-detailed {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }
        
        .project-actions-compact {
            width: 100%;
            justify-content: flex-start;
        }
        
        .filter-row {
            grid-template-columns: 1fr;
        }
        
        .view-toggle {
            flex-direction: column;
        }
    }

    @media (max-width: 480px) {
        .btn {
            width: 100%;
            justify-content: center;
            margin-bottom: 10px;
        }
        
        .project-actions-compact {
            flex-wrap: wrap;
        }
    }
</style>
</head>
<body>
    <div class="container">
        <!-- Навигационная панель -->
        <div class="nav-bar">
            <h1>Ростелеком - Управление проектами</h1>
        </div>

        <!-- Хлебные крошки -->
        <div class="breadcrumb">
            <a href="index.php">Дашборд</a> &raquo; 
            <span>Управление проектами</span>
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

       
      
        <div class="filters-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">Фильтры проектов</h2>
                <a href="add_project.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Добавить проект
                </a>
            </div>

            <form method="GET" action="">
                <div class="filter-row">
                    <div class="form-group">
                        <label>Статус проекта:</label>
                        <select name="status" class="form-control">
                            <option value="">Все статусы</option>
                            <option value="planning" <?php echo $status_filter == 'planning' ? 'selected' : ''; ?>>Планирование</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Активный</option>
                            <option value="on_hold" <?php echo $status_filter == 'on_hold' ? 'selected' : ''; ?>>На паузе</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Завершен</option>
                            <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Отменен</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Приоритет:</label>
                        <select name="priority" class="form-control">
                            <option value="">Все приоритеты</option>
                            <option value="low" <?php echo $priority_filter == 'low' ? 'selected' : ''; ?>>Низкий</option>
                            <option value="medium" <?php echo $priority_filter == 'medium' ? 'selected' : ''; ?>>Средний</option>
                            <option value="high" <?php echo $priority_filter == 'high' ? 'selected' : ''; ?>>Высокий</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Поиск:</label>
                        <input type="text" name="search" class="form-control" 
                               value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="Поиск по названию или описанию...">
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-filter"></i> Применить фильтры
                </button>
                <a href="projects.php" class="btn">
                    <i class="fas fa-times"></i> Сбросить
                </a>
            </form>
        </div>

        <!-- Список проектов -->
        <div class="projects-list">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Список проектов (<?php echo count($projects); ?>)</h2>
                
                <div class="view-toggle">
                    <button class="view-toggle-btn active" data-view="grid">
                        <i class="fas fa-th"></i> Сетка
                    </button>
                    <button class="view-toggle-btn" data-view="list">
                        <i class="fas fa-list"></i> Список
                    </button>
                </div>
                
                <?php if (!empty($status_filter) || !empty($priority_filter) || !empty($search_query)): ?>
                    <div style="color: var(--rtk-dark-gray); font-size: 0.9rem;">
                        Применены фильтры: 
                        <?php 
                            $active_filters = [];
                            if (!empty($status_filter)) $active_filters[] = "Статус: " . ($status_filter == 'planning' ? 'Планирование' : ($status_filter == 'active' ? 'Активный' : ($status_filter == 'on_hold' ? 'На паузе' : ($status_filter == 'completed' ? 'Завершен' : 'Отменен'))));
                            if (!empty($priority_filter)) $active_filters[] = "Приоритет: " . ($priority_filter == 'low' ? 'Низкий' : ($priority_filter == 'medium' ? 'Средний' : 'Высокий'));
                            if (!empty($search_query)) $active_filters[] = "Поиск: " . $search_query;
                            echo implode(', ', $active_filters);
                        ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($projects)): ?>
                <div class="empty-state">
                    <i class="fas fa-project-diagram"></i>
                    <h3>Проекты не найдены</h3>
                    <p><?php echo (!empty($status_filter) || !empty($priority_filter) || !empty($search_query)) ? 'Попробуйте изменить параметры фильтрации' : 'Создайте первый проект для начала работы'; ?></p>
                    <a href="add_project.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Создать проект
                    </a>
                    <?php if (!empty($status_filter) || !empty($priority_filter) || !empty($search_query)): ?>
                        <a href="projects.php" class="btn" style="margin-left: 10px;">
                            <i class="fas fa-list"></i> Показать все проекты
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Представление в виде сетки (как на главной) -->
                <div class="projects-grid" id="grid-view">
                    <?php foreach ($projects as $project): ?>
                        <?php 
                            // Форматирование дат
                            $start_date = !empty($project['start_date']) ? date('d.m.Y', strtotime($project['start_date'])) : 'Не указана';
                            $end_date = !empty($project['end_date']) ? date('d.m.Y', strtotime($project['end_date'])) : 'Не указана';
                            $created_date = date('d.m.Y', strtotime($project['created_at']));
                            
                            // Перевод статусов и приоритетов
                            $status_names = [
                                'planning' => 'Планирование',
                                'active' => 'Активный',
                                'on_hold' => 'На паузе',
                                'completed' => 'Завершен',
                                'cancelled' => 'Отменен'
                            ];
                            
                            $priority_names = [
                                'low' => 'Низкий',
                                'medium' => 'Средний',
                                'high' => 'Высокий'
                            ];
                            
                            // Обрезаем описание для компактного вида
                            $short_description = !empty($project['description']) ? 
                                (strlen($project['description']) > 120 ? 
                                    substr($project['description'], 0, 120) . '...' : 
                                    $project['description']) : 
                                'Описание отсутствует';
                        ?>
                        
                        <div class="project-card-compact">
                            <div class="project-header-compact">
                                <div style="flex: 1;">
                                    <div class="project-title-compact"><?php echo htmlspecialchars($project['title'] ?? ''); ?></div>
                                    <div class="project-meta-compact">
                                        <span><strong>Менеджер:</strong> <?php echo htmlspecialchars($project['manager_name'] ?? 'Не назначен'); ?></span>
                                        <span><strong>Приоритет:</strong> <span class="priority-<?php echo $project['priority'] ?? ''; ?>">
                                            <?php echo $priority_names[$project['priority'] ?? ''] ?? ($project['priority'] ?? 'Не указан'); ?>
                                        </span></span>
                                    </div>
                                </div>
                                <span class="status-badge status-<?php echo $project['status'] ?? ''; ?>">
                                    <?php echo $status_names[$project['status'] ?? ''] ?? ($project['status'] ?? 'Неизвестен'); ?>
                                </span>
                            </div>
                            
                            <div class="project-description-compact">
                                <?php echo htmlspecialchars($short_description); ?>
                            </div>
                            
                            <div class="progress-container-compact">
                                <div class="progress-bar-compact">
                                    <div class="progress-fill-compact" style="width: <?php echo $project['progress'] ?? 0; ?>%;"></div>
                                </div>
                                <div class="progress-text-compact">
                                    <span>Прогресс</span>
                                    <span><strong><?php echo $project['progress'] ?? 0; ?>%</strong></span>
                                </div>
                            </div>
                            
                            <div class="project-meta-compact">
                                <span><strong>Сроки:</strong> <?php echo $start_date; ?> - <?php echo $end_date; ?></span>
                                <?php if (!empty($project['budget']) && $project['budget'] > 0): ?>
                                    <span><strong>Бюджет:</strong> <?php echo number_format($project['budget'], 0, ',', ' '); ?> ₽</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="project-footer-compact">
                                <div class="project-info">
                                    <small style="color: var(--rtk-dark-gray);">
                                        Создан: <?php echo $created_date; ?>
                                    </small>
                                </div>
                                <div class="project-actions-compact">
                                    <a href="view_project.php?id=<?php echo $project['id']; ?>" class="btn btn-compact" title="Просмотреть">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_project.php?id=<?php echo $project['id']; ?>" class="btn btn-success btn-compact" title="Редактировать">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'analyst' || ($project['created_by'] ?? 0) == $_SESSION['user_id']): ?>
                                        <a href="projects.php?delete=<?php echo $project['id']; ?>" 
                                           class="btn btn-danger btn-compact" 
                                           title="Удалить"
                                           onclick="return confirm('Вы уверены, что хотите удалить проект \"<?php echo htmlspecialchars($project['title'] ?? ''); ?>\"?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Представление в виде списка (детальное) -->
                <div id="list-view" style="display: none;">
                    <?php foreach ($projects as $project): ?>
                        <?php 
                            // Форматирование дат
                            $start_date = !empty($project['start_date']) ? date('d.m.Y', strtotime($project['start_date'])) : 'Не указана';
                            $end_date = !empty($project['end_date']) ? date('d.m.Y', strtotime($project['end_date'])) : 'Не указана';
                            $created_date = date('d.m.Y', strtotime($project['created_at']));
                            
                            // Перевод статусов и приоритетов
                            $status_names = [
                                'planning' => 'Планирование',
                                'active' => 'Активный',
                                'on_hold' => 'На паузе',
                                'completed' => 'Завершен',
                                'cancelled' => 'Отменен'
                            ];
                            
                            $priority_names = [
                                'low' => 'Низкий',
                                'medium' => 'Средний',
                                'high' => 'Высокий'
                            ];
                        ?>
                        
                        <div class="project-card-detailed">
                            <div class="project-header-detailed">
                                <div style="flex: 1;">
                                    <div class="project-title-detailed"><?php echo htmlspecialchars($project['title'] ?? ''); ?></div>
                                    <div class="project-meta-detailed">
                                        <span><strong>Менеджер:</strong> <?php echo htmlspecialchars($project['manager_name'] ?? 'Не назначен'); ?></span>
                                        <span><strong>Приоритет:</strong> <span class="priority-<?php echo $project['priority'] ?? ''; ?>">
                                            <?php echo $priority_names[$project['priority'] ?? ''] ?? ($project['priority'] ?? 'Не указан'); ?>
                                        </span></span>
                                        <span><strong>Создан:</strong> <?php echo $created_date; ?></span>
                                    </div>
                                </div>
                                <span class="status-badge status-<?php echo $project['status'] ?? ''; ?>">
                                    <?php echo $status_names[$project['status'] ?? ''] ?? ($project['status'] ?? 'Неизвестен'); ?>
                                </span>
                            </div>
                            
                            <?php if (!empty($project['description'])): ?>
                                <div class="project-description-detailed">
                                    <?php echo htmlspecialchars($project['description']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="progress-container">
                                <div class="progress-bar-detailed">
                                    <div class="progress-fill-detailed" style="width: <?php echo $project['progress'] ?? 0; ?>%;"></div>
                                </div>
                                <div class="progress-text-detailed">
                                    <span>Прогресс выполнения</span>
                                    <span><strong><?php echo $project['progress'] ?? 0; ?>%</strong></span>
                                </div>
                            </div>
                            
                            <div class="project-meta-detailed">
                                <span><strong>Дата начала:</strong> <?php echo $start_date; ?></span>
                                <span><strong>Дата окончания:</strong> <?php echo $end_date; ?></span>
                                <?php if (!empty($project['budget']) && $project['budget'] > 0): ?>
                                    <span><strong>Бюджет:</strong> <?php echo number_format($project['budget'], 0, ',', ' '); ?> ₽</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="project-footer-detailed">
                                <div class="project-info">
                                    <small style="color: var(--rtk-dark-gray);">
                                        Создал: <?php echo htmlspecialchars($project['created_by_name'] ?? 'Неизвестно'); ?>
                                    </small>
                                </div>
                                <div class="project-actions">
                                    <a href="view_project.php?id=<?php echo $project['id']; ?>" class="btn">
                                        <i class="fas fa-eye"></i> Просмотреть
                                    </a>
                                    <a href="edit_project.php?id=<?php echo $project['id']; ?>" class="btn btn-success">
                                        <i class="fas fa-edit"></i> Редактировать
                                    </a>
                                    <?php if ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'analyst' || ($project['created_by'] ?? 0) == $_SESSION['user_id']): ?>
                                        <a href="projects.php?delete=<?php echo $project['id']; ?>" 
                                           class="btn btn-danger" 
                                           onclick="return confirm('Вы уверены, что хотите удалить проект \"<?php echo htmlspecialchars($project['title'] ?? ''); ?>\"?')">
                                            <i class="fas fa-trash"></i> Удалить
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
                const projectCard = e.target.closest('.project-card-compact, .project-card-detailed');
                if (projectCard) {
                    const priorityElement = projectCard.querySelector('.priority-high');
                    if (priorityElement) {
                        return confirm('Внимание! Вы собираетесь удалить проект с высоким приоритетом. Вы уверены?');
                    }
                }
            }
        });
        
      
        document.addEventListener('DOMContentLoaded', function() {
            const viewToggleBtns = document.querySelectorAll('.view-toggle-btn');
            const gridView = document.getElementById('grid-view');
            const listView = document.getElementById('list-view');
            
      
            const savedView = localStorage.getItem('projectsView') || 'grid';
            switchView(savedView);
            
            viewToggleBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const view = this.getAttribute('data-view');
                    switchView(view);
                 
                    localStorage.setItem('projectsView', view);
                });
            });
            
            function switchView(view) {
               
                viewToggleBtns.forEach(btn => {
                    btn.classList.remove('active');
                    if (btn.getAttribute('data-view') === view) {
                        btn.classList.add('active');
                    }
                });
                
                if (view === 'grid') {
                    gridView.style.display = 'grid';
                    listView.style.display = 'none';
                } else {
                    gridView.style.display = 'none';
                    listView.style.display = 'block';
                }
            }
        });
    </script>
</body>
</html>