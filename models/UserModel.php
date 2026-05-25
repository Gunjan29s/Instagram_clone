<?php
require_once __DIR__ . '/BaseModel.php';

class UserModel extends BaseModel {

    public function __construct() {
        parent::__construct();
        $this->ensureLastSeenColumn();
    }

    private function ensureLastSeenColumn(): void {
        try {
            $this->db->exec(
                "ALTER TABLE users ADD COLUMN last_seen DATETIME DEFAULT NULL"
            );
        } catch (PDOException $e) {
            // Column already exists — ignore
        }
    }

    public function updateLastSeen(int $userId): void {
        $this->inupdel(
            "UPDATE users SET last_seen = NOW() WHERE id = ?",
            [$userId]
        );
    }

    public function clearLastSeen(int $userId): void {
        $this->inupdel(
            "UPDATE users SET last_seen = NULL WHERE id = ?",
            [$userId]
        );
    }

    // Sare users fetch karna
    public function getAllUsers(): array {
        return $this->selectQuery("SELECT * FROM users");
    }

    public function getRecentUsers(int $limit = 10): array {
        $stmt = $this->db->prepare("SELECT id, username, full_name, profile_pic FROM users ORDER BY id DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getUserById(int $id): array {
        $rows = $this->selectQuery("SELECT * FROM users WHERE id = ?", [$id]);
        return $rows[0] ?? [];
    }
    public function getUserByUsername(string $username): array {
        $rows = $this->selectQuery(
            "SELECT * FROM users WHERE username = ?",
            [$username]
        );
        return $rows[0] ?? [];
    }
    public function getUserByEmail(string $email): array {
        $rows = $this->selectQuery(
            "SELECT * FROM users WHERE email = ?",
            [$email]
        );
        return $rows[0] ?? [];
    }
    public function createUser(string $username, string $email, string $password, string $fullName = ''): int {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        return $this->inupdel(
            "INSERT INTO users (username, email, password, full_name, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$username, $email, $hashed, $fullName]
        );
    }
    public function updateUser(int $id, string $username, string $email): int {
        return $this->inupdel(
            "UPDATE users SET username = ?, email = ? WHERE id = ?",
            [$username, $email, $id]
        );
    }

    public function getProfile(int $profileUserId, int $currentUserId): array {
        $rows = $this->selectQuery(
            "SELECT u.*,
                    (SELECT COUNT(*) FROM posts WHERE user_id = u.id) AS post_count,
                    (SELECT COUNT(*) FROM follows WHERE following_id = u.id) AS followers,
                    (SELECT COUNT(*) FROM follows WHERE follower_id = u.id) AS following,
                    EXISTS(
                        SELECT 1 FROM follows
                        WHERE follower_id = ? AND following_id = u.id
                    ) AS is_following
             FROM users u
             WHERE u.id = ?
             LIMIT 1",
            [$currentUserId, $profileUserId]
        );
        return $rows[0] ?? [];
    }
}
?>
