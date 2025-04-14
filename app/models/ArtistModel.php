<?php
require_once __DIR__ . '/../core/Database.php';

class ArtistModel {
    private $db;

    public function __construct() {
        try {
            $this->db = Database::getInstance()->getConnection();
            error_log("ArtistModel: Database connection established");
        } catch (Exception $e) {
            error_log("ArtistModel: Database connection failed - " . $e->getMessage());
            throw $e;
        }
    }

    public function getAllArtists() {
        try {
            $sql = "SELECT a.id, a.name, a.image, 
                    (SELECT COUNT(*) 
                     FROM songs s 
                     WHERE s.artist_id = a.id 
                        OR s.title LIKE CONCAT('%', a.name, '%')
                        OR s.artist LIKE CONCAT('%', a.name, '%')
                    ) as song_count
                    FROM artists a
                    WHERE a.image IS NOT NULL AND a.image != ''
                    ORDER BY a.name ASC";
                    
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as &$artist) {
                if (!empty($artist['image'])) {
                    $artist['image'] = '/uploads/artists/' . basename($artist['image']);
                } else {
                    $artist['image'] = '/uploads/artists/placeholder.jpg';
                }
                
                $artist['song_count'] = (int)($artist['song_count'] ?? 0);
            }
            
            return $results;
        } catch (Exception $e) {
            error_log("Error in getAllArtists: " . $e->getMessage());
            return [];
        }
    }

    public function addArtist($data) {
        try {
            $this->db->beginTransaction();

            // Validate dữ liệu
            if (empty($data['name'])) {
                throw new Exception("Tên nghệ sĩ không được để trống");
            }

            // Kiểm tra nghệ sĩ đã tồn tại - thêm COLLATE để so sánh không phân biệt hoa thường
            $stmt = $this->db->prepare("SELECT id FROM artists WHERE LOWER(name) = LOWER(?) COLLATE utf8mb4_unicode_ci");
            $stmt->execute([$data['name']]);
            if ($stmt->fetch()) {
                throw new Exception("Nghệ sĩ này đã tồn tại trong hệ thống");
            }

            // Insert nghệ sĩ mới
            $sql = "INSERT INTO artists (name, image) VALUES (?, ?)";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                trim($data['name']), // Thêm trim() để xóa khoảng trắng thừa
                $data['image']
            ]);

            if (!$result) {
                throw new Exception("Không thể thêm nghệ sĩ");
            }

            $artistId = $this->db->lastInsertId();
            $this->db->commit();
            return $artistId;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in addArtist: " . $e->getMessage());
            throw $e;
        }
    }

    public function updateArtist($data) {
        try {
            $this->db->beginTransaction();

            if (empty($data['id'])) {
                throw new Exception("ID nghệ sĩ không hợp lệ");
            }

            // Kiểm tra tên mới có bị trùng không (trừ chính nghệ sĩ đó)
            if (!empty($data['name'])) {
                $stmt = $this->db->prepare("SELECT id FROM artists WHERE LOWER(name) = LOWER(?) COLLATE utf8mb4_unicode_ci AND id != ?");
                $stmt->execute([trim($data['name']), $data['id']]);
                if ($stmt->fetch()) {
                    throw new Exception("Tên nghệ sĩ đã tồn tại");
                }
            }

            $sql = "UPDATE artists SET name = :name";
            $params = [
                ':id' => $data['id'],
                ':name' => trim($data['name'])
            ];

            if (!empty($data['image'])) {
                // Xóa ảnh cũ nếu có
                $oldArtist = $this->getArtistById($data['id']);
                if ($oldArtist && !empty($oldArtist['image'])) {
                    $oldImagePath = __DIR__ . '/../../public/' . $oldArtist['image'];
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                $sql .= ", image = :image";
                $params[':image'] = $data['image'];
            }

            $sql .= " WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);

            $this->db->commit();
            return $result;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in updateArtist: " . $e->getMessage());
            throw $e;
        }
    }

    public function deleteArtist($id) {
        try {
            $this->db->beginTransaction();

            // Xóa ảnh cũ nếu có
            $artist = $this->getArtistById($id);
            if ($artist && !empty($artist['image'])) {
                $fullPath = __DIR__ . '/../../public/' . $artist['image'];
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }

            // Cập nhật artist_id thành NULL cho các bài hát của nghệ sĩ này
            $sql = "UPDATE songs SET artist_id = NULL WHERE artist_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);

            // Xóa nghệ sĩ
            $sql = "DELETE FROM artists WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$id]);

            $this->db->commit();
            return $result;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in deleteArtist: " . $e->getMessage());
            throw $e;
        }
    }

    public function getArtistById($id) {
        try {
            $sql = "SELECT * FROM artists WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getArtistById: " . $e->getMessage());
            return false;
        }
    }

    public function getArtistByName($name) {
        $sql = "SELECT * FROM artists WHERE name = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$name]);
        return $stmt->fetch();
    }

    public function getArtistSongs($artistId) {
        try {
            $sql = "SELECT s.*, 
                    CASE WHEN ls.id IS NOT NULL THEN 1 ELSE 0 END as is_liked
                    FROM songs s
                    LEFT JOIN liked_songs ls ON s.id = ls.song_id 
                        AND ls.user_id = :user_id
                    WHERE s.artist_id = :artist_id
                    ORDER BY s.created_at DESC";
                    
            $userId = $_SESSION['user_id'] ?? 0;
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':artist_id', $artistId, PDO::PARAM_INT);
            $stmt->execute();
            
            $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format song data
            foreach ($songs as &$song) {
                $song['image'] = !empty($song['image']) ? '/uploads/songs/' . basename($song['image']) : '/uploads/songs/placeholder.jpg';
                $song['file_path'] = !empty($song['file_path']) ? '/uploads/songs/' . basename($song['file_path']) : '';
                
                // Format duration manually instead of using undefined function
                $duration = intval($song['duration']);
                $minutes = floor($duration / 60);
                $seconds = $duration % 60;
                $song['duration'] = sprintf("%d:%02d", $minutes, $seconds);
            }
            
            return $songs;
        } catch (PDOException $e) {
            error_log("Error in getArtistSongs: " . $e->getMessage());
            return [];
        }
    }
} 