<?php
// export_report.php

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

$report_id = $_GET['id'] ?? null;
$format = $_GET['format'] ?? 'excel';

if (!$report_id) {
    die("Не указан ID отчета");
}

// Получаем данные отчета
try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name as creator_name 
        FROM reports r 
        LEFT JOIN users u ON r.created_by = u.id 
        WHERE r.id = ?
    ");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка получения отчета: " . $e->getMessage());
}

if (!$report) {
    die("Отчет не найден");
}

// Получаем данные для отчета (аналогично view_report.php)
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

if ($format === 'excel') {
    // Генерация Excel файла
    exportToExcel($report, $report_data);
} elseif ($format === 'pdf') {
    // Здесь можно добавить генерацию PDF
    // Для простоты перенаправляем обратно
    header('Location: view_report.php?id=' . $report_id);
    exit;
}

function exportToExcel($report, $report_data) {
    // Устанавливаем заголовки для скачивания Excel файла
    $filename = "report_" . $report['id'] . "_" . date('Y-m-d') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Cache-Control: max-age=0");
    
    // Начинаем вывод Excel файла
    echo "<html>";
    echo "<head>";
    echo "<meta charset=\"UTF-8\">";
    echo "<style>";
    echo "table { border-collapse: collapse; width: 100%; }";
    echo "th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }";
    echo "th { background-color: #f2f2f2; font-weight: bold; }";
    echo ".header { background-color: #7800ff; color: white; padding: 10px; }";
    echo ".summary { background-color: #f8f9fa; padding: 10px; margin: 10px 0; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
    
    // Заголовок отчета
    echo "<div class=\"header\">";
    echo "<h1>" . htmlspecialchars($report['title']) . "</h1>";
    echo "<p>" . htmlspecialchars($report['description']) . "</p>";
    echo "</div>";
    
    // Мета-информация
    echo "<div class=\"summary\">";
    echo "<h3>Информация об отчете:</h3>";
    echo "<p><strong>Создатель:</strong> " . htmlspecialchars($report['creator_name']) . "</p>";
    echo "<p><strong>Дата создания:</strong> " . date('d.m.Y H:i', strtotime($report['created_at'])) . "</p>";
    echo "<p><strong>Всего записей:</strong> " . count($report_data) . "</p>";
    
    // Статистика
    $avg_prob = 0;
    if (!empty($report_data)) {
        $total_prob = 0;
        foreach ($report_data as $item) {
            $total_prob += floatval($item['probability']);
        }
        $avg_prob = round($total_prob / count($report_data), 1);
    }
    echo "<p><strong>Средняя вероятность:</strong> " . $avg_prob . "%</p>";
    echo "</div>";
    
    // Таблица с данными
    echo "<table>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>ID</th>";
    echo "<th>Название проекта</th>";
    echo "<th>Клиент</th>";
    echo "<th>ИНН</th>";
    echo "<th>Услуга</th>";
    echo "<th>Тип оплаты</th>";
    echo "<th>Этап проекта</th>";
    echo "<th>Вероятность (%)</th>";
    echo "<th>Менеджер</th>";
    echo "<th>Сегмент</th>";
    echo "<th>Год реализации</th>";
    echo "<th>Отраслевое решение</th>";
    echo "<th>Прогноз принят</th>";
    echo "<th>Текущий статус</th>";
    echo "<th>Дата создания</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    if (empty($report_data)) {
        echo "<tr><td colspan=\"15\" style=\"text-align: center;\">Нет данных</td></tr>";
    } else {
        foreach ($report_data as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['project_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['client_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['client_inn']) . "</td>";
            echo "<td>" . htmlspecialchars($row['service_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['payment_type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['project_stage']) . "</td>";
            echo "<td>" . htmlspecialchars($row['probability']) . "%</td>";
            echo "<td>" . htmlspecialchars($row['manager_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['segment_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['implementation_year']) . "</td>";
            echo "<td>" . ($row['is_industry_solution'] ? 'Да' : 'Нет') . "</td>";
            echo "<td>" . ($row['is_forecast_accepted'] ? 'Да' : 'Нет') . "</td>";
            echo "<td>" . htmlspecialchars($row['current_status']) . "</td>";
            echo "<td>" . date('d.m.Y', strtotime($row['created_at'])) . "</td>";
            echo "</tr>";
        }
    }
    
    echo "</tbody>";
    echo "</table>";
    
    // Статистика по этапам
    if (!empty($report_data)) {
        echo "<div style=\"margin-top: 20px;\">";
        echo "<h3>Статистика по этапам:</h3>";
        
        $stage_stats = [];
        foreach ($report_data as $row) {
            $stage = $row['project_stage'] ?: 'Не указан';
            if (!isset($stage_stats[$stage])) {
                $stage_stats[$stage] = 0;
            }
            $stage_stats[$stage]++;
        }
        
        echo "<table>";
        echo "<thead><tr><th>Этап</th><th>Количество</th><th>Доля</th></tr></thead>";
        echo "<tbody>";
        foreach ($stage_stats as $stage => $count) {
            $percentage = round(($count / count($report_data)) * 100, 1);
            echo "<tr>";
            echo "<td>" . htmlspecialchars($stage) . "</td>";
            echo "<td>" . $count . "</td>";
            echo "<td>" . $percentage . "%</td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
        echo "</div>";
    }
    
    echo "</body>";
    echo "</html>";
    exit;
}
?>