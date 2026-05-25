<?php

require_once __DIR__ . '/_page_helpers.php';
app_start_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('views/sign_in.php'));
    exit;
}

require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

$userColumns = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
$profileColumnSql = [
    'phone' => "ALTER TABLE users ADD phone VARCHAR(30) DEFAULT NULL",
    'gender' => "ALTER TABLE users ADD gender VARCHAR(30) DEFAULT NULL",
    'show_account_suggestions' => "ALTER TABLE users ADD show_account_suggestions TINYINT(1) DEFAULT 1",
];
foreach ($profileColumnSql as $column => $sql) {
    if (!in_array($column, $userColumns, true)) {
        $db->exec($sql);
    }
}

$pageTitle  = 'Edit Profile • Instagram';
$activePage = 'profile';

$current_user_id = $_SESSION['user_id'];

$stmt = $db->prepare("
    SELECT *
    FROM users
    WHERE id = ?
");

$stmt->execute([$current_user_id]);

$user = $stmt->fetch();

if (!$user) {
    die("User not found");
}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $username  = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $bio       = trim($_POST['bio']);
    $website   = trim($_POST['website']);
    $phone     = trim($_POST['phone'] ?? '');
    $gender    = trim($_POST['gender'] ?? '');
    $showSuggestions = isset($_POST['show_account_suggestions']) ? 1 : 0;

    $profile_pic = $user['profile_pic'];

    if (empty($username) || empty($email)) {

        $error = "Username and Email are required.";

    } else {
        $checkStmt = $db->prepare("
            SELECT id
            FROM users
            WHERE username = ?
            AND id != ?
        ");

        $checkStmt->execute([
            $username,
            $current_user_id
        ]);

        if ($checkStmt->rowCount() > 0) {

            $error = "Username already exists.";

        } else {
            if (!empty($_FILES['profile_pic']['name'])) {

                $uploadDir = __DIR__ . "/../uploads/profile/";

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['profile_pic']['name']));

                $targetPath = $uploadDir . $fileName;

                $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                $mime = '';
                if (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo) {
                        $mime = (string) finfo_file($finfo, $_FILES['profile_pic']['tmp_name']);
                        finfo_close($finfo);
                    }
                }

                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

                if (in_array($fileType, $allowed, true) && in_array($mime, $allowedMimes, true)) {

                    if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetPath)) {

                        $profile_pic = 'uploads/profile/' . $fileName;
                    }
                } else {
                    $error = 'Profile image type is not allowed.';
                }
            }

            if ($error !== '') {
                $stmt->execute([$current_user_id]);
                $user = $stmt->fetch();
            } else {
            $updateStmt = $db->prepare("
                UPDATE users
                SET
                    username = ?,
                    full_name = ?,
                    email = ?,
                    bio = ?,
                    website = ?,
                    phone = ?,
                    gender = ?,
                    show_account_suggestions = ?,
                    profile_pic = ?
                WHERE id = ?
            ");

            $updateStmt->execute([
                $username,
                $full_name,
                $email,
                $bio,
                $website,
                $phone,
                $gender,
                $showSuggestions,
                $profile_pic,
                $current_user_id
            ]);


            $_SESSION['username']    = $username;
            $_SESSION['full_name']   = $full_name;
            $_SESSION['profile_pic'] = $profile_pic;


            $success = "Profile updated successfully.";

            $stmt->execute([$current_user_id]);

            $user = $stmt->fetch();
            }
        }
    }
}

include __DIR__ . '/../components/head.php';
?>

<style>
.edit_wrapper{
    max-width:935px;
    margin:auto;
}

.edit_card{
    background:#fff;
    border:1px solid #dbdbdb;
    border-radius:4px;
    overflow:hidden;
}

.profile_preview{
    background:#fafafa;
    border-bottom:1px solid #efefef;
}

