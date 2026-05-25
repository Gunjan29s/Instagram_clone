<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/PostModel.php';
require_once __DIR__ . '/../models/FollowModel.php';

class ProfileController extends BaseController {
    private UserModel $userModel;
    private PostModel $postModel;
    private FollowModel $followModel;

    public function __construct() {
        $this->userModel = new UserModel();
        $this->postModel = new PostModel();
        $this->followModel = new FollowModel();
    }

    public function index(): void {
        $currentUserId = $this->requireLogin();
        $profileUserId = (int) ($_GET['id'] ?? $currentUserId);
        $profile = $this->userModel->getProfile($profileUserId, $currentUserId);

        if (empty($profile)) {
            http_response_code(404);
            echo 'Profile not found';
            return;
        }

        $posts = $this->postModel->getPostsByUser($profileUserId);

        $this->render('profile', [
            'current_user_id' => $currentUserId,
            'profile_user_id' => $profileUserId,
            'profile' => $profile,
            'posts' => $posts,
            'commentsByPost' => $this->postModel->getCommentsByPostIds(array_column($posts, 'id'), 50),
            'followersList' => $this->followModel->getFollowers($profileUserId, $currentUserId),
            'followingList' => $this->followModel->getFollowing($profileUserId, $currentUserId),
        ]);
    }
}
?>
