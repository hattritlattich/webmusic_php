<?php
class Router {
    private $routes = [];
    private $publicRoutes = ['login', 'register', 'home'];
    private $protectedRoutes = ['profile', 'edit_profile'];
    private $adminRoutes = ['admin', 'admin/songs'];

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->initRoutes();
    }

    private function initRoutes() {
        $this->routes = [
            'login' => 'C:/xampp/htdocs/music_website/app/views/login.php',
            'register' => 'C:/xampp/htdocs/music_website/app/views/register.php',
            'home' => 'C:/xampp/htdocs/music_website/app/views/home.php',
            'profile' => 'C:/xampp/htdocs/music_website/app/views/profile.php',
            'edit_profile' => 'C:/xampp/htdocs/music_website/app/views/edit_profile.php',
            'admin' => 'C:/xampp/htdocs/music_website/app/views/admin.php',
            'admin/songs' => [
                'controller' => 'AdminController',
                'action' => 'songs'
            ],
            'admin/songs/get/(\d+)' => [
                'controller' => 'AdminController',
                'action' => 'getSong',
                'pattern' => '/^admin\/songs\/get\/(\d+)$/'
            ],
            'admin/users/get/(\d+)' => [
                'controller' => 'AdminController',
                'action' => 'getUser',
                'pattern' => '/^admin\/users\/get\/(\d+)$/'
            ]
        ];
    }

    public function route($page = 'home') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Kiểm tra quyền truy cập
        if (in_array($page, $this->protectedRoutes) && !isset($_SESSION['user_id'])) {
            header('Location: /?page=login');
            exit;
        }

        if (in_array($page, $this->adminRoutes) && (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin')) {
            header('Location: /?page=home');
            exit;
        }

        // Xác định file content
        if (isset($this->routes[$page])) {
            $content = $this->routes[$page];
        } else {
            $content = __DIR__ . '/../views/home.php';
        }

        // Load layout với content
        require __DIR__ . '/../views/layouts/main.php';
    }

    private function handleController($controllerName, $actionName, $params = []) {
        require_once "C:/xampp/htdocs/music_website/app/controllers/{$controllerName}.php";
        $controller = new $controllerName();
        call_user_func_array([$controller, $actionName], $params);
    }
} 