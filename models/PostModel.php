<?php

require_once __DIR__ . '/BaseModel.php';

class PostModel extends BaseModel {

    public function __construct() {
        parent::__construct();
        $this->ensureInteractionTables();
        $this->ensureStoriesTable();
        $this->normalizeMediaTypes();
        $this->cleanupExpiredStories();
    }

    private function ensureInteractionTables(): void {
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

        try {
            $this->db->exec("ALTER TABLE notifications MODIFY type ENUM('like','comment','follow','mention','message','message_request_declined','follow_request') NOT NULL");
        } catch (PDOException $e) {
            // Notifications table may not exist yet or may already be up to date.
        }
    }

    private function ensureStoriesTable(): void {
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
            "CREATE TABLE IF NOT EXISTS story_views (
                id INT AUTO_INCREMENT PRIMARY KEY,
                story_id INT NOT NULL,
                user_id INT NOT NULL,
                viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_story_view (story_id, user_id),
                FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )"
        );
    }

    private function normalizeMediaTypes(): void {
        $this->db->exec(
            "UPDATE posts
             SET media_type = 'video'
             WHERE media_path REGEXP '\\\\.(mp4|webm|ogg|mov|avi|m4v|3gp|mkv)$'"
        );

        $this->db->exec(
            "UPDATE stories
             SET media_type = 'video'
             WHERE media_path REGEXP '\\\\.(mp4|webm|ogg|mov|avi|m4v|3gp|mkv)$'"
        );
    }

    private function cleanupExpiredStories(): void {
        try {
            $this->db->exec("DELETE FROM stories WHERE expires_at < NOW()");
        } catch (PDOException $e) {
            // Story cleanup is best-effort and must not block page rendering.
        }
    }
    
    public function getAllPosts(): array {
        return $this->selectQuery(
            "SELECT posts.*, 
                    IFNULL(posts.caption, '') AS caption,
                    users.username, 
                    users.profile_pic
             FROM posts
             JOIN users ON posts.user_id = users.id
             ORDER BY posts.created_at DESC"
        );
    }

    public function getHomePosts(int $currentUserId): array {
        // Sirf apne posts + jinhe follow kiya unke posts
        return $this->selectQuery(
            "SELECT posts.*,
                    IFNULL(posts.caption, '') AS caption,
                    users.username,
                    users.profile_pic,
                    (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS total_likes,
                    (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) AS total_comments,
                    EXISTS(
                        SELECT 1 FROM likes
                        WHERE likes.post_id = posts.id AND likes.user_id = ?
                    ) AS is_liked,
                    EXISTS(
                        SELECT 1 FROM saved_posts
                        WHERE saved_posts.post_id = posts.id AND saved_posts.user_id = ?
                    ) AS is_saved,
                    EXISTS(
                        SELECT 1 FROM follows
                        WHERE follows.follower_id = ? AND follows.following_id = posts.user_id
                    ) AS is_following
             FROM posts
             JOIN users ON posts.user_id = users.id
             WHERE (
                posts.user_id = ?
                OR posts.user_id IN (
                    SELECT following_id FROM follows WHERE follower_id = ?
                )
             )
             AND NOT EXISTS (
                SELECT 1 FROM blocked_users
                WHERE (blocker_id = ? AND blocked_id = posts.user_id)
                   OR (blocker_id = posts.user_id AND blocked_id = ?)
             )
             ORDER BY posts.id DESC",
            [$currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId]
        );
    }

    public function getExplorePosts(string $search = '', int $currentUserId = 0): array {
        if ($search !== '') {
            $keyword = '%' . $search . '%';
            return $this->selectQuery(
                "SELECT posts.*,
                        IFNULL(posts.caption, '') AS caption,
                        users.username,
                        users.profile_pic,
                        (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS total_likes,
                        (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) AS total_comments,
                        EXISTS(
                            SELECT 1 FROM likes
                            WHERE likes.post_id = posts.id AND likes.user_id = ?
                        ) AS is_liked,
                        EXISTS(
                            SELECT 1 FROM saved_posts
                            WHERE saved_posts.post_id = posts.id AND saved_posts.user_id = ?
                        ) AS is_saved
                 FROM posts
                 JOIN users ON posts.user_id = users.id
                 WHERE (
                    users.username LIKE ?
                    OR posts.caption LIKE ?
                    OR posts.location LIKE ?
                 )
                 AND NOT EXISTS (
                    SELECT 1 FROM blocked_users
                    WHERE (blocker_id = ? AND blocked_id = posts.user_id)
                       OR (blocker_id = posts.user_id AND blocked_id = ?)
                 )
                 ORDER BY posts.id DESC",
                [$currentUserId, $currentUserId, $keyword, $keyword, $keyword, $currentUserId, $currentUserId]
            );
        }

        return $this->selectQuery(
            "SELECT posts.*,
                    IFNULL(posts.caption, '') AS caption,
                    users.username,
                    users.profile_pic,
                    (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS total_likes,
                    (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) AS total_comments,
                    EXISTS(
                        SELECT 1 FROM likes
                        WHERE likes.post_id = posts.id AND likes.user_id = ?
                    ) AS is_liked,
                    EXISTS(
                        SELECT 1 FROM saved_posts
                        WHERE saved_posts.post_id = posts.id AND saved_posts.user_id = ?
                    ) AS is_saved
             FROM posts
             JOIN users ON posts.user_id = users.id
             WHERE NOT EXISTS (
                SELECT 1 FROM blocked_users
                WHERE (blocker_id = ? AND blocked_id = posts.user_id)
                   OR (blocker_id = posts.user_id AND blocked_id = ?)
             )
             ORDER BY RAND()",
            [$currentUserId, $currentUserId, $currentUserId, $currentUserId]
        );
    }
    
    public function getPostsByUser(int $userId): array {
        return $this->selectQuery(
            "SELECT posts.*,
                    IFNULL(posts.caption, '') AS caption,
                    (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS total_likes,
                    (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) AS total_comments
             FROM posts
             WHERE user_id = ?
             ORDER BY id DESC",
            [$userId]
        );
    }
    
    public function createPost(int $userId, string $imageUrl, string $caption, string $location = '', string $tags = ''): int {
        $mediaType = preg_match('/\.(mp4|webm|ogg|mov|avi|m4v|3gp|mkv)$/i', $imageUrl) ? 'video' : 'image';
        $stmt = $this->db->prepare(
            "INSERT INTO posts (user_id, media_path, media_type, caption, location, created_at) VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$userId, $imageUrl, $mediaType, $caption, $location]);

        $postId = (int) $this->db->lastInsertId();
        if ($postId > 0) {
            $this->syncMentionTags($postId, $userId, $caption . ' ' . $tags);
        }

        return $stmt->rowCount();
    }

    public function createStory(int $userId, string $mediaPath, string $caption = ''): int {
        $mediaType = preg_match('/\.(mp4|webm|ogg|mov|avi|m4v|3gp|mkv)$/i', $mediaPath) ? 'video' : 'image';

        return $this->inupdel(
            "INSERT INTO stories (user_id, media_path, media_type, caption, created_at, expires_at)
             VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))",
            [$userId, $mediaPath, $mediaType, $caption]
        );
    }

    public function getActiveStories(int $currentUserId): array {
        $this->db->exec("DELETE FROM stories WHERE expires_at <= NOW()");

        // Sirf apni stories + jinhe follow kiya unki stories
        return $this->selectQuery(
            "SELECT stories.*,
                    users.username,
                    users.profile_pic,
                    EXISTS(
                        SELECT 1 FROM story_views
                        WHERE story_views.story_id = stories.id
                          AND story_views.user_id = ?
                    ) AS viewed_by_current_user
             FROM stories
             JOIN users ON stories.user_id = users.id
             WHERE stories.expires_at > NOW()
               AND (
                   stories.user_id = ?
                   OR stories.user_id IN (
                       SELECT following_id FROM follows WHERE follower_id = ?
                   )
               )
               AND NOT EXISTS (
                   SELECT 1 FROM blocked_users
                   WHERE (blocker_id = ? AND blocked_id = stories.user_id)
                      OR (blocker_id = stories.user_id AND blocked_id = ?)
               )
             ORDER BY (stories.user_id = ?) DESC,
                      stories.created_at DESC
             LIMIT 30",
            [$currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId]
        );
    }

    public function getStoryViewersByStoryIds(int $ownerId, array $storyIds): array {
        $storyIds = array_values(array_unique(array_filter(array_map('intval', $storyIds))));
        if ($ownerId <= 0 || empty($storyIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($storyIds), '?'));
        $rows = $this->selectQuery(
            "SELECT story_views.story_id,
                    story_views.user_id,
                    story_views.viewed_at,
                    users.username,
                    users.full_name,
                    users.profile_pic
             FROM story_views
             JOIN stories ON stories.id = story_views.story_id
             JOIN users ON users.id = story_views.user_id
             WHERE stories.user_id = ?
               AND story_views.story_id IN ($placeholders)
             ORDER BY story_views.viewed_at DESC",
            array_merge([$ownerId], $storyIds)
        );

        $viewersByStory = [];
        foreach ($rows as $row) {
            $storyId = (int) ($row['story_id'] ?? 0);
            if ($storyId <= 0) {
                continue;
            }

            $viewersByStory[$storyId][] = $row;
        }

        return $viewersByStory;
    }
    
    public function markStoriesSeen(int $userId, array $storyIds): int {
        $storyIds = array_values(array_unique(array_filter(array_map('intval', $storyIds))));
        if ($userId <= 0 || empty($storyIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($storyIds), '?'));
        $visibleRows = $this->selectQuery(
            "SELECT id
             FROM stories
             WHERE id IN ($placeholders)
               AND expires_at > NOW()
               AND user_id <> ?",
            array_merge($storyIds, [$userId])
        );

        $visibleIds = array_map('intval', array_column($visibleRows, 'id'));
        if (empty($visibleIds)) {
            return 0;
        }

        $values = implode(',', array_fill(0, count($visibleIds), '(?, ?, NOW())'));
        $params = [];
        foreach ($visibleIds as $storyId) {
            $params[] = $storyId;
            $params[] = $userId;
        }

        return $this->inupdel(
            "INSERT INTO story_views (story_id, user_id, viewed_at)
             VALUES $values
             ON DUPLICATE KEY UPDATE viewed_at = VALUES(viewed_at)",
            $params
        );
    }
    
    public function deletePost(int $postId, int $userId): int {
        return $this->inupdel(
            "DELETE FROM posts WHERE id = ? AND user_id = ?",
            [$postId, $userId]
        );
    }

    public function deleteStory(int $storyId, int $userId): int {
        return $this->inupdel(
            "DELETE FROM stories WHERE id = ? AND user_id = ?",
            [$storyId, $userId]
        );
    }

    public function deleteComment(int $commentId, int $userId): int {
        return $this->inupdel(
            "DELETE comments
             FROM comments
             JOIN posts ON posts.id = comments.post_id
             WHERE comments.id = ?
             AND (comments.user_id = ? OR posts.user_id = ?)",
            [$commentId, $userId, $userId]
        );
    }

    public function getCommentPostId(int $commentId): int {
        $rows = $this->selectQuery("SELECT post_id FROM comments WHERE id = ? LIMIT 1", [$commentId]);
        return (int) ($rows[0]['post_id'] ?? 0);
    }

    public function deletePostByAdmin(int $postId): int {
        return $this->inupdel("DELETE FROM posts WHERE id = ?", [$postId]);
    }

    public function deleteStoryByAdmin(int $storyId): int {
        return $this->inupdel("DELETE FROM stories WHERE id = ?", [$storyId]);
    }

    public function addComment(int $userId, int $postId, string $comment): int {
        $stmt = $this->db->prepare(
            "INSERT INTO comments (user_id, post_id, comment, created_at) VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute([$userId, $postId, $comment]);
        $commentId = (int) $this->db->lastInsertId();

        if ($commentId > 0) {
            $this->notifyPostOwner($postId, $userId, 'comment');
        }

        return $commentId;
    }

    public function getCommentById(int $commentId): array {
        $rows = $this->selectQuery(
            "SELECT c.id,
                    c.post_id,
                    c.user_id,
                    c.comment,
                    c.created_at,
                    u.username
             FROM comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.id = ?
             LIMIT 1",
            [$commentId]
        );

        return $rows[0] ?? [];
    }

    public function getCommentsByPostIds(array $postIds, int $limitPerPost = 3): array {
        $postIds = array_values(array_unique(array_filter(array_map('intval', $postIds))));

        if (!$postIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $rows = $this->selectQuery(
            "SELECT c.id,
                    c.post_id,
                    c.user_id,
                    c.comment,
                    c.created_at,
                    u.username
             FROM comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.post_id IN ($placeholders)
             ORDER BY c.created_at ASC, c.id ASC",
            $postIds
        );

        $grouped = [];
        foreach ($rows as $row) {
            $postId = (int) $row['post_id'];
            $grouped[$postId][] = $row;
        }

        foreach ($grouped as $postId => $comments) {
            $grouped[$postId] = array_slice($comments, -$limitPerPost);
        }

        return $grouped;
    }

    public function countComments(int $postId): int {
        $rows = $this->selectQuery("SELECT COUNT(*) AS total FROM comments WHERE post_id = ?", [$postId]);
        return (int) ($rows[0]['total'] ?? 0);
    }

    public function postExists(int $postId): bool {
        if ($postId <= 0) {
            return false;
        }

        $rows = $this->selectQuery("SELECT id FROM posts WHERE id = ? LIMIT 1", [$postId]);
        return !empty($rows);
    }

    public function toggleLike(int $userId, int $postId): array {
        $existing = $this->selectQuery(
            "SELECT id FROM likes WHERE user_id = ? AND post_id = ? LIMIT 1",
            [$userId, $postId]
        );

        if ($existing) {
            $this->inupdel("DELETE FROM likes WHERE user_id = ? AND post_id = ?", [$userId, $postId]);
            $status = 'unliked';
        } else {
            $this->inupdel("INSERT IGNORE INTO likes (user_id, post_id, created_at) VALUES (?, ?, NOW())", [$userId, $postId]);
            $this->notifyPostOwner($postId, $userId, 'like');
            $status = 'liked';
        }

        return [
            'status' => $status,
            'total' => $this->countLikes($postId),
        ];
    }

    public function countLikes(int $postId): int {
        $rows = $this->selectQuery("SELECT COUNT(*) AS total FROM likes WHERE post_id = ?", [$postId]);
        return (int) ($rows[0]['total'] ?? 0);
    }

    public function toggleSave(int $userId, int $postId): array {
        $existing = $this->selectQuery(
            "SELECT id FROM saved_posts WHERE user_id = ? AND post_id = ? LIMIT 1",
            [$userId, $postId]
        );

        if ($existing) {
            $this->inupdel("DELETE FROM saved_posts WHERE user_id = ? AND post_id = ?", [$userId, $postId]);
            $status = 'unsaved';
        } else {
            $this->inupdel("INSERT IGNORE INTO saved_posts (user_id, post_id, created_at) VALUES (?, ?, NOW())", [$userId, $postId]);
            $status = 'saved';
        }

        return ['success' => true, 'status' => $status];
    }

    public function getSavedPosts(int $userId): array {
        return $this->selectQuery(
            "SELECT posts.*,
                    IFNULL(posts.caption, '') AS caption,
                    users.username,
                    users.profile_pic,
                    (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS total_likes,
                    (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) AS total_comments,
                    1 AS is_saved
             FROM saved_posts
             JOIN posts ON posts.id = saved_posts.post_id
             JOIN users ON users.id = posts.user_id
             WHERE saved_posts.user_id = ?
             ORDER BY saved_posts.created_at DESC",
            [$userId]
        );
    }

    public function getTaggedPosts(int $userId): array {
        return $this->selectQuery(
            "SELECT DISTINCT posts.*,
                    IFNULL(posts.caption, '') AS caption,
                    users.username,
                    users.profile_pic,
                    (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS total_likes,
                    (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) AS total_comments
             FROM posts
             JOIN users ON users.id = posts.user_id
             JOIN post_tags ON post_tags.post_id = posts.id
             WHERE post_tags.user_id = ?
             ORDER BY posts.created_at DESC",
            [$userId]
        );
    }

    private function syncMentionTags(int $postId, int $fromUserId, string $caption): void {
        preg_match_all('/(?<![A-Za-z0-9_])@([A-Za-z0-9_]{1,50})/', $caption, $matches);
        $usernames = array_values(array_unique(array_map('strtolower', $matches[1] ?? [])));

        if (!$usernames) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $params = array_merge($usernames, [$fromUserId]);
        $users = $this->selectQuery(
            "SELECT id, LOWER(username) AS username
             FROM users
             WHERE LOWER(username) IN ($placeholders)
             AND EXISTS (
                SELECT 1
                FROM follows
                WHERE follows.follower_id = users.id
                AND follows.following_id = ?
             )",
            $params
        );

        foreach ($users as $user) {
            $taggedUserId = (int) ($user['id'] ?? 0);
            if ($taggedUserId <= 0) {
                continue;
            }

            $this->inupdel(
                "INSERT IGNORE INTO post_tags (post_id, user_id, created_at) VALUES (?, ?, NOW())",
                [$postId, $taggedUserId]
            );

            if ($taggedUserId !== $fromUserId) {
                $this->inupdel(
                    "INSERT INTO notifications (user_id, from_user_id, type, post_id, created_at) VALUES (?, ?, 'mention', ?, NOW())",
                    [$taggedUserId, $fromUserId, $postId]
                );
            }
        }
    }

    private function notifyPostOwner(int $postId, int $fromUserId, string $type): void {
        $rows = $this->selectQuery("SELECT user_id FROM posts WHERE id = ? LIMIT 1", [$postId]);
        $ownerId = (int) ($rows[0]['user_id'] ?? 0);

        if ($ownerId > 0 && $ownerId !== $fromUserId) {
            $this->inupdel(
                "INSERT INTO notifications (user_id, from_user_id, type, post_id, created_at) VALUES (?, ?, ?, ?, NOW())",
                [$ownerId, $fromUserId, $type, $postId]
            );
        }
    }
}
?>
