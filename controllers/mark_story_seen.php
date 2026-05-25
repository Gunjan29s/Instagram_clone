<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/PostModel.php';

class MarkStorySeenController extends BaseController {
    public function store(): void {
        $userId = $this->requireLogin();
        require_csrf();
        $rawIds = $_POST['story_ids'] ?? '';

        if (is_array($rawIds)) {
            $storyIds = $rawIds;
        } else {
            $storyIds = preg_split('/\s*,\s*/', (string) $rawIds, -1, PREG_SPLIT_NO_EMPTY);
        }

        (new PostModel())->markStoriesSeen($userId, $storyIds);
        $this->jsonResponse(['success' => true]);
    }
}

(new MarkStorySeenController())->store();
