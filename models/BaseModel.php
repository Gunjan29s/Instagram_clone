<?php

require_once __DIR__ . '/../config/database.php';

class BaseModel {
    protected PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureCoreTables();
        $this->ensurePerformanceIndexes();
    }

    private function ensureCoreTables(): void {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(100),
                bio TEXT,
                website VARCHAR(200),
                phone VARCHAR(30),
                gender VARCHAR(30),
                show_account_suggestions TINYINT(1) DEFAULT 1,
                profile_pic VARCHAR(255) DEFAULT '/instagram_clone/images/default_avatar.png',
                reset_token VARCHAR(100) DEFAULT NULL,
                reset_expiry DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        );

        foreach ([
            "ALTER TABLE users ADD COLUMN phone VARCHAR(30) DEFAULT NULL",
            "ALTER TABLE users ADD COLUMN gender VARCHAR(30) DEFAULT NULL",
            "ALTER TABLE users ADD COLUMN show_account_suggestions TINYINT(1) DEFAULT 1",
            "ALTER TABLE users ADD COLUMN reset_token VARCHAR(100) DEFAULT NULL",
            "ALTER TABLE users ADD COLUMN reset_expiry DATETIME DEFAULT NULL",
            "ALTER TABLE users ADD COLUMN is_banned TINYINT(1) DEFAULT 0",
        ] as $alterSql) {
            try {
                $this->db->exec($alterSql);
            } catch (PDOException $e) {
                // Existing databases may already have these columns.
            }
        }

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS posts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                media_path VARCHAR(300) NOT NULL,
                media_type ENUM('image','video') DEFAULT 'image',
                caption TEXT,
                location VARCHAR(200),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS follows (
                id INT AUTO_INCREMENT PRIMARY KEY,
                follower_id INT NOT NULL,
                following_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_follow (follower_id, following_id),
                FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
            )"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS follow_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sender_id INT NOT NULL,
                receiver_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_request (sender_id, receiver_id),
                FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
            )"
        );

        foreach ([
            "ALTER TABLE follow_requests ADD COLUMN sender_id INT NULL",
            "ALTER TABLE follow_requests ADD COLUMN receiver_id INT NULL",
            "ALTER TABLE follow_requests ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP",
            "UPDATE follow_requests SET sender_id = follower_id WHERE sender_id IS NULL AND follower_id IS NOT NULL",
            "UPDATE follow_requests SET receiver_id = following_id WHERE receiver_id IS NULL AND following_id IS NOT NULL",
            "ALTER TABLE follow_requests ADD UNIQUE KEY unique_request (sender_id, receiver_id)",
        ] as $followRequestSql) {
            try {
                $this->db->exec($followRequestSql);
            } catch (PDOException $e) {
                // Existing databases may already have the right follow request schema.
            }
        }

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS blocked_users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                blocker_id INT NOT NULL,
                blocked_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_block (blocker_id, blocked_id),
                FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
            )"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS likes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                post_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_like (user_id, post_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
            )"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                post_id INT NOT NULL,
                comment TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
            )"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS stories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                media_path VARCHAR(300) NOT NULL,
                media_type ENUM('image','video') DEFAULT 'image',
                caption TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sender_id INT NOT NULL,
                receiver_id INT NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
            )"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                from_user_id INT NOT NULL,
                type ENUM('like','comment','follow','mention','message','message_request_declined','follow_request') NOT NULL,
                post_id INT DEFAULT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE SET NULL
            )"
        );

        try {
            $this->db->exec("ALTER TABLE notifications MODIFY type ENUM('like','comment','follow','mention','message','message_request_declined','follow_request') NOT NULL");
        } catch (PDOException $e) {
            // Existing databases may already be up to date.
        }

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS saved_posts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                post_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_saved_post (user_id, post_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
            )"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS post_tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT NOT NULL,
                user_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_post_tag (post_id, user_id),
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS problem_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                message TEXT NOT NULL,
                status VARCHAR(30) DEFAULT 'open',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )"
        );
    }

    private function ensurePerformanceIndexes(): void {
        foreach ([
            "CREATE INDEX idx_posts_created_at ON posts (created_at)",
            "CREATE INDEX idx_posts_user_created ON posts (user_id, created_at)",
            "CREATE INDEX idx_stories_expires_at ON stories (expires_at)",
            "CREATE INDEX idx_messages_pair_read ON messages (receiver_id, sender_id, is_read, created_at)",
            "CREATE INDEX idx_notifications_user_read ON notifications (user_id, is_read, created_at)",
            "CREATE INDEX idx_comments_post_created ON comments (post_id, created_at)",
            "CREATE INDEX idx_likes_post_created ON likes (post_id, created_at)",
        ] as $indexSql) {
            try {
                $this->db->exec($indexSql);
            } catch (PDOException $e) {
                // Index already exists on upgraded databases.
            }
        }
    }

    public function selectQuery(string $query, array $params = []): array {
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function inupdel(string $query, array $params = []): int {
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}
?>
