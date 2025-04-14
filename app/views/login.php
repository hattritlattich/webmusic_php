<?php
// Xóa 2 dòng này vì session đã được khởi tạo trong Router
// session_start();
// session_regenerate_id(true);

// Xử lý form đăng nhập
$error = '';
$success = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
unset($_SESSION['success_message']); // Xóa message sau khi hiển thị

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $userModel = new UserModel();
        $user = $userModel->getUserByEmail($email);

        if ($user && password_verify($password, $user['password'])) {
            // Đăng nhập thành công
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_avatar'] = $user['avatar'];
            $_SESSION['user_role'] = $user['role'];
            
            // Chuyển hướng về trang người dùng muốn truy cập trước đó
            $redirect = $_SESSION['redirect_after_login'] ?? 'home';
            unset($_SESSION['redirect_after_login']);
            header('Location: ?page=' . $redirect);
            exit;
        } else {
            $error = 'Email hoặc mật khẩu không chính xác';
        }
    } else {
        $error = 'Vui lòng điền đầy đủ thông tin';
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Spotify Clone</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-black text-white">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-[450px]">
            <div class="bg-[#121212] p-8 rounded-lg">
                <div class="text-center mb-8">
                    <h1 class="text-4xl font-bold mb-2">Đăng nhập</h1>
                    <p class="text-gray-400">Tiếp tục với Spotify</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="text-red-400 text-sm mb-4 text-center">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-medium mb-2">
                            Email hoặc tên người dùng
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               required
                               class="w-full p-3 rounded bg-[#242424] border border-gray-700 focus:border-white focus:outline-none"
                               placeholder="Email hoặc tên người dùng">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium mb-2">
                            Mật khẩu
                        </label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required
                               class="w-full p-3 rounded bg-[#242424] border border-gray-700 focus:border-white focus:outline-none"
                               placeholder="Mật khẩu">
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" 
                               id="remember" 
                               name="remember" 
                               class="w-4 h-4 rounded border-gray-700 bg-[#242424] text-[#1DB954] focus:ring-[#1DB954]">
                        <label for="remember" class="ml-2 text-sm">
                            Ghi nhớ tôi
                        </label>
                    </div>

                    <button type="submit" 
                            class="w-full bg-[#1DB954] text-black font-bold py-3 px-4 rounded-full hover:bg-[#1ed760] transition-colors">
                        ĐĂNG NHẬP
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <a href="#" class="text-sm text-gray-400 hover:underline">
                        Quên mật khẩu của bạn?
                    </a>
                </div>

                <div class="mt-8 pt-8 border-t border-gray-800 text-center">
                    <p class="text-gray-400 mb-6">Bạn chưa có tài khoản?</p>
                    <a href="?page=register" 
                       class="block w-full border border-gray-400 text-white font-bold py-3 px-4 rounded-full hover:border-white hover:bg-white/10 transition-colors">
                        ĐĂNG KÝ SPOTIFY
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
