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

// Функции для работы с пользователями
function getUserById($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT id, username, email, full_name, role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting user: " . $e->getMessage());
        return false;
    }
}

// Функции для работы с отчетами
function createReport($pdo, $title, $description, $type, $criteria, $created_by, $is_public = 0) {
    try {
        $stmt = $pdo->prepare("INSERT INTO reports (title, description, type, criteria, created_by, is_public) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $type, $criteria, $created_by, $is_public]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error creating report: " . $e->getMessage());
        return false;
    }
}

function getUserReports($pdo, $user_id, $include_public = true) {
    try {
        if ($include_public) {
            $stmt = $pdo->prepare("
                SELECT r.*, u.username, u.full_name 
                FROM reports r 
                JOIN users u ON r.created_by = u.id 
                WHERE r.created_by = ? OR r.is_public = 1 
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$user_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT r.*, u.username, u.full_name 
                FROM reports r 
                JOIN users u ON r.created_by = u.id 
                WHERE r.created_by = ? 
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$user_id]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting user reports: " . $e->getMessage());
        return [];
    }
}

function getAllReports($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, u.username, u.full_name 
            FROM reports r 
            JOIN users u ON r.created_by = u.id 
            ORDER BY r.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting all reports: " . $e->getMessage());
        return [];
    }
}

