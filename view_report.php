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




$user_role = 'user';
try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $user_role = $user['role'];
    }
} catch (PDOException $e) {
    error_log("Error getting user role: " . $e->getMessage());
}


$report_id = $_GET['id'] ?? null;
$report = null;
$report_data = [];

if ($report_id) {

    try {
        $stmt = $pdo->prepare("
            SELECT r.*, u.full_name as creator_name 
            FROM reports r 
            LEFT JOIN users u ON r.created_by = u.id 
            WHERE r.id = ?
        ");
        $stmt->execute([$report_id]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($report) {
        
            $criteria = json_decode($report['criteria'], true) ?? [];
            
           
            $sql = "SELECT 
                    p.id,
                    p.client_name,
                    p.client_inn,
                    p.project_name,
                    s.name as service_name,
                    pt.name as payment_type,
                    ps.name as project_stage,
                    p.probability,
                    u.full_name as manager_name,
                    seg.name as segment_name,
                    p.implementation_year,
                    p.is_industry_solution,
                    p.is_forecast_accepted,
                    p.current_status,
                    p.created_at
                FROM projects p
                LEFT JOIN services s ON p.service_id = s.id
                LEFT JOIN payment_types pt ON p.payment_type_id = pt.id
                LEFT JOIN project_stages ps ON p.project_stage_id = ps.id
                LEFT JOIN users u ON p.manager_id = u.id
                LEFT JOIN segments seg ON p.segment_id = seg.id
                WHERE 1=1
            ";
            
            $params = [];
            
            
            if (!empty($criteria['date_from'])) {
                $sql .= " AND p.created_at >= ?";
                $params[] = $criteria['date_from'];
            }
            
            if (!empty($criteria['date_to'])) {
                $sql .= " AND p.created_at <= ?";
                $params[] = $criteria['date_to'];
            }
            
            if (!empty($criteria['segment'])) {
                $sql .= " AND seg.name LIKE ?";
                $params[] = '%' . $criteria['segment'] . '%';
            }
            
            if (!empty($criteria['status'])) {
                $sql .= " AND ps.name LIKE ?";
                $params[] = '%' . $criteria['status'] . '%';
            }
            
            if (!empty($criteria['manager'])) {
                $sql .= " AND u.full_name LIKE ?";
                $params[] = '%' . $criteria['manager'] . '%';
            }
            
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
           
            $stats_sql = "
                SELECT 
                    COUNT(*) as total_projects,
                    SUM(p.probability) as total_probability,
                    AVG(p.probability) as avg_probability,
                    ps.name as stage_name,
                    COUNT(*) as stage_count
                FROM projects p
                LEFT JOIN project_stages ps ON p.project_stage_id = ps.id
                GROUP BY ps.name
            ";
            $stats_stmt = $pdo->query($stats_sql);
            $stage_stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
            
        }
    } catch (PDOException $e) {
        error_log("Error getting report data: " . $e->getMessage());
    }
}


try {
    $reports_sql = "SELECT r.*, u.full_name as creator_name FROM reports r LEFT JOIN users u ON r.created_by = u.id";
    
    
    if ($user_role !== 'admin' && $user_role !== 'analyst') {
        $reports_sql .= " WHERE r.is_public = 1";
    }
    
    $reports_sql .= " ORDER BY r.created_at DESC";
    
    $reports_stmt = $pdo->query($reports_sql);
    $reports = $reports_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error getting reports list: " . $e->getMessage());
    $reports = [];
}
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
            max-width: 1400px;
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

        
        .reports-sidebar {
            width: 320px;
            float: left;
            margin-right: 35px;
        }

        .reports-list {
            background: var(--bg-white);
            border: 2px solid var(--border-light);
            border-radius: 15px;
            max-height: 600px;
            overflow-y: auto;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .report-item {
            padding: 20px;
            border-bottom: 1px solid var(--border-light);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .report-item:hover {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            transform: translateX(5px);
        }

        .report-item.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-left: 4px solid var(--secondary);
        }

        .report-item h3 {
            margin: 0 0 8px 0;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .report-meta {
            font-size: 0.9rem;
            color: #666;
        }

        .report-item.active .report-meta {
            color: rgba(255, 255, 255, 0.8);
        }

        .report-content {
            margin-left: 355px;
        }

        
        .report-header {
            margin-bottom: 35px;
            padding-bottom: 25px;
            border-bottom: 2px solid var(--border-light);
        }

        .report-header h1 {
            color: var(--primary);
            margin-bottom: 15px;
            font-size: 2.2rem;
            font-weight: 700;
        }

        .report-meta-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }

        .meta-item {
            padding: 18px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 12px;
            border-left: 4px solid var(--primary);
        }

        .meta-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.95rem;
            margin-bottom: 8px;
        }

        .meta-value {
            margin-top: 8px;
            font-weight: 500;
        }

        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: var(--bg-white);
            padding: 25px;
            border-radius: 15px;
            border-left: 5px solid var(--primary);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
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
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 15px 0;
            line-height: 1;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 1rem;
            font-weight: 500;
        }

        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
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
            font-weight: 600;
            font-size: 0.95rem;
        }

        .data-table tr:hover {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        }

        
        .charts-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 35px;
            margin: 35px 0;
        }

        .chart-card {
            background: var(--bg-white);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid var(--border-light);
        }

        .chart-card h3 {
            margin-bottom: 20px;
            color: var(--primary);
            font-weight: 600;
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

        .btn-danger {
            background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.3);
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
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

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
            font-style: italic;
            font-size: 1.1rem;
        }

    
        @media (max-width: 1024px) {
            .reports-sidebar {
                width: 100%;
                float: none;
                margin-right: 0;
                margin-bottom: 25px;
            }
            
            .report-content {
                margin-left: 0;
            }
            
            .charts-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .header {
                padding: 20px;
            }
            
            .content-container {
                padding: 25px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .report-meta-info {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .data-table {
                font-size: 0.85rem;
            }
            
            .data-table th,
            .data-table td {
                padding: 12px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chart-bar"></i> Ростелеком - Аналитика и отчеты</h1>
        </div>

        <div class="breadcrumb">
            <a href="index.php"><i class="fas fa-home"></i> Дашборд</a> &raquo; 
            <a href="reports.php"><i class="fas fa-chart-pie"></i> Отчеты</a> &raquo; 
            <span><i class="fas fa-eye"></i> Просмотр отчета</span>
        </div>
        
        <div class="content-container">
            <div class="reports-sidebar">
                <h2 style="color: var(--rtk-blue); margin-bottom: 15px;">Доступные отчеты</h2>
                <div class="reports-list">
                    <?php if (empty($reports)): ?>
                        <div class="no-data">Нет доступных отчетов</div>
                    <?php else: ?>
                        <?php foreach ($reports as $rep): ?>
                            <div class="report-item <?php echo $report_id == $rep['id'] ? 'active' : ''; ?>" 
                                 onclick="window.location.href='view_report.php?id=<?php echo $rep['id']; ?>'">
                                <h3><?php echo htmlspecialchars($rep['title']); ?></h3>
                                <div class="report-meta">
                                    <span class="badge badge-<?php echo $rep['type']; ?>">
                                        <?php 
                                        $type_names = [
                                            'financial' => 'Финансовый',
                                            'operational' => 'Операционный',
                                            'analytical' => 'Аналитический'
                                        ];
                                        echo $type_names[$rep['type']] ?? $rep['type'];
                                        ?>
                                    </span>
                                    <br>
                                    Создатель: <?php echo htmlspecialchars($rep['creator_name']); ?><br>
                                    <?php echo date('d.m.Y', strtotime($rep['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($user_role === 'admin' || $user_role === 'analyst'): ?>
                    <div style="margin-top: 20px;">
                        <a href="create_report.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> Создать отчет
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="report-content">
                <?php if ($report): ?>
                    <div class="report-header">
                        <h1><?php echo htmlspecialchars($report['title']); ?></h1>
                        <p><?php echo htmlspecialchars($report['description']); ?></p>
                        
                        <div class="report-meta-info">
                            <div class="meta-item">
                                <div class="meta-label">Тип отчета</div>
                                <div class="meta-value">
                                    <span class="badge badge-<?php echo $report['type']; ?>">
                                        <?php echo $type_names[$report['type']] ?? $report['type']; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Создатель</div>
                                <div class="meta-value"><?php echo htmlspecialchars($report['creator_name']); ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Дата создания</div>
                                <div class="meta-value"><?php echo date('d.m.Y H:i', strtotime($report['created_at'])); ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Статус</div>
                                <div class="meta-value">
                                    <?php echo $report['is_public'] ? 'Публичный' : 'Приватный'; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-label">Всего проектов</div>
                            <div class="stat-value"><?php echo count($report_data); ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Средняя вероятность</div>
                            <div class="stat-value">
                                <?php 
                                $avg_prob = 0;
                                if (!empty($report_data)) {
                                    $total_prob = 0;
                                    foreach ($report_data as $item) {
                                        $total_prob += floatval($item['probability']);
                                    }
                                    $avg_prob = round($total_prob / count($report_data), 1);
                                }
                                echo $avg_prob . '%';
                                ?>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Уникальных сегментов</div>
                            <div class="stat-value">
                                <?php
                                $segments = array_unique(array_column($report_data, 'segment_name'));
                                echo count(array_filter($segments));
                                ?>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Активных менеджеров</div>
                            <div class="stat-value">
                                <?php
                                $managers = array_unique(array_column($report_data, 'manager_name'));
                                echo count(array_filter($managers));
                                ?>
                            </div>
                        </div>
                    </div>

                    
                    <div class="charts-container">
                        <div class="chart-card">
                            <h3>Распределение по этапам</h3>
                            <canvas id="stagesChart" height="250"></canvas>
                        </div>
                        <div class="chart-card">
                            <h3>Распределение по сегментам</h3>
                            <canvas id="segmentsChart" height="250"></canvas>
                        </div>
                    </div>

                    
                    <h2 style="color: var(--rtk-blue); margin: 30px 0 15px 0;">Данные отчета</h2>
                    
                    <?php if (empty($report_data)): ?>
                        <div class="no-data">Нет данных, соответствующих критериям отчета</div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Название проекта</th>
                                        <th>Клиент</th>
                                        <th>ИНН</th>
                                        <th>Услуга</th>
                                        <th>Этап</th>
                                        <th>Вероятность</th>
                                        <th>Менеджер</th>
                                        <th>Сегмент</th>
                                        <th>Год реализации</th>
                                        <th>Дата создания</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['project_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['client_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['client_inn']); ?></td>
                                            <td><?php echo htmlspecialchars($row['service_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['project_stage']); ?></td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <div style="width: 40px; height: 8px; background: #e0e0e0; border-radius: 4px;">
                                                        <div style="width: <?php echo $row['probability']; ?>%; height: 100%; background: var(--rtk-blue); border-radius: 4px;"></div>
                                                    </div>
                                                    <?php echo $row['probability']; ?>%
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['manager_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['segment_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['implementation_year']); ?></td>
                                            <td><?php echo date('d.m.Y', strtotime($row['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <div class="action-buttons">
    <button class="btn" onclick="window.print()">
        <i class="fas fa-print"></i> Печать
    </button>
    <a href="export_report.php?id=<?php echo $report_id; ?>&format=excel" class="btn btn-success">
        <i class="fas fa-file-excel"></i> Excel
    </a>
    <a href="export_report.php?id=<?php echo $report_id; ?>&format=pdf" class="btn btn-danger">
        <i class="fas fa-file-pdf"></i> PDF
    </a>
</div>

                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px;">
                        <i class="fas fa-chart-bar" style="font-size: 4rem; color: #ddd; margin-bottom: 20px;"></i>
                        <h2 style="color: var(--rtk-dark-gray); margin-bottom: 15px;">Выберите отчет для просмотра</h2>
                        <p style="color: #666;">Выберите отчет из списка слева для просмотра детальной информации</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="clear: both;"></div>
        </div>
    </div>
    <!--?php var_dump($report); echo '<HR><HR>'; var_dump($report_data); ?-->
    <script src="chart.js"></script>
    <?php if ($report): ?>
    <script>
        const stageData = {
            labels: [<?php 
                $stage_labels = [];
                $stage_counts = [];
                foreach ($stage_stats as $stat) {
                    $stage_labels[] = "'" . addslashes($stat['stage_name']) . "'";
                    $stage_counts[] = $stat['stage_count'];
                }
                echo implode(', ', $stage_labels);
            ?>],
            datasets: [{
                data: [<?php echo implode(', ', $stage_counts); ?>],
                backgroundColor: [
                    '#0033a0', '#00a0e3', '#4caf50', '#ff9800', '#e91e63',
                    '#9c27b0', '#673ab7', '#3f51b5', '#2196f3', '#03a9f4'
                ],
                borderWidth: 1
            }]
        };

        

        
        // document.addEventListener('DOMContentLoaded', function() {
            
            const stagesCtx = document.getElementById('stagesChart');
            console.log(stagesCtx);
            new Chart(stagesCtx, {
                type: 'pie',
                data: stageData,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            
        // });
    </script>
    <?php endif; ?>
    <?php if ($report && !empty($report_data)): ?>
    <script>
        const segmentData = {
            labels: [<?php
                $segment_counts = [];
                foreach ($report_data as $row) {
                    $segment = $row['segment_name'] ?: 'Не указан';
                    $segment_counts[$segment] = ($segment_counts[$segment] ?? 0) + 1;
                }
                $segment_labels = [];
                $segment_values = [];
                foreach ($segment_counts as $segment => $count) {
                    $segment_labels[] = "'" . addslashes($segment) . "'";
                    $segment_values[] = $count;
                }
                echo implode(', ', $segment_labels);
            ?>],
            datasets: [{
                data: [<?php echo implode(', ', $segment_values); ?>],
                backgroundColor: [
                    '#0033a0', '#00a0e3', '#4caf50', '#ff9800', '#e91e63',
                    '#9c27b0', '#673ab7', '#3f51b5', '#2196f3', '#03a9f4'
                ],
                borderWidth: 1
            }]
        };

        const segmentsCtx = document.getElementById('segmentsChart');
        console.log(segmentsCtx);
        new Chart(segmentsCtx, {
            type: 'doughnut',
            data: segmentData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>