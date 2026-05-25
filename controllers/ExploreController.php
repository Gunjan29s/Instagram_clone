<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/PostModel.php';

class ExploreController extends BaseController {
    private PostModel $postModel;

    public function __construct() {
        $this->postModel = new PostModel();
    }

    public function index(): void {
        $currentUserId = $this->requireLogin();
        $search = $this->get('search');

        $this->render('explore', [
            'search' => $search,
            'posts' => $this->postModel->getExplorePosts($search, $currentUserId),
        ]);
    }
}
?>
