<?php
class AuthController {
    private $userModel;

    public function __construct() {
        $this->userModel = new UserModel();
    }

    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $user = $this->userModel->login(
                    trim($_POST['email']),
                    $_POST['password']
                );

                // Lưu thông tin user vào session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['avatar'] = $user['avatar'];

                header('Location: ?page=home');
                exit;

            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        require 'app/views/login.php';
    }

    public function register() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $data = [
                    'name' => trim($_POST['name']),
                    'email' => trim($_POST['email']),
                    'password' => $_POST['password']
                ];

                if ($this->userModel->register($data)) {
                    $_SESSION['success_message'] = 'Đăng ký thành công! Vui lòng đăng nhập.';
                    header('Location: ?page=login');
                    exit;
                }

            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        require 'app/views/register.php';
    }

    public function logout() {
        Auth::logout();
    }
}
