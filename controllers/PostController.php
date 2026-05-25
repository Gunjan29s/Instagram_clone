<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/PostModel.php';

class PostController extends BaseController {

    private const MAX_UPLOAD_BYTES = 2 * 1024 * 1024 * 1024;
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'webm', 'm4v', '3gp', 'mkv'];

    private PostModel $postModel;

    public function __construct() {
        $this->postModel = new PostModel();
    }

    public function createPage(): void {
        $this->requireLogin();
        $this->render('create_post');
    }

    public function store(): void {
        $userId  = $this->requireLogin();
        require_csrf();
        $caption = $this->rawPost('caption');
        $location = $this->rawPost('location');
        $tags = $this->rawPost('tags');
        $uploadType = $this->rawPost('upload_type', 'post');
        if (!in_array($uploadType, ['post', 'story', 'reel'], true)) {
            $uploadType = 'post';
        }

        if (empty($_FILES['media']['name']) || ($_FILES['media']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->render('create_post', ['error' => $this->uploadErrorMessage((int) ($_FILES['media']['error'] ?? UPLOAD_ERR_NO_FILE))]);
            return;
        }

        $file = $_FILES['media'];
        $fileSize = (int) $file['size'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = array_merge(self::IMAGE_EXTENSIONS, self::VIDEO_EXTENSIONS);

        if (!in_array($extension, $allowed, true)) {
            $this->render('create_post', ['error' => 'Unsupported file format.']);
            return;
        }

        $isVideo = in_array($extension, self::VIDEO_EXTENSIONS, true);
        if (!$this->isAllowedUploadMime($file['tmp_name'], $isVideo)) {
            $this->render('create_post', ['error' => 'Uploaded file type is not allowed.']);
            return;
        }

        if ($uploadType === 'reel' && !$isVideo) {
            $this->render('create_post', ['error' => 'Please select a video file for reels.']);
            return;
        }

        if ($fileSize > self::MAX_UPLOAD_BYTES) {
            $this->render('create_post', ['error' => 'File size must be under 2GB.']);
            return;
        }

        $uploadFolder = $uploadType === 'story' ? 'stories' : 'posts';
        $uploadDir = __DIR__ . '/../uploads/' . $uploadFolder . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($file['name']));
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
            $this->render('create_post', ['error' => 'Failed to upload file.']);
            return;
        }
        if (!$isVideo) {
            $this->optimizeUploadedImage($uploadDir . $fileName, $extension);
        }

        $imageUrl = 'uploads/' . $uploadFolder . '/' . $fileName;

        if ($uploadType === 'story') {
            $rows = $this->postModel->createStory($userId, $imageUrl, $caption);
            if ($rows > 0) {
                $this->redirect(app_url('views/home.php'));
            }
            $this->render('create_post', ['error' => 'Story could not be uploaded. Please try again.']);
            return;
        }

        $rows = $this->postModel->createPost($userId, $imageUrl, $caption, $location, $tags);
        if ($rows > 0) {
            $this->redirect(app_url($uploadType === 'reel' ? 'views/reels.php' : 'views/home.php'));
        } else {
            $this->render('create_post', ['error' => 'Post could not be shared. Please try again.']);
        }
    }

    private function optimizeUploadedImage(string $path, string $extension): void {
        if (!function_exists('imagecreatefromstring') || !is_file($path)) {
            return;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return;
        }

        $image = @imagecreatefromstring($contents);
        if (!$image) {
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $maxDimension = 1600;
        if ($width > $maxDimension || $height > $maxDimension) {
            $ratio = min($maxDimension / $width, $maxDimension / $height);
            $newWidth = max(1, (int) round($width * $ratio));
            $newHeight = max(1, (int) round($height * $ratio));
            $resized = imagescale($image, $newWidth, $newHeight);
            if ($resized) {
                imagedestroy($image);
                $image = $resized;
            }
        }

        if (in_array($extension, ['jpg', 'jpeg'], true)) {
            imagejpeg($image, $path, 85);
        } elseif ($extension === 'png') {
            imagepng($image, $path, 6);
        } elseif ($extension === 'webp' && function_exists('imagewebp')) {
            imagewebp($image, $path, 85);
        }
        imagedestroy($image);
    }

    private function isAllowedUploadMime(string $tmpPath, bool $isVideo): bool {
        $allowedImageMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $allowedVideoMimes = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm', 'video/x-m4v', 'video/3gpp', 'video/x-matroska'];
        $allowedMimes = $isVideo ? $allowedVideoMimes : $allowedImageMimes;

        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string) finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
            }
        }

        return $mime !== '' && in_array($mime, $allowedMimes, true);
    }

    private function uploadErrorMessage(int $errorCode): string {
        if (in_array($errorCode, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return 'The uploaded file is larger than the server upload limit.';
        }

        if ($errorCode === UPLOAD_ERR_PARTIAL) {
            return 'The upload did not complete. Please try again.';
        }

        return 'Please select an image or video.';
    }

    public function addComment(): void {
        $userId = $this->requireLogin();
        require_csrf();
        $postId = (int) ($_POST['post_id'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

        if ($postId > 0 && $comment !== '' && $this->postModel->postExists($postId)) {
            $commentId = $this->postModel->addComment($userId, $postId, $comment);

            if ($isAjax) {
                $row = $this->postModel->getCommentById($commentId);
                $this->jsonResponse([
                    'status' => 'ok',
                    'comment' => [
                        'id' => $commentId,
                        'username' => $row['username'] ?? '',
                        'comment' => $row['comment'] ?? $comment,
                    ],
                    'total' => $this->postModel->countComments($postId),
                ]);
            }
        }

        if ($isAjax) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Invalid comment'], 400);
        }

        $this->redirectBack(app_url('views/home.php'));
    }

    public function toggleLike(): void {
        $userId = $this->requireLogin();
        require_csrf();
        $postId = (int) ($_POST['post_id'] ?? 0);

        if ($postId <= 0 || !$this->postModel->postExists($postId)) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Invalid post', 'total' => 0], 400);
        }

        $this->jsonResponse($this->postModel->toggleLike($userId, $postId));
    }

    public function toggleSave(): void {
        $userId = $this->requireLogin();
        require_csrf();
        $postId = (int) ($_POST['post_id'] ?? 0);

        if ($postId <= 0 || !$this->postModel->postExists($postId)) {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid post'], 400);
        }

        $this->jsonResponse($this->postModel->toggleSave($userId, $postId));
    }

    public function delete(): void {
        $userId = $this->requireLogin();
        require_csrf();
        $postId = (int)$this->post('post_id');

        $rows = $this->postModel->deletePost($postId, $userId);
        $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

        if ($isAjax) {
            $this->jsonResponse(['success' => $rows > 0]);
        }

        $this->redirectBack(app_url('views/home.php'));
    }

    public function deleteStory(): void {
        $userId = $this->requireLogin();
        require_csrf();
        $storyId = (int)$this->post('story_id');

        $rows = $this->postModel->deleteStory($storyId, $userId);
        $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

        if ($isAjax) {
            $this->jsonResponse(['success' => $rows > 0]);
        }

        $this->redirectBack(app_url('views/home.php'));
    }

    public function deleteComment(): void {
        $userId = $this->requireLogin();
        require_csrf();
        $commentId = (int)$this->post('comment_id');
        $postId = $this->postModel->getCommentPostId($commentId);

        $rows = $this->postModel->deleteComment($commentId, $userId);
        $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

        if ($isAjax) {
            $this->jsonResponse([
                'success' => $rows > 0,
                'post_id' => $postId,
                'total' => $postId > 0 ? $this->postModel->countComments($postId) : 0,
            ]);
        }

        $this->redirectBack(app_url('views/home.php'));
    }
}
?>
