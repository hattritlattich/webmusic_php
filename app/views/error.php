<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lỗi - Spotify Clone</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-black text-white min-h-screen flex items-center justify-center">
    <div class="text-center">
        <h1 class="text-4xl font-bold mb-4">Đã xảy ra lỗi</h1>
        <p class="text-gray-400 mb-6"><?= $error ?? 'Có lỗi xảy ra. Vui lòng thử lại sau.' ?></p>
        <a href="?page=home" class="text-green-500 hover:text-green-400">
            Quay về trang chủ
        </a>
    </div>
</body>
</html> 