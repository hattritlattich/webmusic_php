<?php
require_once __DIR__ . '/../core/Database.php';

class PlaylistModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // Kiểm tra nếu playlist với tên đã tồn tại
    public function isPlaylistNameExist($userId, $playlistName) {
        try {
            $sql = "SELECT COUNT(*) FROM playlists WHERE user_id = :user_id AND name = :name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':name' => $playlistName
            ]);
            $count = $stmt->fetchColumn();
            return $count > 0;
        } catch (PDOException $e) {
            error_log("Error in isPlaylistNameExist: " . $e->getMessage());
            return false;
        }
    }

    // Thêm playlist mới
    public function addPlaylist($data) {
        try {
            if (empty($data['name']) || empty($data['user_id'])) {
                throw new Exception("Thiếu thông tin bắt buộc");
            }

            // Kiểm tra tên playlist đã tồn tại
            if ($this->isPlaylistNameExist($data['user_id'], $data['name'])) {
                throw new Exception("Tên playlist đã tồn tại, vui lòng chọn tên khác.");
            }

            $sql = "INSERT INTO playlists (name, user_id, description) VALUES (:name, :user_id, :description)";
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

    // Lấy tất cả playlist
    public function getAllPlaylists() {
        try {
            $sql = "SELECT p.*, u.username as creator FROM playlists p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAllPlaylists: " . $e->getMessage());
            return [];
        }
    }

    // Lấy playlist theo ID
    public function getPlaylistById($id) {
        try {
            $sql = "SELECT p.*, u.username as creator FROM playlists p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getPlaylistById: " . $e->getMessage());
            return false;
        }
    }

    // Cập nhật playlist
    public function updatePlaylist($data) {
        try {
            if (empty($data['id']) || empty($data['name'])) {
                throw new Exception("Thiếu thông tin bắt buộc");
            }

            $sql = "UPDATE playlists SET name = :name, description = :description WHERE id = :id";
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

    // Xóa playlist
    public function deletePlaylist($id) {
        try {
            // Xóa các liên kết bài hát trước
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

    // Lấy playlist của người dùng theo userId, kèm số lượng bài hát
public function getUserPlaylists($userId) {
    try {
        $sql = "
            SELECT 
                p.id,
                p.name,
                p.description,
                p.created_at,
                u.username AS creator,
                COUNT(ps.song_id) AS song_count
            FROM playlists p
            LEFT JOIN users u 
                ON p.user_id = u.id
            LEFT JOIN playlist_songs ps 
                ON p.id = ps.playlist_id
            WHERE p.user_id = ?
            GROUP BY 
                p.id, p.name, p.description, p.created_at, u.username
            ORDER BY p.created_at DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getUserPlaylists: " . $e->getMessage());
        return [];
    }
}
public function getSongsByPlaylistId($playlistId) {
    try {
        $sql = "SELECT s.*, ps.added_at 
                FROM songs s
                JOIN playlist_songs ps ON s.id = ps.song_id
                WHERE ps.playlist_id = ?
                ORDER BY ps.added_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$playlistId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getSongsByPlaylistId: " . $e->getMessage());
        return [];
    }
}


    // Thêm bài hát vào playlist
    public function addSongToPlaylist($playlistId, $songId) {
        try {
            if (empty($playlistId) || empty($songId)) {
                return ['success' => false, 'message' => 'Thiếu thông tin playlist hoặc bài hát'];
            }
    
            // Kiểm tra xem bài hát đã có trong playlist chưa
            $check = $this->db->prepare("SELECT COUNT(*) FROM playlist_songs WHERE playlist_id = ? AND song_id = ?");
            $check->execute([$playlistId, $songId]);
            if ($check->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'Bài hát đã tồn tại trong playlist'];
            }
    
            // Chưa có thì thêm vào
            $stmt = $this->db->prepare("INSERT INTO playlist_songs (playlist_id, song_id, added_at) VALUES (?, ?, NOW())");
            $stmt->execute([$playlistId, $songId]);
    
            return ['success' => true, 'message' => 'Đã thêm bài hát vào playlist'];
        } catch (PDOException $e) {
            error_log("Lỗi khi thêm bài hát vào playlist: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi database'];
        }
    }
    public function removeSongFromPlaylist($playlistId, $songId) {
        try {
            // Prepare and execute the query to remove the song from the playlist
            $stmt = $this->db->prepare("DELETE FROM playlist_songs WHERE playlist_id = ? AND song_id = ?");
            $stmt->execute([$playlistId, $songId]);
    
            // Check if the song was removed successfully
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Bài hát đã được xóa khỏi playlist'];
            } else {
                return ['success' => false, 'message' => 'Bài hát không tồn tại trong playlist'];
            }
        } catch (PDOException $e) {
            // Log and return error if any
            error_log("Error in removeSongFromPlaylist: " . $e->getMessage());
            return ['success' => false, 'message' => 'Có lỗi xảy ra khi xóa bài hát'];
        }
    }
    
}

?>
