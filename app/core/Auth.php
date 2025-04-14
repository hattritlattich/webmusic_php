<?php
class Auth {
    const ROLE_GUEST = 'guest';
    const ROLE_USER = 'user';
    const ROLE_ADMIN = 'admin';

    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login($user) {
        if (!is_array($user) || empty($user['id'])) {
            return false;
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'] ?? 'Guest';
        $_SESSION['email'] = $user['email'] ?? '';
        $_SESSION['user_role'] = $user['role'] ?? self::ROLE_USER;
        $_SESSION['avatar'] = $user['avatar'] ?? '/uploads/avatars/default.png';
        return true;
    }

    public static function logout() {
        $_SESSION = array();
        session_destroy();
        header('Location: ?page=login');
        exit;
    }

    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public static function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === self::ROLE_ADMIN;
    }

    public static function user() {
        if (!self::isLoggedIn()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['name'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['user_role'],
            'avatar' => $_SESSION['avatar']
        ];
    }

    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: ?page=login');
            exit;
        }
    }

    public static function requireAdmin() {
        self::requireLogin();
        if (!self::isAdmin()) {
            header('Location: ?page=home');
            exit;
        }
    }
} 