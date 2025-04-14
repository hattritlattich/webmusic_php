<?php
// Xóa phần session start nếu có
// session_start();
// session_regenerate_id(true);

// Xử lý form đăng ký
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_once __DIR__ . '/../models/UserModel.php';
        $userModel = new UserModel();

        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? ''
        ];

        // Validation
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            throw new Exception('Vui lòng điền đầy đủ thông tin');
        }
        
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email không hợp lệ');
        }
        
        if (strlen($data['password']) < 6) {
            throw new Exception('Mật khẩu phải có ít nhất 6 ký tự');
        }

        if ($userModel->register($data)) {
            $success = 'Đăng ký thành công! Bạn sẽ được chuyển đến trang đăng nhập sau 3 giây...';
            header("refresh:3;url=?page=login");
            // Không exit ngay để hiển thị thông báo thành công
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký - Spotify Clone</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-black">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-[450px]">
            <div class="bg-[#121212] p-8 rounded-lg">
                <div class="text-center mb-8">
                    <h1 class="text-4xl font-bold text-white mb-2">Đăng ký tài khoản</h1>
                    <p class="text-gray-400">Nghe nhạc miễn phí trên Spotify</p>
                </div>

                <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 text-center">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="text-red-400 text-sm mb-4 text-center">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="?page=register" class="space-y-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-300 mb-2">
                            Tên hiển thị
                        </label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                               class="w-full p-3 rounded bg-[#242424] text-white border border-gray-700 focus:border-white focus:outline-none"
                               required>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-300 mb-2">
                            Email
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               class="w-full p-3 rounded bg-[#242424] text-white border border-gray-700 focus:border-white focus:outline-none"
                               required>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-300 mb-2">
                            Mật khẩu
                        </label>
                        <input type="password" 
                               id="password" 
                               name="password"
                               class="w-full p-3 rounded bg-[#242424] text-white border border-gray-700 focus:border-white focus:outline-none"
                               minlength="6"
                               required>
                        <p class="text-gray-400 text-sm mt-1">Mật khẩu phải có ít nhất 6 ký tự</p>
                    </div>

                    <button type="submit" 
                            class="w-full bg-[#1DB954] text-black font-bold py-3 px-4 rounded-full hover:bg-[#1ed760] transition-colors">
                        Đăng ký
                    </button>

                    <div class="text-center mt-6">
                        <p class="text-gray-400">
                            Đã có tài khoản? 
                            <a href="?page=login" class="text-white hover:underline">Đăng nhập</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
