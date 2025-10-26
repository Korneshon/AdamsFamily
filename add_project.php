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

$error = '';
$success = '';


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


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
    
        $project_data = [
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
            ':created_by' => $_SESSION['user_id']
        ];

        
        $stage_probability = 0;
        foreach ($project_stages as $stage) {
            if ($stage['id'] == $project_data[':project_stage_id']) {
                $stage_probability = $stage['probability'];
                break;
            }
        }
        $project_data[':probability'] = $stage_probability;

       
        $sql = "INSERT INTO projects (
            client_name, client_inn, project_name, service_id, payment_type_id, 
            project_stage_id, probability, manager_id, segment_id, implementation_year,
            is_industry_solution, is_forecast_accepted, is_dzo_implementation,
            requires_management_control, evaluation_status, industry_manager,
            project_number, current_status, completed_in_period, plans_for_next_period, created_by
        ) VALUES (
            :client_name, :client_inn, :project_name, :service_id, :payment_type_id,
            :project_stage_id, :probability, :manager_id, :segment_id, :implementation_year,
            :is_industry_solution, :is_forecast_accepted, :is_dzo_implementation,
            :requires_management_control, :evaluation_status, :industry_manager,
            :project_number, :current_status, :completed_in_period, :plans_for_next_period, :created_by
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($project_data);
        $project_id = $pdo->lastInsertId();

        
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

        
        $history_sql = "INSERT INTO project_history (project_id, changed_field, old_value, new_value, changed_by) 
                        VALUES (?, 'project_created', NULL, 'Проект создан', ?)";
        $history_stmt = $pdo->prepare($history_sql);
        $history_stmt->execute([$project_id, $_SESSION['user_id']]);

        $pdo->commit();
        $success = 'Проект успешно создан!';
        $_POST = []; 

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при создании проекта: ' . $e->getMessage();
        error_log("Project creation error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавление проекта - Ростелеком</title>
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
        max-width: 1200px;
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

    .form-container {
        background: var(--bg-white);
        padding: 35px;
        border-radius: 0 0 20px 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        border: 1px solid var(--border-light);
    }

    .form-section {
        margin-bottom: 35px;
        padding: 30px;
        border: 2px solid var(--border-light);
        border-radius: 20px;
        background: var(--bg-white);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .form-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }

    .form-section:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(120, 0, 255, 0.1);
    }

    .form-section h2 {
        color: var(--primary);
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid var(--border-light);
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
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
        padding: 16px 20px;
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

    textarea.form-control {
        min-height: 120px;
        resize: vertical;
        line-height: 1.5;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
    }

    .form-row-3 {
        grid-template-columns: 1fr 1fr 1fr;
    }

    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 15px;
        padding: 12px 16px;
        background: var(--bg-gray);
        border-radius: 10px;
        transition: background-color 0.3s ease;
    }

    .checkbox-group:hover {
        background: #f1f5f9;
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

    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 16px 32px;
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
        background: linear-gradient(135deg, #fe4e12 0%, #fe4e12 100%);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
    }

    .btn-success:hover {
        background: linear-gradient(135deg, #fc6c3cff 0%, #fe4e12 100%);
    }

    .btn-danger {
        background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
        box-shadow: 0 6px 20px rgba(239, 68, 68, 0.3);
    }

    .btn-sm {
        padding: 12px 20px;
        font-size: 0.95rem;
    }

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

    .required::after {
        content: " *";
        color: var(--danger);
    }

    .char-count {
        font-size: 0.9rem;
        color: var(--text-light);
        margin-top: 8px;
        font-weight: 500;
    }

    
    .dynamic-section {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        padding: 25px;
        border-radius: 15px;
        margin-bottom: 20px;
        border-left: 4px solid var(--primary);
        transition: all 0.3s ease;
    }

    .dynamic-section:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
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

    .form-section {
        animation: fadeInUp 0.6s ease-out;
    }

    .form-section:nth-child(2) { animation-delay: 0.1s; }
    .form-section:nth-child(3) { animation-delay: 0.2s; }
    .form-section:nth-child(4) { animation-delay: 0.3s; }

    h1 {
        margin-bottom: 25px;
        font-size: 2.2rem;
        font-weight: 700;
        text-align: center;
    }

   
    @media (max-width: 768px) {
        body {
            padding: 15px;
        }
        
        .header {
            padding: 20px;
        }
        
        .form-container {
            padding: 25px;
        }
        
        .form-section {
            padding: 20px;
        }
        
        .form-row, .form-row-3 {
            grid-template-columns: 1fr;
        }
        
        .btn {
            width: 100%;
            justify-content: center;
            margin-bottom: 10px;
        }
    }

    @media (max-width: 480px) {
        .checkbox-group {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        
        .dynamic-section {
            padding: 20px;
        }
    }
</style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-project-diagram"></i> Ростелеком - Управление проектами</h1>
        </div>

        <div class="breadcrumb">
            <a href="index.php"><i class="fas fa-home"></i> Дашборд</a> &raquo; 
            <a href="projects.php"><i class="fas fa-list"></i> Проекты</a> &raquo; 
            <span><i class="fas fa-plus"></i> Добавление проекта</span>
        </div>
        
        <div class="form-container">
            <h1 style="color: var(--rtk-blue); margin-bottom: 20px;">Добавление нового проекта</h1>
            
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
                                        <?php echo ($_POST['project_stage_id'] ?? '') == $stage['id'] ? 'selected' : ''; ?>>
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
                                <?php echo isset($_POST['is_industry_solution']) ? 'checked' : ''; ?>>
                            <label for="is_industry_solution">Отраслевое решение</label>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="is_forecast_accepted" name="is_forecast_accepted" value="1"
                                <?php echo isset($_POST['is_forecast_accepted']) ? 'checked' : ''; ?>>
                            <label for="is_forecast_accepted">Принимаемый к прогнозу</label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_dzo_implementation" name="is_dzo_implementation" value="1"
                                <?php echo isset($_POST['is_dzo_implementation']) ? 'checked' : ''; ?>>
                            <label for="is_dzo_implementation">Реализация через ДЗО</label>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="requires_management_control" name="requires_management_control" value="1"
                                <?php echo isset($_POST['requires_management_control']) ? 'checked' : ''; ?>>
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

                
                <div class="form-section">
                    <div class="section-header">
                        <h2><i class="fas fa-chart-line"></i> Информация по выручке проекта</h2>
                    </div>
                    <div id="revenue-sections">
                        <div class="dynamic-section revenue-section">
                            <div class="form-row form-row-3">
                                <div class="form-group">
                                    <label>Год</label>
                                    <input type="number" name="revenue_year[]" class="form-control" min="2000" max="2030" 
                                           value="<?php echo htmlspecialchars($_POST['revenue_year'][0] ?? ''); ?>">
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
                                    <input type="number" name="revenue_amount[]" class="form-control" step="0.01" 
                                           value="<?php echo htmlspecialchars($_POST['revenue_amount'][0] ?? ''); ?>">
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
                    </div>
                    <button type="button" class="btn btn-sm" onclick="addRevenueSection()">
                        <i class="fas fa-plus"></i> Добавить период выручки
                    </button>
                </div>

                
                <div class="form-section">
                    <div class="section-header">
                        <h2><i class="fas fa-money-bill-wave"></i> Информация по затратам проекта</h2>
                    </div>
                    <div id="cost-sections">
                        <div class="dynamic-section cost-section">
                            <div class="form-row form-row-3">
                                <div class="form-group">
                                    <label>Год</label>
                                    <input type="number" name="cost_year[]" class="form-control" min="2000" max="2030" 
                                           value="<?php echo htmlspecialchars($_POST['cost_year'][0] ?? ''); ?>">
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
                                    <input type="number" name="cost_amount[]" class="form-control" step="0.01" 
                                           value="<?php echo htmlspecialchars($_POST['cost_amount'][0] ?? ''); ?>">
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
                    </div>
                    <button type="button" class="btn btn-sm" onclick="addCostSection()">
                        <i class="fas fa-plus"></i> Добавить период затрат
                    </button>
                </div>

                
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
                        <i class="fas fa-plus"></i> Создать проект
                    </button>
                    <a href="projects.php" class="btn">
                        <i class="fas fa-arrow-left"></i> Назад к списку проектов
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        
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

        
        function getMonthOptions() {
            const months = [
                'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
            ];
            return months.map((month, index) => 
                `<option value="${index + 1}">${month}</option>`
            ).join('');
        }

        
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
            updateCount(); 
        }

        
        setupCharacterCount('current_status', 'current_status_count');
        setupCharacterCount('completed_in_period', 'completed_count');
        setupCharacterCount('plans_for_next_period', 'plans_count');
    </script>
</body>
</html>