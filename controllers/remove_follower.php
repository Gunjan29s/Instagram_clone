<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/FollowModel.php';

class RemoveFollowerController extends BaseController {
    public function store(): void {
        $currentUserId = $this->requireLogin();
        require_csrf();
        $followerId = (int) ($_POST['user_id'] ?? 0);

        if ($followerId <= 0 || $followerId === $currentUserId) {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid user'], 400);
        }

        $followModel = new FollowModel();
        $rows = $followModel->removeFollower($currentUserId, $followerId);

        $this->jsonResponse([
            'success' => $rows > 0,
            'followers' => $followModel->getFollowersCount($currentUserId),
        ]);
    }
}

(new RemoveFollowerController())->store();
