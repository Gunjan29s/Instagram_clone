<?php
// controllers/FollowController.php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/FollowModel.php';

class FollowController extends BaseController {

    private FollowModel $followModel;

    public function __construct() {
        $this->followModel = new FollowModel();
    }

    // Suggested users wala page
    public function index(): void {
        $this->startSession();
        $currentUserId  = $_SESSION['user_id'] ?? 1;
        $suggestedUsers = $this->followModel->getSuggestedUsers($currentUserId);
        $this->render('follow', ['suggestedUsers' => $suggestedUsers]);
    }

    // Follow action (AJAX se call hoga)
    public function follow(): void {
        $followerId  = $this->requireLogin();
        require_csrf();
        $followingId = (int)$this->post('following_id');

        $rows = $this->followModel->follow($followerId, $followingId);
        $this->jsonResponse([
            'success' => $rows > 0 || $this->followModel->isFollowing($followerId, $followingId),
            'state' => 'following',
            'followers' => $this->followModel->getFollowersCount($followingId),
            'following' => $this->followModel->getFollowingCount($followingId),
            'current_followers' => $this->followModel->getFollowersCount($followerId),
            'current_following' => $this->followModel->getFollowingCount($followerId),
        ]);
    }

    // Unfollow action (AJAX se call hoga)
    public function unfollow(): void {
        $followerId  = $this->requireLogin();
        require_csrf();
        $followingId = (int)$this->post('following_id');

        $rows = $this->followModel->unfollow($followerId, $followingId);
        $this->jsonResponse([
            'success' => $rows > 0 || !$this->followModel->isFollowing($followerId, $followingId),
            'state' => 'follow',
            'followers' => $this->followModel->getFollowersCount($followingId),
            'following' => $this->followModel->getFollowingCount($followingId),
            'current_followers' => $this->followModel->getFollowersCount($followerId),
            'current_following' => $this->followModel->getFollowingCount($followerId),
        ]);
    }

    public function toggle(): void {
        $followerId = $this->requireLogin();
        require_csrf();
        $followingId = (int)$this->post('following_id');

        if ($followingId <= 0 || $followingId === $followerId) {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid user'], 400);
        }

        $this->jsonResponse($this->followModel->toggleFollow($followerId, $followingId));
    }
}
?>