function getReportById($pdo, $report_id, $user_id = null) {
    try {
        if ($user_id) {
            $stmt = $pdo->prepare("
                SELECT r.*, u.username, u.full_name 
                FROM reports r 
                JOIN users u ON r.created_by = u.id 
                WHERE r.id = ? AND (r.created_by = ? OR r.is_public = 1)
            ");
            $stmt->execute([$report_id, $user_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT r.*, u.username, u.full_name 
                FROM reports r 
                JOIN users u ON r.created_by = u.id 
                WHERE r.id = ?
            ");
            $stmt->execute([$report_id]);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting report: " . $e->getMessage());
        return false;
    }
}

function updateReport($pdo, $report_id, $title, $description, $type, $criteria, $is_public, $user_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE reports 
            SET title = ?, description = ?, type = ?, criteria = ?, is_public = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ? AND created_by = ?
        ");
        return $stmt->execute([$title, $description, $type, $criteria, $is_public, $report_id, $user_id]);
    } catch (PDOException $e) {
        error_log("Error updating report: " . $e->getMessage());
        return false;
    }
}

function deleteReport($pdo, $report_id, $user_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM reports WHERE id = ? AND created_by = ?");
        return $stmt->execute([$report_id, $user_id]);
    } catch (PDOException $e) {
        error_log("Error deleting report: " . $e->getMessage());
        return false;
    }
}

function getReportsByType($pdo, $type, $user_id = null) {
    try {
        if ($user_id) {
            $stmt = $pdo->prepare("
                SELECT r.*, u.username, u.full_name 
                FROM reports r 
                JOIN users u ON r.created_by = u.id 
                WHERE r.type = ? AND (r.created_by = ? OR r.is_public = 1)
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$type, $user_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT r.*, u.username, u.full_name 
                FROM reports r 
                JOIN users u ON r.created_by = u.id 
                WHERE r.type = ?
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$type]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting reports by type: " . $e->getMessage());
        return [];
    }
}

// Функции для работы с проектами
function createProject($pdo, $title, $description, $status, $priority, $start_date, $end_date, $budget, $progress, $manager_id, $created_by) {
    try {
        $stmt = $pdo->prepare("INSERT INTO projects (title, description, status, priority, start_date, end_date, budget, progress, manager_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$title, $description, $status, $priority, $start_date, $end_date, $budget, $progress, $manager_id, $created_by]);
    } catch (PDOException $e) {
        error_log("Error creating project: " . $e->getMessage());
        return false;
    }
}

function getAllProjects($pdo, $user_id = null, $limit = null) {
    try {
        $sql = "
            SELECT p.*, u.username as manager_username, u.full_name as manager_name,
                   creator.username as created_by_username, creator.full_name as created_by_name
            FROM projects p
            LEFT JOIN users u ON p.manager_id = u.id
            LEFT JOIN users creator ON p.created_by = creator.id
        ";
        
        $params = [];
        
        if ($user_id) {
            $sql .= " WHERE p.manager_id = ? OR p.created_by = ?";
            $params[] = $user_id;
            $params[] = $user_id;
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting projects: " . $e->getMessage());
        return [];
    }
}

function getProjectsStats($pdo) {
    try {
        $stats = [];
        
        // Общее количество проектов
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM projects");
        $stats['total'] = $stmt->fetchColumn();
        
        // Проекты по статусам
        $stmt = $pdo->query("
            SELECT status, COUNT(*) as count 
            FROM projects 
            GROUP BY status
        ");
        $status_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($status_stats as $stat) {
            $stats[$stat['status']] = $stat['count'];
        }
        
        // Проекты по приоритетам
        $stmt = $pdo->query("
            SELECT priority, COUNT(*) as count 
            FROM projects 
            GROUP BY priority
        ");
        $priority_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($priority_stats as $stat) {
            $stats[$stat['priority']] = $stat['count'];
        }
        
        // Общий бюджет
        $stmt = $pdo->query("SELECT SUM(budget) as total_budget FROM projects");
        $stats['total_budget'] = $stmt->fetchColumn() ?? 0;
        
        // Средний прогресс
        $stmt = $pdo->query("SELECT AVG(progress) as avg_progress FROM projects");
        $stats['avg_progress'] = round($stmt->fetchColumn() ?? 0, 1);
        
        // Активные проекты (статус active)
        $stmt = $pdo->query("SELECT COUNT(*) as active FROM projects WHERE status = 'active'");
        $stats['active'] = $stmt->fetchColumn();
        
        // Завершенные проекты
        $stmt = $pdo->query("SELECT COUNT(*) as completed FROM projects WHERE status = 'completed'");
        $stats['completed'] = $stmt->fetchColumn();
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Error getting projects stats: " . $e->getMessage());
        return [
            'total' => 0,
            'active' => 0,
            'completed' => 0,
            'total_budget' => 0,
            'avg_progress' => 0
        ];
    }
}

function getProjectById($pdo, $project_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, u.username as manager_username, u.full_name as manager_name,
                   creator.username as created_by_username, creator.full_name as created_by_name
            FROM projects p
            LEFT JOIN users u ON p.manager_id = u.id
            LEFT JOIN users creator ON p.created_by = creator.id
            WHERE p.id = ?
        ");
        $stmt->execute([$project_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting project: " . $e->getMessage());
        return false;
    }
}

function updateProject($pdo, $project_id, $title, $description, $status, $priority, $start_date, $end_date, $budget, $progress, $manager_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE projects 
            SET title = ?, description = ?, status = ?, priority = ?, start_date = ?, end_date = ?, 
                budget = ?, progress = ?, manager_id = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        return $stmt->execute([$title, $description, $status, $priority, $start_date, $end_date, $budget, $progress, $manager_id, $project_id]);
    } catch (PDOException $e) {
        error_log("Error updating project: " . $e->getMessage());
        return false;
    }
}

function deleteProject($pdo, $project_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        return $stmt->execute([$project_id]);
    } catch (PDOException $e) {
        error_log("Error deleting project: " . $e->getMessage());
        return false;
    }
}

function getProjectsByStatus($pdo, $status) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, u.username as manager_username, u.full_name as manager_name
            FROM projects p
            LEFT JOIN users u ON p.manager_id = u.id
            WHERE p.status = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting projects by status: " . $e->getMessage());
        return [];
    }
}

function getRecentProjects($pdo, $limit = 5) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, u.username as manager_username, u.full_name as manager_name
            FROM projects p
            LEFT JOIN users u ON p.manager_id = u.id
            ORDER BY p.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting recent projects: " . $e->getMessage());
        return [];
    }
}

function getActiveProjects($pdo, $limit = null) {
    try {
        $sql = "
            SELECT p.*, u.username as manager_username, u.full_name as manager_name
            FROM projects p
            LEFT JOIN users u ON p.manager_id = u.id
            WHERE p.status = 'active'
            ORDER BY p.priority DESC, p.created_at DESC
        ";
        
        if ($limit) {
            $sql .= " LIMIT ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$limit]);
        } else {
            $stmt = $pdo->query($sql);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting active projects: " . $e->getMessage());
        return [];
    }
}

function getDashboardStats($pdo) {
    try {
        $stats = [];
        
        // Общее количество проектов
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM projects");
        $stats['total_projects'] = $stmt->fetchColumn();
        
        // Активные проекты
        $stmt = $pdo->query("SELECT COUNT(*) as active FROM projects WHERE status = 'active'");
        $stats['active_projects'] = $stmt->fetchColumn();
        
        // Завершенные проекты
        $stmt = $pdo->query("SELECT COUNT(*) as completed FROM projects WHERE status = 'completed'");
        $stats['completed_projects'] = $stmt->fetchColumn();
        
        // Проекты в планировании
        $stmt = $pdo->query("SELECT COUNT(*) as planning FROM projects WHERE status = 'planning'");
        $stats['planning_projects'] = $stmt->fetchColumn();
        
        // Общий бюджет
        $stmt = $pdo->query("SELECT SUM(budget) as total_budget FROM projects");
        $stats['total_budget'] = $stmt->fetchColumn() ?? 0;
        
        // Средний прогресс
        $stmt = $pdo->query("SELECT AVG(progress) as avg_progress FROM projects WHERE status = 'active'");
        $stats['avg_progress'] = round($stmt->fetchColumn() ?? 0, 1);
        
        // Высокоприоритетные проекты
        $stmt = $pdo->query("SELECT COUNT(*) as high_priority FROM projects WHERE priority = 'high' AND status = 'active'");
        $stats['high_priority'] = $stmt->fetchColumn();
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Error getting dashboard stats: " . $e->getMessage());
        return [
            'total_projects' => 0,
            'active_projects' => 0,
            'completed_projects' => 0,
            'planning_projects' => 0,
            'total_budget' => 0,
            'avg_progress' => 0,
            'high_priority' => 0
        ];
    }
}
?>
