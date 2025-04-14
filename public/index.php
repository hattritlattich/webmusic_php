<?php
// Định nghĩa đường dẫn gốc
define('ROOT_PATH', dirname(__DIR__));

// Bắt đầu session
if (!isset($_SESSION)) {
    session_start();
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load các file cần thiết theo thứ tự
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/app/core/Database.php';
require_once ROOT_PATH . '/app/core/Auth.php';
require_once ROOT_PATH . '/app/core/Router.php';

// Load Models
require_once ROOT_PATH . '/app/models/UserModel.php';

// Khởi tạo router
$router = new Router();

// Lấy trang từ query parameter
$page = $_GET['page'] ?? 'home';

// Xử lý logout
if ($page === 'logout') {
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
    session_destroy();
    header('Location: ?page=home');
    exit();
}

try {
    // Gọi phương thức route() thay vì run()
    $router->route($page);
} catch (Exception $e) {
    // Log lỗi và hiển thị trang lỗi
    error_log($e->getMessage());
    require_once ROOT_PATH . '/app/views/error.php';
}
?>
