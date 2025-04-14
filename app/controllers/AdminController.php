<?php

class AdminController {
    public function __construct() {
        // Kiểm tra quyền admin cho tất cả các action
        Auth::requireAdmin();
    }

    public function index() {
        require 'app/views/admin.php';
    }

    public function songs() {
        // Xử lý thêm/sửa/xóa bài hát (chỉ admin mới có quyền)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::isAdmin()) {
                http_response_code(403);
                echo json_encode(['error' => 'Không có quyền thực hiện hành động này']);
                exit;
            }

            switch($_GET['action']) {
                case 'add_song':
                    $this->addSong();
                    break;
                case 'edit_song':
                    $this->editSong();
                    break;
                case 'delete_song':
                    $this->deleteSong();
                    break;
            }
        }

        require 'app/views/admin/songs.php';
    }

    private function addSong() {
        // Xử lý upload file và lưu thông tin bài hát
    }

    private function editSong() {
        // Xử lý sửa thông tin bài hát
    }

    private function deleteSong() {
        // Xử lý xóa bài hát
    }

    public function getSong($id) {
        try {
            require_once __DIR__ . '/../models/SongModel.php';
            $songModel = new SongModel();
            
            $song = $songModel->getSongById($id);
            if (!$song) {
                throw new Exception("Không tìm thấy bài hát");
            }

            // Trả về dữ liệu dạng JSON
            header('Content-Type: application/json');
            echo json_encode($song);
            exit;

        } catch (Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }

    public function getUser($id) {
        try {
            require_once __DIR__ . '/../models/UserModel.php';
            $userModel = new UserModel();
            
            $user = $userModel->getUserById($id);
            if (!$user) {
                throw new Exception("Không tìm thấy người dùng");
            }

            header('Content-Type: application/json');
            echo json_encode($user);
            exit;

        } catch (Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
} 