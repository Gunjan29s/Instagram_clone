<?php
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/PostModel.php';
require_once __DIR__ . '/../../config/mailing.php';

class AdminController {

    private static $instance = null;
    private $db;

    private function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureAdminsTable();
        $this->ensureReportsTable();
        new PostModel();
    }

    public static function getInstance(): AdminController {
        if (self::$instance === null) {
            self::$instance = new AdminController();
        }
        return self::$instance;
    }

    public function searchData(string $table, array $searchColumns = [], string $search = '', string $sortColumn = 'id', string $sort = 'DESC', int $limit = 50, int $offset = 0): array {
        $allowedColumnsByTable = [
            'users' => ['id', 'username', 'email', 'full_name', 'created_at'],
            'posts' => ['id', 'user_id', 'caption', 'location', 'created_at'],
            'likes' => ['id', 'user_id', 'post_id', 'created_at'],
            'comments' => ['id', 'user_id', 'post_id', 'created_at'],
            'follows' => ['id', 'follower_id', 'following_id', 'created_at'],
            'messages' => ['id', 'sender_id', 'receiver_id', 'created_at'],
            'notifications' => ['id', 'user_id', 'from_user_id', 'created_at'],
            'problem_reports' => ['id', 'user_id', 'status', 'created_at'],
            'admins' => ['id', 'username', 'email', 'full_name', 'created_at'],
        ];

        if (!isset($allowedColumnsByTable[$table])) return [];
        $allowedColumns = $allowedColumnsByTable[$table];
        $searchColumns = array_values(array_intersect($searchColumns, $allowedColumns));
        $sortColumn = in_array($sortColumn, $allowedColumns, true) ? $sortColumn : 'id';
        
        $sort = strtoupper($sort) === 'ASC' ? 'ASC' : 'DESC';
        $sql = "SELECT * FROM {$table}";
        if (!empty($search) && !empty($searchColumns)) {
            $cols = implode(", ", $searchColumns);
            $sql .= " WHERE CONCAT_WS(' ', {$cols}) LIKE :search";
        }
        $sql .= " ORDER BY {$sortColumn} {$sort} LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        if (!empty($search) && !empty($searchColumns)) {
            $stmt->bindValue(':search', "%{$search}%");
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getStats(): array {
        return [
            'total_users' => $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'total_posts' => $this->db->query("SELECT COUNT(*) FROM posts")->fetchColumn(),
            'total_likes' => $this->db->query("SELECT COUNT(*) FROM likes")->fetchColumn(),
            'total_comments' => $this->db->query("SELECT COUNT(*) FROM comments")->fetchColumn(),
            'total_follows' => $this->db->query("SELECT COUNT(*) FROM follows")->fetchColumn(),
            'total_messages' => $this->db->query("SELECT COUNT(*) FROM messages")->fetchColumn(),
            'total_notifications' => $this->db->query("SELECT COUNT(*) FROM notifications")->fetchColumn(),
            'new_today' => $this->db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn(),
        ];
    }

    // --- USERS ---
    public function searchUsers(string $q, string $date = ''): array {
        $q = trim($q); $date = trim($date);
        $sql = "SELECT u.*, 
                    (SELECT COUNT(*) FROM posts WHERE user_id = u.id) AS post_count,
                    (SELECT COUNT(*) FROM follows WHERE following_id = u.id) AS followers
                FROM users u
                WHERE CONCAT_WS(' ', u.username, u.email, u.full_name, DATE_FORMAT(u.created_at, '%Y-%m-%d %d %M %Y %b %e %Y')) LIKE :q";
        
        if ($date !== '') {
            $sql .= " AND DATE_FORMAT(u.created_at, '%Y-%m-%d %d %M %Y %b %e %Y') LIKE :date";
        }
        $sql .= " ORDER BY u.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':q', "%$q%");
        if ($date !== '') $stmt->bindValue(':date', "%$date%");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAllUsers(string $date = '', int $limit = 50, int $offset = 0): array {
        $date = trim($date);
        $sql = "SELECT u.*,
                    (SELECT COUNT(*) FROM posts WHERE user_id = u.id) AS post_count,
                    (SELECT COUNT(*) FROM follows WHERE following_id = u.id) AS followers
                FROM users u";
        
        if ($date !== '') {
            $sql .= " WHERE DATE_FORMAT(u.created_at, '%Y-%m-%d %d %M %Y %b %e %Y') LIKE :date";
        }
        $sql .= " ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        if ($date !== '') $stmt->bindValue(':date', "%$date%");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // --- POSTS ---
    public function searchPosts(string $q, string $date = ''): array {
        $q = trim($q); $date = trim($date);
        $sql = "SELECT * FROM (
                    SELECT p.id, p.user_id, p.media_path, p.media_type, p.caption, p.location, p.created_at, 'post' AS item_type, u.username, u.email, 
                           (SELECT COUNT(*) FROM likes WHERE post_id = p.id) AS like_count, 
                           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count
                    FROM posts p JOIN users u ON u.id = p.user_id
                    WHERE CONCAT_WS(' ', u.username, u.email, p.caption, DATE_FORMAT(p.created_at, '%Y-%m-%d %d %M %Y %b %e %Y')) LIKE :q1
                    UNION ALL
                    SELECT s.id, s.user_id, s.media_path, s.media_type, s.caption, '' AS location, s.created_at, 'story' AS item_type, u.username, u.email, 0 AS like_count, 0 AS comment_count
                    FROM stories s JOIN users u ON u.id = s.user_id
                    WHERE CONCAT_WS(' ', u.username, u.email, s.caption, DATE_FORMAT(s.created_at, '%Y-%m-%d %d %M %Y %b %e %Y')) LIKE :q2
                ) media_items";
        
        if ($date !== '') {
            $sql .= " WHERE DATE_FORMAT(created_at, '%Y-%m-%d %d %M %Y %b %e %Y') LIKE :date";
        }
        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':q1', "%$q%");
        $stmt->bindValue(':q2', "%$q%");
        if ($date !== '') $stmt->bindValue(':date', "%$date%");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAllPosts(string $date = '', int $limit = 50, int $offset = 0): array {
        $date = trim($date);
        $sql = "SELECT * FROM (
                    SELECT p.id, p.user_id, p.media_path, p.media_type, p.caption, p.location, p.created_at, 'post' AS item_type, u.username, u.email, 
                           (SELECT COUNT(*) FROM likes WHERE post_id = p.id) AS like_count, 
                           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count
                    FROM posts p JOIN users u ON u.id = p.user_id
                    UNION ALL
                    SELECT s.id, s.user_id, s.media_path, s.media_type, s.caption, '' AS location, s.created_at, 'story' AS item_type, u.username, u.email, 0 AS like_count, 0 AS comment_count
                    FROM stories s JOIN users u ON u.id = s.user_id
                ) media_items ";
        
        if ($date !== '') {
            $sql .= " WHERE DATE_FORMAT(created_at, '%Y-%m-%d %d %M %Y %b %e %Y') LIKE :date";
        }
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        if ($date !== '') $stmt->bindValue(':date', "%$date%");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // --- LIKES ---
    public function searchLikes(string $q, string $date = ''): array {
        $q = trim($q); $date = trim($date);
        $sql = "SELECT l.*, u.username, p.caption 
                FROM likes l 
                LEFT JOIN users u ON u.id = l.user_id 
                LEFT JOIN posts p ON p.id = l.post_id 
                WHERE CONCAT_WS(' ', u.username, p.caption, l.user_id, l.post_id, DATE_FORMAT(l.created_at, '%Y-%m-%d %d %M %Y %b %e %Y')) LIKE :q";
        
        if ($date !== '') {
            $sql .= " AND DATE_FORMAT(l.created_at, '%Y-%m-%d %d %M %Y %b %e %Y') LIKE :date";
        }
        $sql .= " ORDER BY l.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':q', "%$q%");
        if ($date !== '') $stmt->bindValue(':date', "%$date%");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAllLikes(string $date = ''): array {
        $date = trim($date);
        $sql = "SELECT l.*, u.username, p.caption FROM likes l LEFT JOIN users u ON u.id = l.user_id LEFT JOIN posts p ON p.id = l.post_id";
        
        if ($date !== '') {
            $sql .= " WHERE DATE_FORMAT(l.created_at, '%Y-%m-%d %d %M %Y %b %e %Y') LIKE :date";
        }
        $sql .= " ORDER BY l.created_at DESC";

        $stmt = $this->db->prepare($sql);
        if ($date !== '') $stmt->bindValue(':date', "%$date%");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // --- COMMENTS ---
    public function searchComments(string $q, string $date = ''): array {
        $q = trim($q); $date = trim($date);
        $sql = "SELECT c.*, u.username, p.caption, (SELECT COUNT(*) FROM likes WHERE post_id = c.post_id) AS like_count 
                FROM comments c LEFT JOIN users u ON u.id = c.user_id LEFT JOIN posts p ON p.id = c.post_id 
                WHERE CONCAT_WS(' ', u.username, c.comment, p.caption, DATE_FORMAT(c.created_at, '%Y-%m-%d %d %M %Y %b %e %Y')) LIKE :q";
        
        if ($date !== '') {
            $sql .= " AND DATE_FORMAT(c.created_at, '%Y-%m-%d %d %M %Y %b %e %Y') LIKE :date";
        }
        $sql .= " ORDER BY c.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':q', "%$q%");
        if ($date !== '') $stmt->bindValue(':date', "%$date%");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAllComments(string $date = ''): array {
        $date = trim($date);
        $sql = "SELECT c.*, u.username, p.caption, (SELECT COUNT(*) FROM likes WHERE post_id = c.post_id) AS like_count 
                FROM comments c LEFT JOIN users u ON u.id = c.user_id LEFT JOIN posts p ON p.id = c.post_id";
        
        if ($date !== '') {
            $sql .= " WHERE DATE_FORMAT(c.created_at, '%Y-%m-%d %d %M %Y %b %e %Y') LIKE :date";
        }
        $sql .= " ORDER BY c.created_at DESC";

        $stmt = $this->db->prepare($sql);
        if ($date !== '') $stmt->bindValue(':date', "%$date%");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // --- FOLLOWS ---
    public function searchFollows(string $q, string $date = ''): array {
        $q = trim($q); $date = trim($date);
        $sql = "SELECT f.*, u1.username AS follower_name, u2.username AS following_name 
                FROM follows f 
                LEFT JOIN users u1 ON u1.id = f.follower_id 
                LEFT JOIN users u2 ON u2.id = f.following_id 
                WHERE CONCAT_WS(' ', u1.username, u2.username, DATE_FORMAT(f.created_at, '%Y-%m-%d %d %M %Y %b %e %Y')) LIKE :q";
        
        if ($date !== '') {
            $sql .= " AND DATE_FORMAT(f.created_at, '%Y-%m-%d %d %M %Y %b %e %Y') LIKE :date";
        }
        $sql .= " ORDER BY f.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':q', "%$q%");
        if ($date !== '') $stmt->bindValue(':date', "%$date%");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAllFollows(string $date = ''): array {
        $date = trim($date);
        $sql = "SELECT f.*, u1.username AS follower_name, u2.username AS following_name 
                FROM follows f 
                LEFT JOIN users u1 ON u1.id = f.follower_id 
                LEFT JOIN users u2 ON u2.id = f.following_id";
        
        if ($date !== '') {
            $sql .= " WHERE DATE_FORMAT(f.created_at, '%Y-%m-%d %d %M %Y %b %e %Y') LIKE :date";
        }
        $sql .= " ORDER BY f.created_at DESC";

        $stmt = $this->db->prepare($sql);
        if ($date !== '') $stmt->bindValue(':date', "%$date%");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // --- MESSAGES ---
    public function getAllMessages(string $date = ''): array {
        $date = trim($date);
        $sql = "SELECT m.*, s.username AS sender_name, r.username AS receiver_name 
                FROM messages m LEFT JOIN users s ON s.id = m.sender_id LEFT JOIN users r ON r.id = m.receiver_id";
        
        if ($date !== '') {
            $sql .= " WHERE DATE_FORMAT(m.created_at, '%Y-%m-%d %d %M %Y %b %e %Y') LIKE :date";
        }
        $sql .= " ORDER BY m.created_at DESC";

        $stmt = $this->db->prepare($sql);
        if ($date !== '') $stmt->bindValue(':date', "%$date%");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // --- NOTIFICATIONS ---
    public function getAllNotifications(string $date = ''): array {
        $date = trim($date);
        $sql = "SELECT n.*, u.username FROM notifications n LEFT JOIN users u ON u.id = n.user_id";
        
        if ($date !== '') {
            $sql .= " WHERE DATE_FORMAT(n.created_at, '%Y-%m-%d %d %M %Y %b %e %Y') LIKE :date";
        }
        $sql .= " ORDER BY n.created_at DESC";

        $stmt = $this->db->prepare($sql);
        if ($date !== '') $stmt->bindValue(':date', "%$date%");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // --- REPORTS ---
    public function searchReports(string $q, string $date = ''): array {
        $q = trim($q); $date = trim($date);
        $sql = "SELECT r.*, u.username, u.email, u.full_name FROM problem_reports r LEFT JOIN users u ON u.id = r.user_id 
                WHERE CONCAT_WS(' ', r.message, r.status, u.username, u.email, u.full_name, DATE_FORMAT(r.created_at, '%Y-%m-%d %d %M %Y %b %e %Y')) LIKE :q";
        
        if ($date !== '') {
            $sql .= " AND DATE_FORMAT(r.created_at, '%Y-%m-%d %d %M %Y %b %e %Y') LIKE :date";
        }
        $sql .= " ORDER BY r.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':q', "%$q%");
        if ($date !== '') $stmt->bindValue(':date', "%$date%");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAllReports(string $date = ''): array {
        $date = trim($date);
        $sql = "SELECT r.*, u.username, u.email, u.full_name FROM problem_reports r LEFT JOIN users u ON u.id = r.user_id";
        
        if ($date !== '') {
            $sql .= " WHERE DATE_FORMAT(r.created_at, '%Y-%m-%d %d %M %Y %b %e %Y') LIKE :date";
        }
        $sql .= " ORDER BY r.created_at DESC";

        $stmt = $this->db->prepare($sql);
        if ($date !== '') $stmt->bindValue(':date', "%$date%");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // --- OTHER HELPERS ---
    public function deletePost(int $id): bool { return (new PostModel())->deletePostByAdmin($id) > 0; }
    public function deleteStory(int $id): bool { return (new PostModel())->deleteStoryByAdmin($id) > 0; }
    public function updateReportStatus(int $id, string $status): bool {
        $allowed = ['open', 'reviewing', 'resolved'];
        if (!in_array($status, $allowed, true)) return false;
        $stmt = $this->db->prepare("UPDATE problem_reports SET status = :status WHERE id = :id");
        return $stmt->execute([':status' => $status, ':id' => $id]);
    }
    public function getRecentActivity(int $limit = 10): array {
        $stmt = $this->db->prepare("SELECT 'signup' AS type, full_name AS detail, created_at FROM users UNION ALL SELECT 'post', caption, created_at FROM posts ORDER BY created_at DESC LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // --- ADMIN LOGIC ---
    public function adminLogin(string $username, string $password): bool {
        $stmt = $this->db->prepare("SELECT * FROM admins WHERE username=:username OR email=:username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password'])) return true;

        $fallbackUser = getenv('ADMIN_FALLBACK_USER') ?: '';
        $fallbackPassHash = getenv('ADMIN_FALLBACK_PASS_HASH') ?: '';
        return $fallbackUser !== ''
            && $fallbackPassHash !== ''
            && hash_equals($fallbackUser, $username)
            && password_verify($password, $fallbackPassHash);
    }
    public function createAdmin(string $username, string $email, string $password, string $fullName): bool {
        try {
            $stmt = $this->db->prepare("INSERT INTO admins (username, email, password, full_name, created_at) VALUES (:username, :email, :password, :full_name, NOW())");
            $ok = $stmt->execute([':username' => $username, ':email' => $email, ':password' => password_hash($password, PASSWORD_DEFAULT), ':full_name' => $fullName]);
        } catch (PDOException $e) { return false; }
        if ($ok) $this->sendAdminSignupEmails($username, $email, $fullName);
        return $ok;
    }
    public function adminExists(string $username, string $email): bool {
        $stmt = $this->db->prepare("SELECT id FROM admins WHERE username=:username OR email=:email LIMIT 1");
        $stmt->execute([':username' => $username, ':email' => $email]);
        return (bool)$stmt->fetch();
    }

    public function hasAnyAdmin(): bool {
        $this->ensureAdminsTable();
        return (int) $this->db->query("SELECT COUNT(*) FROM admins")->fetchColumn() > 0;
    }
    
    // --- DELETE USER & CLEANUP ---
    public function deleteUser(int $id): bool {
        if ($id <= 0) return false;
        try {
            $this->db->beginTransaction();
            $this->runDelete("DELETE FROM likes WHERE user_id = :id", [':id' => $id]);
            $this->runDelete("DELETE FROM comments WHERE user_id = :id", [':id' => $id]);
            $this->runDelete("DELETE c FROM comments c JOIN posts p ON p.id = c.post_id WHERE p.user_id = :id", [':id' => $id]);
            $this->runDelete("DELETE FROM saved_posts WHERE user_id = :id", [':id' => $id]);
            $this->runDelete("DELETE sp FROM saved_posts sp JOIN posts p ON p.id = sp.post_id WHERE p.user_id = :id", [':id' => $id]);
            $this->runDelete("DELETE FROM post_tags WHERE user_id = :id", [':id' => $id]);
            $this->runDelete("DELETE pt FROM post_tags pt JOIN posts p ON p.id = pt.post_id WHERE p.user_id = :id", [':id' => $id]);
            $this->runDelete("DELETE FROM follows WHERE follower_id = :id OR following_id = :id", [':id' => $id]);
            $this->runDelete("DELETE FROM messages WHERE sender_id = :id OR receiver_id = :id", [':id' => $id]);
            $this->runDelete("DELETE FROM notifications WHERE user_id = :id OR from_user_id = :id", [':id' => $id]);
            $this->runDelete("DELETE FROM notifications WHERE post_id IN (SELECT id FROM posts WHERE user_id = :id)", [':id' => $id]);
            $this->runDelete("DELETE FROM problem_reports WHERE user_id = :id", [':id' => $id]);
            $this->runDelete("DELETE FROM likes WHERE post_id IN (SELECT id FROM posts WHERE user_id = :id)", [':id' => $id]);
            $stmt = $this->db->prepare("DELETE FROM stories WHERE user_id = :id"); $stmt->execute([':id' => $id]);
            $stmt = $this->db->prepare("DELETE FROM posts WHERE user_id = :id"); $stmt->execute([':id' => $id]);
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id"); $stmt->execute([':id' => $id]);
            $deleted = $stmt->rowCount() > 0;
            return $this->db->commit() && $deleted;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return false;
        }
    }
    public function toggleBanUser(int $id): bool {
        if ($id <= 0) return false;
        $userStmt = $this->db->prepare("SELECT username, email, full_name FROM users WHERE id = :id LIMIT 1");
        $userStmt->execute([':id' => $id]);
        $user = $userStmt->fetch();
        if (!$user) return false;
        $deleted = $this->deleteUser($id);
        if ($deleted) {
            $this->sendUserBanEmail($user);
        }
        return $deleted;
    }
    private function runDelete(string $sql, array $params): void {
        try { $stmt = $this->db->prepare($sql); $stmt->execute($params); } catch (PDOException $e) {}
    }

    // --- SETUP TABLES & EMAILS ---
    private function ensureAdminsTable(): void { $this->db->exec("CREATE TABLE IF NOT EXISTS admins (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, email VARCHAR(100) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, full_name VARCHAR(100) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)"); }
    private function ensureReportsTable(): void { $this->db->exec("CREATE TABLE IF NOT EXISTS problem_reports (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, message TEXT NOT NULL, status VARCHAR(30) DEFAULT 'open', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)"); }
    private function sendUserBanEmail(array $user): void {
        $email = trim((string)($user['email'] ?? '')); if ($email === '') return;
        $name = trim((string)($user['full_name'] ?? '')) ?: ((string)($user['username'] ?? 'User'));
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); $safeUsername = htmlspecialchars((string)($user['username'] ?? ''), ENT_QUOTES, 'UTF-8');
        $body = "<div style='font-family:Arial,sans-serif;line-height:1.5;color:#222'><h2 style='margin:0 0 12px'>Account banned</h2><p>Hello {$safeName},</p><p>Your account <strong>@{$safeUsername}</strong> has been banned by admin.</p><p>Your account details have been removed from Instagram Clone.</p></div>";
        try { sendMail($email, $name, 'Account banned by admin', $body); } catch (Throwable $e) {}
    }
    private function sendAdminSignupEmails(string $username, string $email, string $fullName): void {
        $safeName = htmlspecialchars($fullName); $safeUsername = htmlspecialchars($username); $safeEmail = htmlspecialchars($email); $signupTime = date('d M Y, h:i A');
        $newAdminBody = "<div style='font-family:Arial;padding:20px'><h2>Admin Account Created</h2><p>Hello {$safeName}, your admin account has been created successfully.</p><p><strong>Username:</strong> {$safeUsername}</p><p><strong>Email:</strong> {$safeEmail}</p></div>";
        $mainAdminBody = "<div style='font-family:Arial;padding:20px'><h2>New Admin Signup</h2><p><strong>Name:</strong> {$safeName}</p><p><strong>Username:</strong> {$safeUsername}</p><p><strong>Email:</strong> {$safeEmail}</p><p><strong>Time:</strong> {$signupTime}</p></div>";
        sendMail($email, $fullName, 'Your Instagram Clone Admin Account', $newAdminBody); sendMail(ADMIN_EMAIL, ADMIN_NAME, 'New Admin Signup: ' . $username, $mainAdminBody);
    }
}
?>
