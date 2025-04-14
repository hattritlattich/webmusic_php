<?php
$title = 'Chỉnh sửa hồ sơ - Spotify Clone';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: ?page=login');
    exit;
}

// Lấy thông tin người dùng từ session
$user = [
    'id' => $_SESSION['user_id'],
    'name' => $_SESSION['user_name'],
    'email' => $_SESSION['user_email'],
    'avatar' => $_SESSION['user_avatar'] ?? '/uploads/avatars/default.png'
];

$error = '';
$success = '';
$userModel = new UserModel();

// Lấy thông tin chi tiết user từ database
$userDetails = $userModel->getUserById($user['id']);
$user['birthdate'] = $userDetails['birthdate'] ?? '';
$user['country'] = $userDetails['country'] ?? 'Việt Nam';

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Xử lý upload avatar
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/avatars/';
        $fileExtension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '.' . $fileExtension;
        $uploadFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadFile)) {
            if ($userModel->updateAvatar($user['id'], '/' . $uploadFile)) {
                $_SESSION['user_avatar'] = '/' . $uploadFile;
                $success = 'Cập nhật ảnh đại diện thành công';
            } else {
                $error = 'Có lỗi khi cập nhật ảnh đại diện';
            }
        } else {
            $error = 'Không thể lưu ảnh lên server';
        }
    }

    // Xử lý cập nhật thông tin
    $name = $_POST['name'] ?? '';
    $birthdate = $_POST['birthdate'] ?? '';
    $country = $_POST['country'] ?? '';

    if (!empty($name)) {
        $updateData = [
            'name' => $name,
            'birthdate' => $birthdate,
            'country' => $country
        ];

        if ($userModel->updateProfile($user['id'], $updateData)) {
            // Cập nhật session
            $_SESSION['user_name'] = $name;
            $success = 'Cập nhật thông tin thành công';

            // Cập nhật lại thông tin user
            $user['name'] = $name;
            $user['birthdate'] = $birthdate;
            $user['country'] = $country;
        } else {
            $error = 'Có lỗi xảy ra khi cập nhật thông tin';
        }
    }

    // Redirect lại trang profile sau khi lưu thay đổi
    header('Location: ?page=profile');
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-black text-white">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-black h-screen fixed left-0 top-0 p-6">
            <div class="flex flex-col h-full">
                <!-- Logo -->
                <div class="mb-8">
                    <i class="fab fa-spotify text-white text-3xl"></i>
                </div>

                <!-- Navigation -->
                <nav class="flex-1">
                    <ul class="space-y-4">
                        <li>
                            <a href="?page=home" class="flex items-center text-gray-400 hover:text-white">
                                <i class="fas fa-home mr-4"></i>
                                <span>Trang chủ</span>
                            </a>
                        </li>
                        <li>
                            <a href="?page=search" class="flex items-center text-gray-400 hover:text-white">
                                <i class="fas fa-search mr-4"></i>
                                <span>Tìm kiếm</span>
                            </a>
                        </li>
                        <li>
                            <a href="?page=library" class="flex items-center text-gray-400 hover:text-white">
                                <i class="fas fa-book mr-4"></i>
                                <span>Thư viện</span>
                            </a>
                        </li>
                    </ul>
                </nav>

                <!-- User Menu -->
                <div class="mt-auto">
                    <a href="?page=profile" class="flex items-center text-white">
                        <img src="<?= htmlspecialchars($user['avatar']) ?>" 
                             alt="Profile" 
                             class="w-8 h-8 rounded-full mr-3">
                        <span><?= htmlspecialchars($user['name']) ?></span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 ml-64 p-8">
            <div class="max-w-2xl mx-auto">
                <div class="flex items-center justify-between mb-8">
                    <h1 class="text-3xl font-bold">Chỉnh sửa hồ sơ</h1>
                    <a href="?page=profile" 
                       class="px-4 py-2 bg-[#282828] text-white rounded-full hover:bg-[#333] transition">
                        Quay lại
                    </a>
                </div>

                <?php if ($error): ?>
                    <div class="text-red-400 text-sm mb-4 text-center">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="text-green-400 text-sm mb-4 text-center">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <!-- Avatar Section -->
                    <div class="flex items-center space-x-6">
                        <div class="relative">
                            <img src="<?= htmlspecialchars($user['avatar']) ?>" 
                                 alt="Avatar" 
                                 id="avatar-preview"
                                 class="w-24 h-24 rounded-full object-cover">
                            <label for="avatar-upload" 
                                   class="absolute bottom-0 right-0 bg-[#1DB954] text-black p-2 rounded-full cursor-pointer hover:bg-[#1ed760]">
                                <i class="fas fa-camera"></i>
                            </label>
                            <input type="file" 
                                   id="avatar-upload" 
                                   name="avatar" 
                                   accept="image/*"
                                   class="hidden"
                                   onchange="previewImage(this)">
                        </div>
                        <div>
                            <h3 class="font-medium">Ảnh hồ sơ</h3>
                            <p class="text-sm text-gray-400">Chọn ảnh để thay đổi ảnh hồ sơ</p>
                        </div>
                    </div>

                    <!-- Profile Info -->
                    <div class="space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium mb-2">Tên hiển thị</label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   value="<?= htmlspecialchars($user['name']) ?>"
                                   class="w-full p-3 rounded bg-[#242424] border border-gray-700 focus:border-white focus:outline-none"
                                   required>
                        </div>

                        <div>
                            <label for="birthdate" class="block text-sm font-medium mb-2">Ngày sinh</label>
                            <input type="date" 
                                   id="birthdate" 
                                   name="birthdate"
                                   value="<?= htmlspecialchars($user['birthdate']) ?>"
                                   class="w-full p-3 rounded bg-[#242424] border border-gray-700 focus:border-white focus:outline-none">
                        </div>

                        <div>
                            <label for="country" class="block text-sm font-medium mb-2">Quốc gia</label>
                            <input type="text" 
                                   id="country" 
                                   name="country"
                                   value="<?= htmlspecialchars($user['country']) ?>"
                                   class="w-full p-3 rounded bg-[#242424] border border-gray-700 focus:border-white focus:outline-none">
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" 
                                class="px-6 py-2 bg-[#1DB954] text-black font-bold rounded-full hover:bg-[#1ed760] transition">
                            Lưu thay đổi
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Player -->
        <div class="fixed bottom-0 left-0 right-0 bg-[#181818] border-t border-[#282828] px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center w-1/4">
                    <div class="text-sm text-gray-400">Chưa phát bài hát nào</div>
                </div>
                <div class="flex items-center justify-center w-1/2">
                    <div class="flex items-center space-x-4">
                        <button class="text-gray-400 hover:text-white">
                            <i class="fas fa-play"></i>
                        </button>
                    </div>
                </div>
                <div class="w-1/4"></div>
            </div>
        </div>
    </div>

    <script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('avatar-preview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>
</body>
</html>
