<?php
require_once __DIR__ . '/../core/Database.php';

class PlaylistModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function addPlaylist($data) {
        try {
            if (empty($data['name']) || empty($data['user_id'])) {
                throw new Exception("Thiếu thông tin bắt buộc");
            }

            $sql = "INSERT INTO playlists (name, user_id, description) 
                    VALUES (:name, :user_id, :description)";
            
            $stmt = $this->db->prepare($sql);
            
            $params = [
                ':name' => $data['name'],
                ':user_id' => $data['user_id'],
                ':description' => $data['description'] ?? ''
            ];

            if (!$stmt->execute($params)) {
                throw new Exception("Lỗi khi thêm playlist");
            }

            return true;
        } catch (Exception $e) {
            error_log("Error in addPlaylist: " . $e->getMessage());
            throw $e;
        }
    }

    public function getAllPlaylists() {
        try {
            $sql = "SELECT p.*, u.username as creator 
                    FROM playlists p 
                    LEFT JOIN users u ON p.user_id = u.id 
                    ORDER BY p.created_at DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAllPlaylists: " . $e->getMessage());
            return [];
        }
    }

    public function getPlaylistById($id) {
        try {
            $sql = "SELECT p.*, u.username as creator 
                    FROM playlists p 
                    LEFT JOIN users u ON p.user_id = u.id 
                    WHERE p.id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getPlaylistById: " . $e->getMessage());
            return false;
        }
    }

    public function updatePlaylist($data) {
        try {
            if (empty($data['id']) || empty($data['name'])) {
                throw new Exception("Thiếu thông tin bắt buộc");
            }

            $sql = "UPDATE playlists 
                    SET name = :name, description = :description 
                    WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':id' => $data['id'],
                ':name' => $data['name'],
                ':description' => $data['description'] ?? ''
            ]);

        } catch (Exception $e) {
            error_log("Error in updatePlaylist: " . $e->getMessage());
            return false;
        }
    }

    public function deletePlaylist($id) {
        try {
            // Xóa các liên kết với bài hát trước
            $sql = "DELETE FROM playlist_songs WHERE playlist_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);

            // Sau đó xóa playlist
            $sql = "DELETE FROM playlists WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Error in deletePlaylist: " . $e->getMessage());
            return false;
        }
    }
} 