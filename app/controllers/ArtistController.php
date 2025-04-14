<?php
require_once __DIR__ . '/../models/ArtistModel.php';

class ArtistController {
    private $artistModel;

    public function __construct() {
        $this->artistModel = new ArtistModel();
    }

    public function getArtist($id) {
        try {
            $artist = $this->artistModel->getArtistById($id);
            if (!$artist) {
                http_response_code(404);
                echo json_encode(['error' => 'Không tìm thấy nghệ sĩ']);
                return;
            }

            header('Content-Type: application/json');
            echo json_encode($artist);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Có lỗi xảy ra khi lấy thông tin nghệ sĩ']);
        }
    }
} 