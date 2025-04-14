<?php
class Middleware {
    public static function handleRequest() {
        $page = $_GET['page'] ?? 'home';
        
        // Các trang không cần đăng nhập
        $publicPages = ['home', 'login', 'register'];
        
        // Các trang chỉ dành cho admin
        $adminPages = ['admin', 'admin/songs', 'admin/users'];
        
        if (!in_array($page, $publicPages)) {
            Auth::requireLogin();
            
            if (in_array($page, $adminPages)) {
                Auth::requireAdmin();
            }
        }
    }
} 