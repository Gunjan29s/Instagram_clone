<?php
require_once __DIR__ . '/BaseModel.php';

class FollowModel extends BaseModel {

    public function __construct() {
        parent::__construct();
        $this->ensureFollowsTable();
        $this->ensureBlockedUsersTable();
        $this->ensureNotificationsTable();
    }

    private function ensureFollowsTable(): void {
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
    }

    private function ensureBlockedUsersTable(): void {
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
    }

    public function isBlockedBetween(int $firstUserId, int $secondUserId): bool {
        if ($firstUserId <= 0 || $secondUserId <= 0 || $firstUserId === $secondUserId) {
            return false;
        }

        $rows = $this->selectQuery(
            "SELECT id
             FROM blocked_users
             WHERE (blocker_id = ? AND blocked_id = ?)
                OR (blocker_id = ? AND blocked_id = ?)
             LIMIT 1",
            [$firstUserId, $secondUserId, $secondUserId, $firstUserId]
        );

        return !empty($rows);
    }

    private function ensureNotificationsTable(): void {
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
    }

    public function follow(int $followerId, int $followingId): int {
        if ($followerId <= 0 || $followingId <= 0 || $followerId === $followingId) {
            return 0;
        }
        if ($this->isBlockedBetween($followerId, $followingId)) {
            return 0;
        }

        $rows = $this->inupdel(
            "INSERT IGNORE INTO follows (follower_id, following_id, created_at) VALUES (?, ?, NOW())",
            [$followerId, $followingId]
        );

        if ($rows > 0) {
            $this->createFollowNotification($followingId, $followerId);
        }

        return $rows;
    }

    public function unfollow(int $followerId, int $followingId): int {
        if ($followerId <= 0 || $followingId <= 0 || $followerId === $followingId) {
            return 0;
        }

        return $this->inupdel(
            "DELETE FROM follows WHERE follower_id = ? AND following_id = ?",
            [$followerId, $followingId]
        );
    }

    public function removeFollower(int $currentUserId, int $followerId): int {
        if ($currentUserId <= 0 || $followerId <= 0 || $currentUserId === $followerId) {
            return 0;
        }

        return $this->inupdel(
            "DELETE FROM follows WHERE follower_id = ? AND following_id = ?",
            [$followerId, $currentUserId]
        );
    }

    public function isFollowing(int $followerId, int $followingId): bool {
        $rows = $this->selectQuery(
            "SELECT id FROM follows WHERE follower_id = ? AND following_id = ? LIMIT 1",
            [$followerId, $followingId]
        );

        return !empty($rows);
    }

    private function createFollowNotification(int $userId, int $fromUserId): void {
        if ($userId <= 0 || $fromUserId <= 0 || $userId === $fromUserId) {
            return;
        }

        $this->inupdel(
            "INSERT INTO notifications (user_id, from_user_id, type, created_at) VALUES (?, ?, 'follow', NOW())",
            [$userId, $fromUserId]
        );
    }

    public function toggleFollow(int $followerId, int $followingId): array {
        if ($this->isBlockedBetween($followerId, $followingId)) {
            return [
                'success' => false,
                'state' => 'blocked',
                'message' => 'You cannot follow this user.',
                'followers' => $this->getFollowersCount($followingId),
                'following' => $this->getFollowingCount($followingId),
                'current_followers' => $this->getFollowersCount($followerId),
                'current_following' => $this->getFollowingCount($followerId),
            ];
        }

        if ($this->isFollowing($followerId, $followingId)) {
            $this->unfollow($followerId, $followingId);
            $state = 'follow';
        } else {
            $this->follow($followerId, $followingId);
            $state = 'following';
        }

        return [
            'success' => true,
            'state' => $state,
            'followers' => $this->getFollowersCount($followingId),
            'following' => $this->getFollowingCount($followingId),
            'current_followers' => $this->getFollowersCount($followerId),
            'current_following' => $this->getFollowingCount($followerId),
        ];
    }
    public function getSuggestedUsers(int $currentUserId): array {
        return $this->selectQuery(
            "SELECT * FROM users
             WHERE id != ?
             AND COALESCE(show_account_suggestions, 1) = 1
             AND id NOT IN (
                SELECT following_id FROM follows WHERE follower_id = ?
             )
             AND NOT EXISTS (
                SELECT 1 FROM blocked_users
                WHERE (blocker_id = ? AND blocked_id = users.id)
                   OR (blocker_id = users.id AND blocked_id = ?)
             )
             LIMIT 10",
            [$currentUserId, $currentUserId, $currentUserId, $currentUserId]
        );
    }
    public function getFollowersCount(int $userId): int {
        $rows = $this->selectQuery(
            "SELECT COUNT(*) as cnt FROM follows WHERE following_id = ?",
            [$userId]
        );
        return (int)($rows[0]['cnt'] ?? 0);
    }
    public function getFollowingCount(int $userId): int {
        $rows = $this->selectQuery(
            "SELECT COUNT(*) as cnt FROM follows WHERE follower_id = ?",
            [$userId]
        );
        return (int)($rows[0]['cnt'] ?? 0);
    }

    public function getFollowers(int $userId, int $currentUserId): array {
        return $this->selectQuery(
            "SELECT u.id,
                    u.username,
                    u.full_name,
                    u.profile_pic,
                    EXISTS(
                        SELECT 1
                        FROM follows f2
                        WHERE f2.follower_id = ?
                        AND f2.following_id = u.id
                    ) AS is_following
             FROM follows f
             JOIN users u ON u.id = f.follower_id
             WHERE f.following_id = ?
             AND NOT EXISTS (
                SELECT 1 FROM blocked_users
                WHERE (blocker_id = ? AND blocked_id = u.id)
                   OR (blocker_id = u.id AND blocked_id = ?)
             )
             ORDER BY f.created_at DESC",
            [$currentUserId, $userId, $currentUserId, $currentUserId]
        );
    }

    public function getFollowing(int $userId, int $currentUserId): array {
        return $this->selectQuery(
            "SELECT u.id,
                    u.username,
                    u.full_name,
                    u.profile_pic,
                    EXISTS(
                        SELECT 1
                        FROM follows f2
                        WHERE f2.follower_id = ?
                        AND f2.following_id = u.id
                    ) AS is_following
             FROM follows f
             JOIN users u ON u.id = f.following_id
             WHERE f.follower_id = ?
             AND NOT EXISTS (
                SELECT 1 FROM blocked_users
                WHERE (blocker_id = ? AND blocked_id = u.id)
                   OR (blocker_id = u.id AND blocked_id = ?)
             )
             ORDER BY f.created_at DESC",
            [$currentUserId, $userId, $currentUserId, $currentUserId]
        );
    }
}
?>
