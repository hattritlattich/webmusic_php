<?php

require_once __DIR__ . '/../models/UserModel.php'; // Chỉnh sửa đường dẫn đúng

class UserController {

    private $userModel;
    
    public function __construct() {
        $this->userModel = new UserModel();
    }

    // Hàm hiển thị trang chỉnh sửa hồ sơ
    public function editProfile() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ?page=login');
            exit;
        }

        // Lấy thông tin người dùng từ session
        $user = [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'avatar' => $_SESSION['user_avatar'] ?? '/uploads/avatars/default.png',
            'birthdate' => $_SESSION['user_birthdate'] ?? '',
            'country' => $_SESSION['user_country'] ?? ''
        ];

        $error = '';
        $success = '';

        // Xử lý nếu người dùng gửi form
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Upload avatar
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/avatars/';
                $fileExtension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $fileName = uniqid() . '.' . $fileExtension;
                $uploadFile = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadFile)) {
                    if ($this->userModel->updateAvatar($user['id'], '/' . $uploadFile)) {
                        $_SESSION['user_avatar'] = '/' . $uploadFile;
                        $user['avatar'] = '/' . $uploadFile;
                        $success = 'Cập nhật ảnh đại diện thành công';
                    } else {
                        $error = 'Có lỗi khi cập nhật ảnh đại diện';
                    }
                } else {
                    $error = 'Không thể lưu ảnh lên server';
                }
            }

            // Cập nhật thông tin người dùng
            $name = $_POST['name'] ?? '';
            $birthdate = $_POST['birthdate'] ?? '';
            $country = $_POST['country'] ?? '';

            // Kiểm tra dữ liệu đầu vào
            if (!empty($name) && !empty($birthdate) && !empty($country)) {
                // Đảm bảo định dạng ngày sinh
                $birthdate = DateTime::createFromFormat('Y-m-d', $birthdate)->format('Y-m-d');
                
                $updateData = [
                    'name' => $name,
                    'birthdate' => $birthdate,
                    'country' => $country
                ];

                if ($this->userModel->updateProfile($user['id'], $updateData)) {
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_birthdate'] = $birthdate;
                    $_SESSION['user_country'] = $country;

                    $user['name'] = $name;
                    $user['birthdate'] = $birthdate;
                    $user['country'] = $country;
                    $success = 'Cập nhật thông tin thành công';
                } else {
                    $error = 'Có lỗi xảy ra khi cập nhật thông tin';
                }
            } else {
                $error = 'Tất cả các trường thông tin đều bắt buộc';
            }
        }

        // Gửi dữ liệu đến view
        require_once __DIR__ . '/../views/user/editProfile.php';
    }
}
