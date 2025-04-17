<?php
session_start();
require_once __DIR__ . '/../models/PlaylistModel.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// Kiểm tra dữ liệu và action
if (!isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Thiếu hành động']);
    exit;
}

$playlistModel = new PlaylistModel();

switch ($data['action']) {
    case 'create':
        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId) {
            echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng nhập']);
            exit;
        }

        try {
            $playlistData = [
                'name' => $data['name'] ?? '',
                'description' => $data['description'] ?? '',
                'user_id' => $userId
            ];

            $playlistModel->addPlaylist($playlistData);

            // Lấy lại playlist vừa thêm để lấy ID
            $userPlaylists = $playlistModel->getUserPlaylists($userId);
            $lastPlaylist = $userPlaylists[0] ?? null;

            echo json_encode([
                'success' => true,
                'message' => 'Tạo playlist thành công',
                'id' => $lastPlaylist['id'] ?? null
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Lỗi khi tạo playlist: ' . $e->getMessage()
            ]);
        }

        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
        break;
}
