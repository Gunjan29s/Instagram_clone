<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../config/mailing.php';

class AuthController extends BaseController {

    private ?UserModel $userModel = null;

    private function userModel(): UserModel {
        if ($this->userModel === null) {
            $this->userModel = new UserModel();
        }

        return $this->userModel;
    }

    public function signupPage(): void {
        $this->startSession();
        if (isset($_SESSION['user_id'])) {
            $this->redirect(app_url('views/home.php'));
        }
        $this->render('sign_up', ['captcha' => $this->prepareCaptcha('signup')]);
    }

    public function signup(): void {
        $this->startSession();
        require_csrf();
        $username = $this->rawPost('username');
        $email    = $this->rawPost('email');
        $password = $this->rawPost('password');
        $fullName = $this->rawPost('full_name');

        $data = compact('username', 'email') + ['full_name' => $fullName];

        if (!$this->verifyCaptcha('signup', $_POST['captcha_answer'] ?? '')) {
            $this->render('sign_up', $data + [
                'error' => 'Captcha answer is incorrect.',
                'captcha' => $this->prepareCaptcha('signup'),
            ]);
            return;
        }

        if (empty($username) || empty($email) || empty($password) || empty($fullName)) {
            $this->render('sign_up', $data + ['error' => 'All fields are required.', 'captcha' => $this->prepareCaptcha('signup')]);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->render('sign_up', $data + ['error' => 'Enter a valid email address.', 'captcha' => $this->prepareCaptcha('signup')]);
            return;
        }

        if (!$this->emailDomainAcceptsMail($email)) {
            $this->render('sign_up', $data + ['error' => 'Please enter a working email address.', 'captcha' => $this->prepareCaptcha('signup')]);
            return;
        }

        if (strlen($password) < 8) {
            $this->render('sign_up', $data + ['error' => 'Password must be at least 8 characters.', 'captcha' => $this->prepareCaptcha('signup')]);
            return;
        }

        if (!empty($this->userModel()->getUserByUsername($username)) || !empty($this->userModel()->getUserByEmail($email))) {
            $this->render('sign_up', $data + ['error' => 'Username or email already exists.', 'captcha' => $this->prepareCaptcha('signup')]);
            return;
        }

        try {
            $rows = $this->userModel()->createUser($username, $email, $password, $fullName);
        } catch (PDOException $e) {
            $this->render('sign_up', $data + ['error' => 'Signup failed. Please try again.', 'captcha' => $this->prepareCaptcha('signup')]);
            return;
        }

        if ($rows <= 0) {
            $this->render('sign_up', $data + ['error' => 'Signup failed. Please try again.', 'captcha' => $this->prepareCaptcha('signup')]);
            return;
        }

        $user = $this->userModel()->getUserByEmail($email);
        $this->sendSignupEmails($user);

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['profile_pic'] = $user['profile_pic'] ?? '';
        app_set_auth_cookie((int) $user['id'], $user['password']);
        $this->userModel()->updateLastSeen((int) $user['id']);
        $this->redirect(app_url('views/home.php'));
    }

    private function emailDomainAcceptsMail(string $email): bool {
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
        if ($domain === '') {
            return false;
        }

        if (function_exists('idn_to_ascii')) {
            $asciiDomain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($asciiDomain !== false) {
                $domain = $asciiDomain;
            }
        }

        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
    }

