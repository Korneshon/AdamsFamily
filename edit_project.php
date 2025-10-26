<?php

session_start();


$host = 'MySQL-8.4';
$dbname = 'project_management';
$username = 'root';
$password = '';

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

// Получение информации о текущем пользователе для проверки прав
try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_role = $current_user['role'] ?? 'user';
} catch (PDOException $e) {
    error_log("Error getting user role: " . $e->getMessage());
    $user_role = 'user';
}

// Проверка прав доступа (пользователь может редактировать только свои проекты, если он не админ/аналитик)
$can_edit = in_array($user_role, ['admin', 'analyst']) || $project['created_by'] == $_SESSION['user_id'];

if (!$can_edit) {
    $_SESSION['error'] = 'У вас нет прав для редактирования этого проекта';
    header('Location: view_project.php?id=' . $project_id);
    exit;
}

// Получение данных для справочников
try {
    $services = $pdo->query("SELECT id, name FROM services ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $payment_types = $pdo->query("SELECT id, name FROM payment_types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $project_stages = $pdo->query("SELECT id, name, probability FROM project_stages ORDER BY probability")->fetchAll(PDO::FETCH_ASSOC);
    $segments = $pdo->query("SELECT id, name FROM segments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $users = $pdo->query("SELECT id, username, full_name FROM users ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
    $revenue_statuses = $pdo->query("SELECT id, name FROM revenue_statuses ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $cost_types = $pdo->query("SELECT id, name FROM cost_types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $cost_statuses = $pdo->query("SELECT id, name FROM cost_statuses ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error getting reference data: " . $e->getMessage());
    $services = $payment_types = $project_stages = $segments = $users = $revenue_statuses = $cost_types = $cost_statuses = [];
}

// Получение выручки проекта
try {
    $stmt = $pdo->prepare("
        SELECT * FROM project_revenue 
        WHERE project_id = ? 
        ORDER BY year DESC, month DESC
    ");
    $stmt->execute([$project_id]);
    $revenues = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error getting project revenues: " . $e->getMessage());
    $revenues = [];
}

// Получение затрат проекта
try {
    $stmt = $pdo->prepare("
        SELECT * FROM project_costs 
        WHERE project_id = ? 
        ORDER BY year DESC, month DESC
    ");
    $stmt->execute([$project_id]);
    $costs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error getting project costs: " . $e->getMessage());
    $costs = [];
}

$error = '';
$success = '';

// Обработка формы обновления проекта
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Собираем изменения для истории
        $changes = [];
        
        // Основные данные проекта
        $new_project_data = [
            ':client_name' => trim($_POST['client_name'] ?? ''),
            ':client_inn' => trim($_POST['client_inn'] ?? ''),
            ':project_name' => trim($_POST['project_name'] ?? ''),
            ':service_id' => !empty($_POST['service_id']) ? $_POST['service_id'] : null,
            ':payment_type_id' => !empty($_POST['payment_type_id']) ? $_POST['payment_type_id'] : null,
            ':project_stage_id' => !empty($_POST['project_stage_id']) ? $_POST['project_stage_id'] : null,
            ':manager_id' => !empty($_POST['manager_id']) ? $_POST['manager_id'] : null,
            ':segment_id' => !empty($_POST['segment_id']) ? $_POST['segment_id'] : null,
            ':implementation_year' => !empty($_POST['implementation_year']) ? $_POST['implementation_year'] : null,
            ':is_industry_solution' => isset($_POST['is_industry_solution']) ? 1 : 0,
            ':is_forecast_accepted' => isset($_POST['is_forecast_accepted']) ? 1 : 0,
            ':is_dzo_implementation' => isset($_POST['is_dzo_implementation']) ? 1 : 0,
            ':requires_management_control' => isset($_POST['requires_management_control']) ? 1 : 0,
            ':evaluation_status' => $_POST['evaluation_status'] ?? null,
            ':industry_manager' => trim($_POST['industry_manager'] ?? ''),
            ':project_number' => trim($_POST['project_number'] ?? ''),
            ':current_status' => trim($_POST['current_status'] ?? ''),
            ':completed_in_period' => trim($_POST['completed_in_period'] ?? ''),
            ':plans_for_next_period' => trim($_POST['plans_for_next_period'] ?? ''),
            ':id' => $project_id
        ];

        // Определяем вероятность на основе этапа
        $stage_probability = 0;
        foreach ($project_stages as $stage) {
            if ($stage['id'] == $new_project_data[':project_stage_id']) {
                $stage_probability = $stage['probability'];
                break;
            }
        }
        $new_project_data[':probability'] = $stage_probability;

        // Сравниваем старые и новые значения для истории
        $fields_to_check = [
            'client_name', 'client_inn', 'project_name', 'service_id', 'payment_type_id',
            'project_stage_id', 'manager_id', 'segment_id', 'implementation_year',
            'is_industry_solution', 'is_forecast_accepted', 'is_dzo_implementation',
            'requires_management_control', 'evaluation_status', 'industry_manager',
            'project_number', 'current_status', 'completed_in_period', 'plans_for_next_period'
        ];

        foreach ($fields_to_check as $field) {
            $old_value = $project[$field];
            $new_value = $new_project_data[":$field"] ?? null;
            
            // Для внешних ключей получаем текстовые представления
            if ($field == 'service_id' && $old_value != $new_value) {
                $old_text = $pdo->prepare("SELECT name FROM services WHERE id = ?")->execute([$old_value])->fetchColumn() ?: $old_value;
                $new_text = $pdo->prepare("SELECT name FROM services WHERE id = ?")->execute([$new_value])->fetchColumn() ?: $new_value;
                $changes[] = ['field' => 'service', 'old' => $old_text, 'new' => $new_text];
            }
            elseif ($field == 'payment_type_id' && $old_value != $new_value) {
                $old_text = $pdo->prepare("SELECT name FROM payment_types WHERE id = ?")->execute([$old_value])->fetchColumn() ?: $old_value;
                $new_text = $pdo->prepare("SELECT name FROM payment_types WHERE id = ?")->execute([$new_value])->fetchColumn() ?: $new_value;
                $changes[] = ['field' => 'payment_type', 'old' => $old_text, 'new' => $new_text];
            }
            elseif ($field == 'project_stage_id' && $old_value != $new_value) {
                $old_text = $pdo->prepare("SELECT name FROM project_stages WHERE id = ?")->execute([$old_value])->fetchColumn() ?: $old_value;
                $new_text = $pdo->prepare("SELECT name FROM project_stages WHERE id = ?")->execute([$new_value])->fetchColumn() ?: $new_value;
                $changes[] = ['field' => 'project_stage', 'old' => $old_text, 'new' => $new_text];
            }
            elseif ($field == 'manager_id' && $old_value != $new_value) {
                $old_text = $pdo->prepare("SELECT full_name FROM users WHERE id = ?")->execute([$old_value])->fetchColumn() ?: $old_value;
                $new_text = $pdo->prepare("SELECT full_name FROM users WHERE id = ?")->execute([$new_value])->fetchColumn() ?: $new_value;
                $changes[] = ['field' => 'manager', 'old' => $old_text, 'new' => $new_text];
            }
            elseif ($field == 'segment_id' && $old_value != $new_value) {
                $old_text = $pdo->prepare("SELECT name FROM segments WHERE id = ?")->execute([$old_value])->fetchColumn() ?: $old_value;
                $new_text = $pdo->prepare("SELECT name FROM segments WHERE id = ?")->execute([$new_value])->fetchColumn() ?: $new_value;
                $changes[] = ['field' => 'segment', 'old' => $old_text, 'new' => $new_text];
            }
            elseif ($old_value != $new_value) {
                $changes[] = ['field' => $field, 'old' => $old_value, 'new' => $new_value];
            }
        }

        // SQL для обновления проекта
        $sql = "UPDATE projects SET
            client_name = :client_name,
            client_inn = :client_inn,
            project_name = :project_name,
            service_id = :service_id,
            payment_type_id = :payment_type_id,
            project_stage_id = :project_stage_id,
            probability = :probability,
            manager_id = :manager_id,
            segment_id = :segment_id,
            implementation_year = :implementation_year,
            is_industry_solution = :is_industry_solution,
            is_forecast_accepted = :is_forecast_accepted,
            is_dzo_implementation = :is_dzo_implementation,
            requires_management_control = :requires_management_control,
            evaluation_status = :evaluation_status,
            industry_manager = :industry_manager,
            project_number = :project_number,
            current_status = :current_status,
            completed_in_period = :completed_in_period,
            plans_for_next_period = :plans_for_next_period,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($new_project_data);

        // Удаляем старую выручку и добавляем новую
        $delete_revenues = $pdo->prepare("DELETE FROM project_revenue WHERE project_id = ?");
        $delete_revenues->execute([$project_id]);

        if (isset($_POST['revenue_year']) && is_array($_POST['revenue_year'])) {
            $revenue_sql = "INSERT INTO project_revenue (project_id, year, month, amount, revenue_status_id) VALUES (?, ?, ?, ?, ?)";
            $revenue_stmt = $pdo->prepare($revenue_sql);
            
            foreach ($_POST['revenue_year'] as $index => $year) {
                if (!empty($year) && !empty($_POST['revenue_amount'][$index])) {
                    $revenue_stmt->execute([
                        $project_id,
                        $year,
                        $_POST['revenue_month'][$index] ?? null,
                        $_POST['revenue_amount'][$index],
                        $_POST['revenue_status_id'][$index] ?? null
                    ]);
                }
            }
        }

        // Удаляем старые затраты и добавляем новые
        $delete_costs = $pdo->prepare("DELETE FROM project_costs WHERE project_id = ?");
        $delete_costs->execute([$project_id]);

        if (isset($_POST['cost_year']) && is_array($_POST['cost_year'])) {
            $cost_sql = "INSERT INTO project_costs (project_id, year, month, amount, cost_type_id, cost_status_id) VALUES (?, ?, ?, ?, ?, ?)";
            $cost_stmt = $pdo->prepare($cost_sql);
            
            foreach ($_POST['cost_year'] as $index => $year) {
                if (!empty($year) && !empty($_POST['cost_amount'][$index])) {
                    $cost_stmt->execute([
                        $project_id,
                        $year,
                        $_POST['cost_month'][$index] ?? null,
                        $_POST['cost_amount'][$index],
                        $_POST['cost_type_id'][$index] ?? null,
                        $_POST['cost_status_id'][$index] ?? null
                    ]);
                }
            }
        }

        // Записываем изменения в историю
        foreach ($changes as $change) {
            $history_sql = "INSERT INTO project_history (project_id, changed_field, old_value, new_value, changed_by) VALUES (?, ?, ?, ?, ?)";
            $history_stmt = $pdo->prepare($history_sql);
            $history_stmt->execute([
                $project_id,
                $change['field'],
                $change['old'],
                $change['new'],
                $_SESSION['user_id']
            ]);
        }

        $pdo->commit();
        $success = 'Проект успешно обновлен!';
        
        // Обновляем данные проекта после сохранения
        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                s.name as service_name,
                pt.name as payment_type_name,
                ps.name as project_stage_name,
                u_manager.full_name as manager_name,
                seg.name as segment_name
            FROM projects p
            LEFT JOIN services s ON p.service_id = s.id
            LEFT JOIN payment_types pt ON p.payment_type_id = pt.id
            LEFT JOIN project_stages ps ON p.project_stage_id = ps.id
            LEFT JOIN users u_manager ON p.manager_id = u_manager.id
            LEFT JOIN segments seg ON p.segment_id = seg.id
            WHERE p.id = ?
        ");
        $stmt->execute([$project_id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при обновлении проекта: ' . $e->getMessage();
        error_log("Project update error: " . $e->getMessage());
    }
}

// Если форма не отправлена, используем данные из БД
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    $_POST = [
        'client_name' => $project['client_name'],
        'client_inn' => $project['client_inn'],
        'project_name' => $project['project_name'],
        'service_id' => $project['service_id'],
        'payment_type_id' => $project['payment_type_id'],
        'project_stage_id' => $project['project_stage_id'],
        'manager_id' => $project['manager_id'],
        'segment_id' => $project['segment_id'],
        'implementation_year' => $project['implementation_year'],
        'is_industry_solution' => $project['is_industry_solution'],
        'is_forecast_accepted' => $project['is_forecast_accepted'],
        'is_dzo_implementation' => $project['is_dzo_implementation'],
        'requires_management_control' => $project['requires_management_control'],
        'evaluation_status' => $project['evaluation_status'],
        'industry_manager' => $project['industry_manager'],
        'project_number' => $project['project_number'],
        'current_status' => $project['current_status'],
        'completed_in_period' => $project['completed_in_period'],
        'plans_for_next_period' => $project['plans_for_next_period']
    ];
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование проекта - Ростелеком</title>
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
        max-width: 1200px;
        margin: 0 auto;
    }

    /* Анимированный заголовок */
    .header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        padding: 30px;
        border-radius: 20px 20px 0 0;
        box-shadow: 0 20px 40px rgba(120, 0, 255, 0.15);
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
        font-size: 2rem;
        font-weight: 700;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        gap: 15px;
    }

    /* Хлебные крошки */
    .breadcrumb {
        background: var(--bg-white);
        padding: 20px 30px;
        border-bottom: 1px solid var(--border-light);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }

    .breadcrumb a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .breadcrumb a:hover {
        color: var(--primary-dark);
        transform: translateY(-1px);
    }

    /* Основной контейнер формы */
    .form-container {
        background: var(--bg-white);
        padding: 40px;
        border-radius: 0 0 20px 20px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        border: 1px solid var(--border-light);
        animation: fadeInUp 0.6s ease-out;
    }

    /* Заголовок проекта */
    .project-info-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 25px;
        border-bottom: 2px solid var(--border-light);
    }

    .project-info-header h1 {
        color: var(--text-dark);
        font-size: 1.8rem;
        font-weight: 700;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .probability-display {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        padding: 12px 20px;
        border-radius: 25px;
        font-weight: 700;
        font-size: 1.1rem;
        box-shadow: 0 8px 20px rgba(120, 0, 255, 0.3);
        transition: all 0.3s ease;
    }

    .probability-display:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 25px rgba(120, 0, 255, 0.4);
    }

    /* Секции формы */
    .form-section {
        margin-bottom: 40px;
        padding: 30px;
        border: 1px solid var(--border-light);
        border-radius: 15px;
        background: var(--bg-white);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .form-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 5px;
        height: 100%;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    }

    .form-section:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(120, 0, 255, 0.1);
    }

    .form-section h2 {
        color: var(--primary);
        margin-bottom: 25px;
        font-size: 1.4rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    /* Группы формы */
    .form-group {
        margin-bottom: 25px;
        position: relative;
    }

    .form-group label {
        display: block;
        margin-bottom: 10px;
        color: var(--text-dark);
        font-weight: 600;
        font-size: 1rem;
    }

    .form-control {
        width: 100%;
        padding: 15px 20px;
        border: 2px solid var(--border-light);
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: var(--bg-white);
        color: var(--text-dark);
    }

    .form-control:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 4px rgba(120, 0, 255, 0.1);
        transform: translateY(-2px);
    }

    textarea.form-control {
        min-height: 120px;
        resize: vertical;
        line-height: 1.6;
    }

    /* Сетка формы */
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
    }

    .form-row-3 {
        grid-template-columns: 1fr 1fr 1fr;
    }

    /* Чекбоксы */
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 15px;
        padding: 12px 15px;
        background: var(--bg-gray);
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .checkbox-group:hover {
        background: var(--border-light);
        transform: translateX(5px);
    }

    .checkbox-group input[type="checkbox"] {
        width: 20px;
        height: 20px;
        border-radius: 6px;
        border: 2px solid var(--border-light);
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .checkbox-group input[type="checkbox"]:checked {
        background: var(--primary);
        border-color: var(--primary);
    }

    .checkbox-group label {
        margin: 0;
        font-weight: 500;
        color: var(--text-dark);
        cursor: pointer;
    }

    /* Кнопки */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 15px 30px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        box-shadow: 0 8px 20px rgba(120, 0, 255, 0.3);
    }

    .btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 25px rgba(120, 0, 255, 0.4);
        background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
    }

    .btn-success {
        background: linear-gradient(135deg, #fe4e12 0%, #fe4e12 100%);
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
    }

    .btn-success:hover {
        background: linear-gradient(135deg, #fd7f56ff 0%, #fe4e12 100%);
        box-shadow: 0 12px 25px rgba(16, 185, 129, 0.4);
    }

    .btn-danger {
        background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
    }

    .btn-danger:hover {
        background: linear-gradient(135deg, #f87171 0%, var(--danger) 100%);
        box-shadow: 0 12px 25px rgba(239, 68, 68, 0.4);
    }

    .btn-sm {
        padding: 10px 20px;
        font-size: 0.9rem;
    }

    /* Сообщения */
    .error-message {
        color: var(--danger);
        padding: 20px;
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        border-radius: 12px;
        margin-bottom: 25px;
        border-left: 5px solid var(--danger);
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 600;
        box-shadow: 0 5px 15px rgba(239, 68, 68, 0.1);
    }

    .success-message {
        color: var(--success);
        padding: 20px;
        background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        border-radius: 12px;
        margin-bottom: 25px;
        border-left: 5px solid var(--success);
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 600;
        box-shadow: 0 5px 15px rgba(16, 185, 129, 0.1);
    }

    /* Обязательные поля */
    .required::after {
        content: " *";
        color: var(--danger);
        font-weight: 700;
    }

    /* Счетчик символов */
    .char-count {
        font-size: 0.85rem;
        color: var(--text-light);
        margin-top: 8px;
        font-weight: 500;
    }

    /* Динамические секции */
    .dynamic-section {
        background: var(--bg-gray);
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 20px;
        border-left: 4px solid var(--primary);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .dynamic-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(120, 0, 255, 0.05), transparent);
        transition: left 0.6s ease;
    }

    .dynamic-section:hover::before {
        left: 100%;
    }

    .dynamic-section:hover {
        transform: translateX(8px);
        box-shadow: 0 8px 20px rgba(120, 0, 255, 0.1);
    }

    /* Заголовки секций с кнопками */
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid var(--border-light);
    }

    /* Анимации */
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

    /* Адаптивность */
    @media (max-width: 768px) {
        body {
            padding: 15px;
        }
        
        .header {
            padding: 20px;
        }
        
        .header h1 {
            font-size: 1.5rem;
        }
        
        .form-container {
            padding: 25px;
        }
        
        .project-info-header {
            flex-direction: column;
            gap: 20px;
            text-align: center;
        }
        
        .form-row, .form-row-3 {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .section-header {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }
        
        .form-section {
            padding: 20px;
        }
    }

    /* Стили для select */
    select.form-control {
        appearance: none;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%237800ff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 15px center;
        background-size: 16px;
        padding-right: 45px;
    }

    /* Группа кнопок внизу формы */
    .form-actions {
        display: flex;
        gap: 15px;
        margin-top: 40px;
        padding-top: 25px;
        border-top: 2px solid var(--border-light);
        flex-wrap: wrap;
    }

    /* Специфичные стили для edit_project.php */
    .revenue-section, .cost-section {
        border-left-color: var(--accent);
    }

    .management-control {
        background: linear-gradient(135deg, #fef3c7, #fef3c7);
        border-left-color: var(--warning);
    }

    .industry-solution {
        background: linear-gradient(135deg, #dbeafe, #dbeafe);
        border-left-color: var(--primary);
    }
</style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-edit"></i> Ростелеком - Редактирование проекта</h1>
        </div>

        <div class="breadcrumb">
            <a href="index.php"><i class="fas fa-home"></i> Дашборд</a> &raquo; 
            <a href="projects.php"><i class="fas fa-list"></i> Проекты</a> &raquo; 
            <a href="view_project.php?id=<?php echo $project_id; ?>"><i class="fas fa-eye"></i> Просмотр проекта</a> &raquo; 
            <span><i class="fas fa-edit"></i> Редактирование</span>
        </div>
        
        <div class="form-container">
            <div class="project-info-header">
                <h1>Редактирование проекта: <?php echo htmlspecialchars($project['project_name']); ?></h1>
                <div class="probability-display">
                    Текущая вероятность: <?php echo $project['probability']; ?>%
                </div>
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
            
            <form method="POST" action="">
                <!-- Общая информация -->
                <div class="form-section">
                    <h2><i class="fas fa-info-circle"></i> Общая информация по проекту</h2>
                    
                    <div class="form-group">
                        <label for="client_name" class="required">Название организации</label>
                        <input type="text" id="client_name" name="client_name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['client_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="client_inn" class="required">ИНН организации</label>
                        <input type="text" id="client_inn" name="client_inn" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['client_inn'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="project_name" class="required">Название проекта</label>
                        <input type="text" id="project_name" name="project_name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['project_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="service_id">Услуга</label>
                            <select id="service_id" name="service_id" class="form-control">
                                <option value="">-- Выберите услугу --</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?php echo $service['id']; ?>" 
                                        <?php echo ($_POST['service_id'] ?? '') == $service['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($service['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="payment_type_id">Тип платежа</label>
                            <select id="payment_type_id" name="payment_type_id" class="form-control">
                                <option value="">-- Выберите тип платежа --</option>
                                <?php foreach ($payment_types as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" 
                                        <?php echo ($_POST['payment_type_id'] ?? '') == $type['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="project_stage_id">Этап проекта</label>
                            <select id="project_stage_id" name="project_stage_id" class="form-control">
                                <option value="">-- Выберите этап --</option>
                                <?php foreach ($project_stages as $stage): ?>
                                    <option value="<?php echo $stage['id']; ?>" 
                                        <?php echo ($_POST['project_stage_id'] ?? '') == $stage['id'] ? 'selected' : ''; ?>
                                        data-probability="<?php echo $stage['probability']; ?>">
                                        <?php echo htmlspecialchars($stage['name']); ?> (<?php echo $stage['probability']; ?>%)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="manager_id">Менеджер</label>
                            <select id="manager_id" name="manager_id" class="form-control">
                                <option value="">-- Выберите менеджера --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" 
                                        <?php echo ($_POST['manager_id'] ?? '') == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['username'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="segment_id">Сегмент бизнеса</label>
                            <select id="segment_id" name="segment_id" class="form-control">
                                <option value="">-- Выберите сегмент --</option>
                                <?php foreach ($segments as $segment): ?>
                                    <option value="<?php echo $segment['id']; ?>" 
                                        <?php echo ($_POST['segment_id'] ?? '') == $segment['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($segment['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="implementation_year">Год реализации</label>
                            <input type="number" id="implementation_year" name="implementation_year" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['implementation_year'] ?? date('Y')); ?>" 
                                   min="2000" max="2030">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_industry_solution" name="is_industry_solution" value="1"
                                <?php echo isset($_POST['is_industry_solution']) && $_POST['is_industry_solution'] ? 'checked' : ''; ?>>
                            <label for="is_industry_solution">Отраслевое решение</label>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="is_forecast_accepted" name="is_forecast_accepted" value="1"
                                <?php echo isset($_POST['is_forecast_accepted']) && $_POST['is_forecast_accepted'] ? 'checked' : ''; ?>>
                            <label for="is_forecast_accepted">Принимаемый к прогнозу</label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_dzo_implementation" name="is_dzo_implementation" value="1"
                                <?php echo isset($_POST['is_dzo_implementation']) && $_POST['is_dzo_implementation'] ? 'checked' : ''; ?>>
                            <label for="is_dzo_implementation">Реализация через ДЗО</label>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="requires_management_control" name="requires_management_control" value="1"
                                <?php echo isset($_POST['requires_management_control']) && $_POST['requires_management_control'] ? 'checked' : ''; ?>>
                            <label for="requires_management_control">Требуется контроль статуса на уровне руководства</label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="evaluation_status">Принимаемый к оценке</label>
                            <select id="evaluation_status" name="evaluation_status" class="form-control">
                                <option value="">-- Выберите статус --</option>
                                <option value="Одобрен" <?php echo ($_POST['evaluation_status'] ?? '') == 'Одобрен' ? 'selected' : ''; ?>>Одобрен</option>
                                <option value="На рассмотрении" <?php echo ($_POST['evaluation_status'] ?? '') == 'На рассмотрении' ? 'selected' : ''; ?>>На рассмотрении</option>
                                <option value="Отклонен" <?php echo ($_POST['evaluation_status'] ?? '') == 'Отклонен' ? 'selected' : ''; ?>>Отклонен</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="industry_manager">Отраслевой менеджер</label>
                            <input type="text" id="industry_manager" name="industry_manager" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['industry_manager'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="project_number">Номер проекта</label>
                        <input type="text" id="project_number" name="project_number" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['project_number'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Информация по выручке -->
                <div class="form-section">
                    <div class="section-header">
                        <h2><i class="fas fa-chart-line"></i> Информация по выручке проекта</h2>
                        <button type="button" class="btn btn-sm" onclick="addRevenueSection()">
                            <i class="fas fa-plus"></i> Добавить период
                        </button>
                    </div>
                    <div id="revenue-sections">
                        <?php if (empty($revenues)): ?>
                            <div class="dynamic-section revenue-section">
                                <div class="form-row form-row-3">
                                    <div class="form-group">
                                        <label>Год</label>
                                        <input type="number" name="revenue_year[]" class="form-control" min="2000" max="2030">
                                    </div>
                                    <div class="form-group">
                                        <label>Месяц</label>
                                        <select name="revenue_month[]" class="form-control">
                                            <option value="">-- Выберите месяц --</option>
                                            <?php 
                                            $months = [
                                                1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
                                                5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
                                                9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь'
                                            ];
                                            foreach ($months as $num => $name): ?>
                                                <option value="<?php echo $num; ?>"><?php echo $name; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Сумма (руб.)</label>
                                        <input type="number" name="revenue_amount[]" class="form-control" step="0.01">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Статус начисления выручки</label>
                                    <select name="revenue_status_id[]" class="form-control">
                                        <option value="">-- Выберите статус --</option>
                                        <?php foreach ($revenue_statuses as $status): ?>
                                            <option value="<?php echo $status['id']; ?>">
                                                <?php echo htmlspecialchars($status['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($revenues as $index => $revenue): ?>
                                <div class="dynamic-section revenue-section">
                                    <div class="form-row form-row-3">
                                        <div class="form-group">
                                            <label>Год</label>
                                            <input type="number" name="revenue_year[]" class="form-control" min="2000" max="2030"
                                                   value="<?php echo htmlspecialchars($revenue['year'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Месяц</label>
                                            <select name="revenue_month[]" class="form-control">
                                                <option value="">-- Выберите месяц --</option>
                                                <?php foreach ($months as $num => $name): ?>
                                                    <option value="<?php echo $num; ?>" 
                                                        <?php echo ($revenue['month'] ?? '') == $num ? 'selected' : ''; ?>>
                                                        <?php echo $name; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Сумма (руб.)</label>
                                            <input type="number" name="revenue_amount[]" class="form-control" step="0.01"
                                                   value="<?php echo htmlspecialchars($revenue['amount'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Статус начисления выручки</label>
                                        <select name="revenue_status_id[]" class="form-control">
                                            <option value="">-- Выберите статус --</option>
                                            <?php foreach ($revenue_statuses as $status): ?>
                                                <option value="<?php echo $status['id']; ?>" 
                                                    <?php echo ($revenue['revenue_status_id'] ?? '') == $status['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($status['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php if ($index > 0): ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">
                                            <i class="fas fa-trash"></i> Удалить
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Информация по затратам -->
                <div class="form-section">
                    <div class="section-header">
                        <h2><i class="fas fa-money-bill-wave"></i> Информация по затратам проекта</h2>
                        <button type="button" class="btn btn-sm" onclick="addCostSection()">
                            <i class="fas fa-plus"></i> Добавить период
                        </button>
                    </div>
                    <div id="cost-sections">
                        <?php if (empty($costs)): ?>
                            <div class="dynamic-section cost-section">
                                <div class="form-row form-row-3">
                                    <div class="form-group">
                                        <label>Год</label>
                                        <input type="number" name="cost_year[]" class="form-control" min="2000" max="2030">
                                    </div>
                                    <div class="form-group">
                                        <label>Месяц</label>
                                        <select name="cost_month[]" class="form-control">
                                            <option value="">-- Выберите месяц --</option>
                                            <?php foreach ($months as $num => $name): ?>
                                                <option value="<?php echo $num; ?>"><?php echo $name; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Сумма (руб.)</label>
                                        <input type="number" name="cost_amount[]" class="form-control" step="0.01">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Вид затрат</label>
                                        <select name="cost_type_id[]" class="form-control">
                                            <option value="">-- Выберите вид затрат --</option>
                                            <?php foreach ($cost_types as $type): ?>
                                                <option value="<?php echo $type['id']; ?>">
                                                    <?php echo htmlspecialchars($type['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Статус отражения затрат</label>
                                        <select name="cost_status_id[]" class="form-control">
                                            <option value="">-- Выберите статус --</option>
                                            <?php foreach ($cost_statuses as $status): ?>
                                                <option value="<?php echo $status['id']; ?>">
                                                    <?php echo htmlspecialchars($status['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($costs as $index => $cost): ?>
                                <div class="dynamic-section cost-section">
                                    <div class="form-row form-row-3">
                                        <div class="form-group">
                                            <label>Год</label>
                                            <input type="number" name="cost_year[]" class="form-control" min="2000" max="2030"
                                                   value="<?php echo htmlspecialchars($cost['year'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Месяц</label>
                                            <select name="cost_month[]" class="form-control">
                                                <option value="">-- Выберите месяц --</option>
                                                <?php foreach ($months as $num => $name): ?>
                                                    <option value="<?php echo $num; ?>" 
                                                        <?php echo ($cost['month'] ?? '') == $num ? 'selected' : ''; ?>>
                                                        <?php echo $name; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Сумма (руб.)</label>
                                            <input type="number" name="cost_amount[]" class="form-control" step="0.01"
                                                   value="<?php echo htmlspecialchars($cost['amount'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Вид затрат</label>
                                            <select name="cost_type_id[]" class="form-control">
                                                <option value="">-- Выберите вид затрат --</option>
                                                <?php foreach ($cost_types as $type): ?>
                                                    <option value="<?php echo $type['id']; ?>" 
                                                        <?php echo ($cost['cost_type_id'] ?? '') == $type['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($type['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Статус отражения затрат</label>
                                            <select name="cost_status_id[]" class="form-control">
                                                <option value="">-- Выберите статус --</option>
                                                <?php foreach ($cost_statuses as $status): ?>
                                                    <option value="<?php echo $status['id']; ?>" 
                                                        <?php echo ($cost['cost_status_id'] ?? '') == $status['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($status['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <?php if ($index > 0): ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">
                                            <i class="fas fa-trash"></i> Удалить
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Дополнительная информация -->
                <div class="form-section">
                    <h2><i class="fas fa-comments"></i> Дополнительная информация</h2>
                    
                    <div class="form-group">
                        <label for="current_status">Текущий статус по проекту</label>
                        <textarea id="current_status" name="current_status" class="form-control" maxlength="1000" 
                                  placeholder="Опишите текущее состояние проекта..."><?php 
                            echo htmlspecialchars($_POST['current_status'] ?? ''); 
                        ?></textarea>
                        <div class="char-count">Осталось символов: <span id="current_status_count">1000</span></div>
                    </div>

                    <div class="form-group">
                        <label for="completed_in_period">Что сделано за период</label>
                        <textarea id="completed_in_period" name="completed_in_period" class="form-control" maxlength="1000"
                                  placeholder="Опишите выполненные работы за отчетный период..."><?php 
                            echo htmlspecialchars($_POST['completed_in_period'] ?? ''); 
                        ?></textarea>
                        <div class="char-count">Осталось символов: <span id="completed_count">1000</span></div>
                    </div>

                    <div class="form-group">
                        <label for="plans_for_next_period">Планы на следующий период</label>
                        <textarea id="plans_for_next_period" name="plans_for_next_period" class="form-control" maxlength="1000"
                                  placeholder="Опишите планируемые работы на следующий период..."><?php 
                            echo htmlspecialchars($_POST['plans_for_next_period'] ?? ''); 
                        ?></textarea>
                        <div class="char-count">Осталось символов: <span id="plans_count">1000</span></div>
                    </div>
                </div>

                <div style="display: flex; gap: 15px; margin-top: 30px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Сохранить изменения
                    </button>
                    <a href="view_project.php?id=<?php echo $project_id; ?>" class="btn">
                        <i class="fas fa-times"></i> Отмена
                    </a>
                    <a href="projects.php" class="btn">
                        <i class="fas fa-arrow-left"></i> К списку проектов
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Добавление секции выручки
        function addRevenueSection() {
            const section = document.createElement('div');
            section.className = 'dynamic-section revenue-section';
            section.innerHTML = `
                <div class="form-row form-row-3">
                    <div class="form-group">
                        <label>Год</label>
                        <input type="number" name="revenue_year[]" class="form-control" min="2000" max="2030">
                    </div>
                    <div class="form-group">
                        <label>Месяц</label>
                        <select name="revenue_month[]" class="form-control">
                            <option value="">-- Выберите месяц --</option>
                            ${getMonthOptions()}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Сумма (руб.)</label>
                        <input type="number" name="revenue_amount[]" class="form-control" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label>Статус начисления выручки</label>
                    <select name="revenue_status_id[]" class="form-control">
                        <option value="">-- Выберите статус --</option>
                        <?php foreach ($revenue_statuses as $status): ?>
                            <option value="<?php echo $status['id']; ?>"><?php echo htmlspecialchars($status['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">
                    <i class="fas fa-trash"></i> Удалить
                </button>
            `;
            document.getElementById('revenue-sections').appendChild(section);
        }

        // Добавление секции затрат
        function addCostSection() {
            const section = document.createElement('div');
            section.className = 'dynamic-section cost-section';
            section.innerHTML = `
                <div class="form-row form-row-3">
                    <div class="form-group">
                        <label>Год</label>
                        <input type="number" name="cost_year[]" class="form-control" min="2000" max="2030">
                    </div>
                    <div class="form-group">
                        <label>Месяц</label>
                        <select name="cost_month[]" class="form-control">
                            <option value="">-- Выберите месяц --</option>
                            ${getMonthOptions()}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Сумма (руб.)</label>
                        <input type="number" name="cost_amount[]" class="form-control" step="0.01">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Вид затрат</label>
                        <select name="cost_type_id[]" class="form-control">
                            <option value="">-- Выберите вид затрат --</option>
                            <?php foreach ($cost_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Статус отражения затрат</label>
                        <select name="cost_status_id[]" class="form-control">
                            <option value="">-- Выберите статус --</option>
                            <?php foreach ($cost_statuses as $status): ?>
                                <option value="<?php echo $status['id']; ?>"><?php echo htmlspecialchars($status['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">
                    <i class="fas fa-trash"></i> Удалить
                </button>
            `;
            document.getElementById('cost-sections').appendChild(section);
        }

        // Генерация опций месяцев
        function getMonthOptions() {
            const months = [
                'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
            ];
            return months.map((month, index) => 
                `<option value="${index + 1}">${month}</option>`
            ).join('');
        }

        // Подсчет символов в текстовых полях
        function setupCharacterCount(textareaId, countId) {
            const textarea = document.getElementById(textareaId);
            const count = document.getElementById(countId);
            
            function updateCount() {
                const remaining = 1000 - textarea.value.length;
                count.textContent = remaining;
                if (remaining < 50) {
                    count.style.color = 'red';
                } else if (remaining < 100) {
                    count.style.color = 'orange';
                } else {
                    count.style.color = '#666';
                }
            }
            
            textarea.addEventListener('input', updateCount);
            updateCount(); // Инициализация при загрузке
        }

        // Обновление отображения вероятности при изменении этапа
        document.getElementById('project_stage_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const probability = selectedOption.getAttribute('data-probability');
            if (probability) {
                document.querySelector('.probability-display').textContent = `Текущая вероятность: ${probability}%`;
            }
        });

        // Инициализация подсчета символов для всех текстовых полей
        setupCharacterCount('current_status', 'current_status_count');
        setupCharacterCount('completed_in_period', 'completed_count');
        setupCharacterCount('plans_for_next_period', 'plans_count');

        // Автоматическое скрытие сообщений через 5 секунд
        setTimeout(function() {
            const messages = document.querySelectorAll('.error-message, .success-message');
            messages.forEach(message => {
                message.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>