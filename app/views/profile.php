<?php
$title = 'Hồ sơ - Spotify Clone';

if (!isset($_SESSION['user_id'])) {
    header('Location: ?page=login');
    exit;
}

require_once __DIR__ . '/../models/UserModel.php';
$userModel = new UserModel(); // Không cần truyền $db
    // $db là PDO đã kết nối

$userId = $_SESSION['user_id'];
$userDetails = $userModel->getUserById($userId);

// Nếu không tìm thấy, quay về trang login
if (!$userDetails) {
    header('Location: ?page=login');
    exit;
}

// Gán lại session nếu có thay đổi
$_SESSION['user_name'] = $userDetails['name'];
$_SESSION['user_email'] = $userDetails['email'];
$_SESSION['user_avatar'] = $userDetails['avatar'];

$user = [
    'id' => $userId,
    'name' => $userDetails['name'],
    'email' => $userDetails['email'],
    'avatar' => $userDetails['avatar'] ?? '/uploads/artists/placeholder.jpg'
];

// Ví dụ bạn có thể lấy thêm thống kê playlist/followers từ model riêng
$userData = [
    'playlists' => 0,
    'followers' => 0,
    'following' => 0
];
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
        <div class="flex-1 ml-64">
            <!-- Profile Header -->
            <div class="bg-gradient-to-b from-[#535353] to-black p-8">
                <div class="max-w-4xl mx-auto">
                    <div class="flex items-center space-x-6">
                        <div class="relative group">
                            <img src="<?= htmlspecialchars($userDetails['avatar']) ?>" 
                                 alt="Profile" 
                                 class="w-48 h-48 rounded-full object-cover shadow-2xl">
                        </div>
                        
                        <div>
                            <p class="text-sm uppercase mb-2">Hồ sơ</p>
                            <h1 class="text-6xl font-bold mb-6"><?= htmlspecialchars($userDetails['name']) ?></h1>
                            <div class="flex items-center space-x-4 text-sm">
                                <span><?= $userData['playlists'] ?> Playlist</span>
                                <span class="text-gray-400">•</span>
                                <span><?= $userData['followers'] ?> người theo dõi</span>
                                <span class="text-gray-400">•</span>
                                <span>Đang theo dõi <?= $userData['following'] ?> người</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Content -->
            <div class="p-8">
                <div class="max-w-4xl mx-auto">
                    <!-- Action Buttons -->
                    <div class="mb-8">
                        <a href="?page=edit_profile" 
                           class="inline-block px-8 py-3 rounded-full border border-white font-bold hover:bg-white hover:text-black transition-colors">
                            Chỉnh sửa hồ sơ
                        </a>
                    </div>

                    <!-- Profile Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Basic Info -->
                        <div class="bg-[#181818] p-6 rounded-lg">
                            <h2 class="text-xl font-bold mb-4">Thông tin cơ bản</h2>
                            <div class="space-y-4">
                                <div>
                                    <p class="text-sm text-gray-400">Email</p>
                                    <p class="font-medium"><?= htmlspecialchars($userDetails['email']) ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-400">Ngày sinh</p>
                                    <p class="font-medium">
                                        <?= $userDetails['birthdate'] ? date('d/m/Y', strtotime($userDetails['birthdate'])) : 'Chưa cập nhật' ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-400">Quốc gia</p>
                                    <p class="font-medium"><?= htmlspecialchars($userDetails['country']) ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Account Info -->
                        <div class="bg-[#181818] p-6 rounded-lg">
                            <h2 class="text-xl font-bold mb-4">Thông tin tài khoản</h2>
                            <div class="space-y-4">
                                <div>
                                    <p class="text-sm text-gray-400">Ngày tham gia</p>
                                    <p class="font-medium"><?= date('d/m/Y', strtotime($userDetails['created_at'])) ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-400">Playlist công khai</p>
                                    <p class="font-medium"><?= $userData['playlists'] ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-400">Người theo dõi</p>
                                    <p class="font-medium"><?= $userData['followers'] ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
</body>
</html> 