    private function sendSignupEmails(array $user): void {
        if (empty($user['email']) || empty($user['username'])) {
            return;
        }

        $username = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
        $fullName = htmlspecialchars($user['full_name'] ?: $user['username'], ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');
        $adminUsersUrl = app_url('admin/users.php');

        $userBody = "
            <div style='font-family:Arial,sans-serif;line-height:1.5;color:#222'>
                <h2 style='margin:0 0 12px'>Registration successful</h2>
                <p>Hi {$fullName},</p>
                <p>Your Instagram Clone account has been created successfully.</p>
                <p style='margin:6px 0'><strong>Username:</strong> {$username}</p>
                <p style='margin:6px 0'><strong>Email:</strong> {$email}</p>
            </div>
        ";

        $adminBody = "
            <div style='font-family:Arial,sans-serif;line-height:1.5;color:#222'>
                <h2 style='margin:0 0 12px'>New user signup</h2>
                <p>A new user registered on Instagram Clone.</p>
                <p style='margin:6px 0'><strong>Name:</strong> {$fullName}</p>
                <p style='margin:6px 0'><strong>Username:</strong> {$username}</p>
                <p style='margin:6px 0'><strong>Email:</strong> {$email}</p>
                <p><a href='{$adminUsersUrl}'>Open admin users</a></p>
            </div>
        ";

        try {
            sendMail($user['email'], $user['full_name'] ?: $user['username'], 'Registration successful', $userBody);
        } catch (Throwable $e) {
            error_log('User signup mail error: ' . $e->getMessage());
        }

        try {
            sendMail(ADMIN_EMAIL, ADMIN_NAME, 'New User Signup: ' . $user['username'], $adminBody, $user['email'], $user['full_name'] ?: $user['username']);
        } catch (Throwable $e) {
            error_log('Admin signup mail error: ' . $e->getMessage());
        }
    }

    public function loginPage(): void {
        $this->startSession();
        if (isset($_SESSION['user_id'])) {
            $this->redirect(app_url('views/home.php'));
        }
        $this->render('sign_in', ['captcha' => $this->prepareCaptcha('signin')]);
    }

    public function login(): void {
        $this->startSession();
        require_csrf();

        $identifier = $this->rawPost('username');
        $password   = $this->rawPost('password');
        $rateKey = app_rate_limit_key('user_login', $identifier);

        if (app_rate_limited($rateKey, 7, 900)) {
            $this->render('sign_in', [
                'error' => 'Too many login attempts. Please try again after 15 minutes.',
                'captcha' => $this->prepareCaptcha('signin'),
            ]);
            return;
        }

        if (!$this->verifyCaptcha('signin', $_POST['captcha_answer'] ?? '')) {
            $this->render('sign_in', [
                'error' => 'Captcha answer is incorrect.',
                'captcha' => $this->prepareCaptcha('signin'),
            ]);
            return;
        }

        $user = $this->userModel()->getUserByUsername($identifier);
        if (empty($user)) {
            $user = $this->userModel()->getUserByEmail($identifier);
        }

        if (!empty($user) && !empty($user['is_banned'])) {
            $this->render('sign_in', ['error' => 'Your account has been banned by admin.', 'captcha' => $this->prepareCaptcha('signin')]);
            return;
        }

        if (!empty($user) && password_verify($password, $user['password'])) {
            app_rate_limit_clear($rateKey);
            session_regenerate_id(true);
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['username']    = $user['username'];
            $_SESSION['profile_pic'] = $user['profile_pic'] ?? '';
            app_set_auth_cookie((int) $user['id'], $user['password']);
            $this->userModel()->updateLastSeen((int) $user['id']);
            $this->redirect(app_url('views/home.php'));
        } else {
            app_rate_limit_hit($rateKey);
            $this->render('sign_in', ['error' => 'Invalid username or password.', 'captcha' => $this->prepareCaptcha('signin')]);
        }
    }
    public function logout(): void {
        $this->startSession();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId > 0) {
            $this->userModel()->clearLastSeen($userId);
        }

        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['profile_pic'], $_SESSION['captcha']);
        app_clear_auth_cookie();
        session_regenerate_id(true);

        $this->redirect(app_url('views/sign_in.php'));
    }
    public function forgotPage(): void {
        $this->render('forget');
    }

    public function forgotSend(): void {
        require_csrf();
        $this->render('forget', ['success' => 'If the account exists, a password reset link will be sent.']);
    }
}
?>