.profile_img{
    width:90px;
    height:90px;
    border-radius:50%;
    object-fit:cover;
    border:3px solid #fff;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.form-control,
.form-control:focus{
    box-shadow:none;
}

.save_btn{
    background:#0095f6;
    border:none;
    color:#fff;
    padding:12px;
    border-radius:10px;
    font-weight:600;
}

.save_btn:hover{
    background:#1877f2;
}

.upload_btn{
    cursor:pointer;
    color:#0095f6;
    font-weight:600;
    font-size:14px;
}

textarea{
    resize:none;
}

.edit_form_row{
    display:grid;
    grid-template-columns:160px minmax(0, 1fr);
    gap:28px;
    align-items:start;
    margin-bottom:18px;
}

.edit_form_row label{
    padding-top:7px;
    text-align:right;
    font-weight:600;
}

.edit_help{
    color:#737373;
    font-size:12px;
    line-height:1.35;
    margin-top:6px;
}

.bio_counter{
    color:#737373;
    font-size:12px;
    text-align:right;
    margin-top:4px;
}

.suggestions_box{
    border:1px solid #dbdbdb;
    border-radius:8px;
    padding:12px 14px;
}

@media(max-width:768px){

    .edit_card{
        border-radius:0;
        border-left:none;
        border-right:none;
    }

    .edit_form_row{
        grid-template-columns:1fr;
        gap:6px;
    }

    .edit_form_row label{
        text-align:left;
    }
}
</style>


<div class="post_page d-flex">

    <?php include __DIR__ . '/../components/navbar.php'; ?>


    <main class="flex-grow-1 py-4 px-3">

        <div class="edit_wrapper">


            <h3 class="fw-bold mb-4">
                Edit Profile
            </h3>


            <div class="edit_card">



                <div class="profile_preview p-4 d-flex align-items-center gap-4">

                    <img src="<?= htmlspecialchars(profile_avatar($user['profile_pic'], $user['username'])) ?>"
                         class="profile_img"
                         id="previewImage"
                         alt="profile">


                    <div>

                        <h5 class="mb-1">

                            <?= htmlspecialchars($user['username']) ?>

                        </h5>

                        <div class="text-muted small mb-2">

                            <?= htmlspecialchars($user['full_name']) ?>

                        </div>

                        <label class="upload_btn">

                            Change profile photo

                            <input type="file"
                                   name="profile_pic"
                                   form="editForm"
                                   hidden
                                   accept="image/*"
                                   onchange="previewProfile(event)">

                        </label>

                    </div>

                </div>

                <form method="POST"
                      enctype="multipart/form-data"
                      id="editForm"
                      class="p-4">
                    <?= csrf_field() ?>


                    <?php if(!empty($success)): ?>

                    <div class="alert alert-success">

                        <?= $success ?>

                    </div>

                    <?php endif; ?>


                    <?php if(!empty($error)): ?>

                    <div class="alert alert-danger">

                        <?= $error ?>

                    </div>

                    <?php endif; ?>


                    <div class="edit_form_row">
                        <label for="fullNameInput">Name</label>
                        <div>
                            <input type="text"
                                   id="fullNameInput"
                                   name="full_name"
                                   class="form-control"
                                   value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
                            <div class="edit_help">Help people discover your account by using the name you're known by.</div>
                        </div>
                    </div>

                    <div class="edit_form_row">
                        <label for="usernameInput">Username</label>
                        <div>
                            <input type="text"
                                   id="usernameInput"
                                   name="username"
                                   class="form-control"
                                   value="<?= htmlspecialchars($user['username'] ?? '') ?>"
                                   required>
                        </div>
                    </div>

                    <div class="edit_form_row">
                        <label for="websiteInput">Website</label>
                        <div>
                            <input type="url"
                                   id="websiteInput"
                                   name="website"
                                   class="form-control"
                                   value="<?= htmlspecialchars($user['website'] ?? '') ?>"
                                   placeholder="Website">
                        </div>
                    </div>

                    <div class="edit_form_row">
                        <label for="bioInput">Bio</label>
                        <div>
                            <textarea name="bio"
                                      id="bioInput"
                                      rows="4"
                                      maxlength="150"
                                      class="form-control"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                            <div class="bio_counter"><span id="bioCount">0</span> / 150</div>
                        </div>
                    </div>

                    <div class="edit_form_row">
                        <label for="emailInput">Email</label>
                        <div>
                            <input type="email"
                                   id="emailInput"
                                   name="email"
                                   class="form-control"
                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                   required>
                        </div>
                    </div>

                    <div class="edit_form_row">
                        <label for="phoneInput">Phone number</label>
                        <div>
                            <input type="tel"
                                   id="phoneInput"
                                   name="phone"
                                   class="form-control"
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                   placeholder="Phone number">
                        </div>
                    </div>

                    <div class="edit_form_row">
                        <label for="genderInput">Gender</label>
                        <div>
                            <?php $gender = $user['gender'] ?? ''; ?>
                            <select id="genderInput" name="gender" class="form-control">
                                <option value="" <?= $gender === '' ? 'selected' : '' ?>>Prefer not to say</option>
                                <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
                                <option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Custom" <?= $gender === 'Custom' ? 'selected' : '' ?>>Custom</option>
                            </select>
                        </div>
                    </div>

                    <div class="edit_form_row">
                        <label>Profile display</label>
                        <div class="suggestions_box">
                            <label class="d-flex align-items-start gap-2 mb-0">
                                <input type="checkbox"
                                       name="show_account_suggestions"
                                       value="1"
                                       class="mt-1"
                                       <?= !isset($user['show_account_suggestions']) || (int) $user['show_account_suggestions'] === 1 ? 'checked' : '' ?>>
                                <span>
                                    <strong>Show account suggestions on profiles</strong>
                                    <span class="d-block edit_help mt-1">Let people see similar account suggestions on your profile.</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="edit_form_row">
                        <label></label>
                        <div>
                            <button type="submit" class="save_btn px-4">
                                Submit
                            </button>
                        </div>
                    </div>

                </form>

            </div>

        </div>

    </main>

</div>


<script>
function previewProfile(event){

    const image = document.getElementById('previewImage');

    image.src = URL.createObjectURL(event.target.files[0]);
}

const bioInput = document.getElementById('bioInput');
const bioCount = document.getElementById('bioCount');
function updateBioCount() {
    bioCount.textContent = bioInput.value.length;
}
bioInput.addEventListener('input', updateBioCount);
updateBioCount();
</script>


<?php include __DIR__ . '/../components/footer.php'; ?>
