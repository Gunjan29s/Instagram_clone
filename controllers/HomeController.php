<?php
// controllers/HomeController.php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/PostModel.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/FollowModel.php';

class HomeController extends BaseController {

    private PostModel $postModel;
    private UserModel $userModel;
    private FollowModel $followModel;

    public function __construct() {
        $this->postModel   = new PostModel();
        $this->userModel   = new UserModel();
        $this->followModel = new FollowModel();
    }

    // Home feed dikhana
    public function index(): void {
        $currentUserId   = $this->requireLogin();
        $currentUser     = $this->userModel->getUserById($currentUserId);
        $posts           = $this->postModel->getHomePosts($currentUserId);
        $commentsByPost  = $this->postModel->getCommentsByPostIds(array_column($posts, 'id'), 50);
        $stories         = $this->postModel->getActiveStories($currentUserId);
        $ownStoryIds     = [];
        foreach ($stories as $story) {
            if ((int) ($story['user_id'] ?? 0) === $currentUserId) {
                $ownStoryIds[] = (int) ($story['id'] ?? 0);
            }
        }
        $storyViewers    = $this->postModel->getStoryViewersByStoryIds($currentUserId, $ownStoryIds);
        $suggestedUsers  = $this->followModel->getSuggestedUsers($currentUserId);

        $this->render('home', [
            'currentUser'    => $currentUser,
            'posts'          => $posts,
            'commentsByPost' => $commentsByPost,
            'stories'        => $stories,
            'storyViewers'   => $storyViewers,
            'suggestedUsers' => $suggestedUsers,
            'user_id'        => $currentUserId,
        ]);
    }
}
?>
