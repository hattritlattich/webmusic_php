<?php
require_once __DIR__ . '/../core/Database.php';

class SongModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function addSong($data) {
        try {
            // Validate dữ liệu
            if (empty($data['title']) || empty($data['artist']) || 
                empty($data['file_path']) || empty($data['cover_image'])) {
                throw new Exception("Thiếu thông tin bắt buộc");
            }

            // Lấy hoặc tạo artist_id
            $artistId = null;
            if (!empty($data['artist'])) {
                $stmt = $this->db->prepare("SELECT id FROM artists WHERE name = ?");
                $stmt->execute([$data['artist']]);
                $artist = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($artist) {
                    $artistId = $artist['id'];
                } else {
                    // Tạo nghệ sĩ mới nếu chưa tồn tại
                    $stmt = $this->db->prepare("INSERT INTO artists (name) VALUES (?)");
                    $stmt->execute([$data['artist']]);
                    $artistId = $this->db->lastInsertId();
                }
            }

            $sql = "INSERT INTO songs (title, artist, artist_id, album, file_path, cover_image, duration, lyrics_file) 
                    VALUES (:title, :artist, :artist_id, :album, :file_path, :cover_image, :duration, :lyrics_file)";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':title' => $data['title'],
                ':artist' => $data['artist'],
                ':artist_id' => $artistId,
                ':album' => $data['album'] ?? null,
                ':file_path' => $data['file_path'],
                ':cover_image' => $data['cover_image'],
                ':duration' => $data['duration'] ?? 0,
                ':lyrics_file' => $data['lyrics_file'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Error in addSong: " . $e->getMessage());
            throw $e;
        }
    }

    public function getAllSongs() {
        try {
            $sql = "SELECT songs.*, 
                    CASE WHEN liked_songs.song_id IS NOT NULL THEN 1 ELSE 0 END as is_liked
                    FROM songs 
                    LEFT JOIN liked_songs ON songs.id = liked_songs.song_id 
                        AND liked_songs.user_id = :user_id
                    ORDER BY songs.created_at DESC";
                    
            $stmt = $this->db->prepare($sql);
            $userId = $_SESSION['user_id'] ?? 0;
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Đảm bảo duration là số
            foreach ($results as &$song) {
                $song['duration'] = (int)($song['duration'] ?? 0);
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log("Error in getAllSongs: " . $e->getMessage());
            return [];
        }
    }

    public function getSongById($id) {
        try {
            $sql = "SELECT * FROM songs WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getSongById: " . $e->getMessage());
            return false;
        }
    }

    public function deleteSong($id) {
        try {
            // Lấy thông tin bài hát để xóa file
            $song = $this->getSongById($id);
            if ($song) {
                // Xóa files
                @unlink(__DIR__ . '/../../public/' . $song['file_path']);
                @unlink(__DIR__ . '/../../public/' . $song['cover_image']);
                
                // Xóa record trong database
                $sql = "DELETE FROM songs WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                return $stmt->execute([$id]);
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error in deleteSong: " . $e->getMessage());
            return false;
        }
    }

    public function updateSong($data) {
        try {
            if (empty($data['id'])) {
                throw new Exception("ID bài hát không hợp lệ");
            }

            $updates = [];
            $params = [];

            // Cập nhật các trường cơ bản
            foreach (['title', 'artist', 'album'] as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }

            // Cập nhật file_path nếu có
            if (!empty($data['file_path'])) {
                $updates[] = "file_path = :file_path";
                $params[':file_path'] = $data['file_path'];
            }

            // Cập nhật cover_image nếu có
            if (!empty($data['cover_image'])) {
                $updates[] = "cover_image = :cover_image";
                $params[':cover_image'] = $data['cover_image'];
            }
            
            // Cập nhật lyrics_file nếu có
            if (!empty($data['lyrics_file'])) {
                $updates[] = "lyrics_file = :lyrics_file";
                $params[':lyrics_file'] = $data['lyrics_file'];
            }

            if (empty($updates)) {
                return false;
            }

            $params[':id'] = $data['id'];
            $sql = "UPDATE songs SET " . implode(', ', $updates) . " WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);

        } catch (Exception $e) {
            error_log("Error in updateSong: " . $e->getMessage());
            return false;
        }
    }

    public function searchSongs($query) {
        try {
            // Chuẩn bị query cho tìm kiếm
            $searchTerm = "%" . trim($query) . "%";
            
            $sql = "SELECT s.*, 
                    CASE WHEN ls.song_id IS NOT NULL THEN 1 ELSE 0 END as is_liked
                    FROM songs s
                    LEFT JOIN liked_songs ls ON s.id = ls.song_id 
                        AND ls.user_id = :user_id
                    WHERE 
                        s.title LIKE :search 
                        OR s.artist LIKE :search
                    ORDER BY 
                        CASE 
                            WHEN s.title = :exact THEN 1
                            WHEN s.artist = :exact THEN 2
                            WHEN s.title LIKE :start THEN 3
                            WHEN s.artist LIKE :start THEN 4
                            ELSE 5
                        END,
                        s.created_at DESC";

            $stmt = $this->db->prepare($sql);
            
            // Bind các tham số
            $userId = $_SESSION['user_id'] ?? 0;
            $exactQuery = trim($query);
            $startWith = $exactQuery . '%';
            
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':search', $searchTerm);
            $stmt->bindParam(':exact', $exactQuery);
            $stmt->bindParam(':start', $startWith);
            
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Xử lý đường dẫn file cho mỗi bài hát
            foreach ($results as &$song) {
                // Đảm bảo các trường không null
                $song['title'] = $song['title'] ?? '';
                $song['artist'] = $song['artist'] ?? '';
                $song['album'] = $song['album'] ?? '';
                $song['duration'] = $song['duration'] ?? '';
                
                // Xử lý đường dẫn file
                if (!empty($song['file_path'])) {
                    $song['file_path'] = '/uploads/songs/' . basename($song['file_path']);
                }
                
                // Xử lý ảnh bìa
                if (!empty($song['cover_image'])) {
                    $song['cover_image'] = '/uploads/songs/' . basename($song['cover_image']);
                } else {
                    $song['cover_image'] = '/placeholder.svg?height=40&width=40';
                }

                // Đảm bảo trạng thái like
                $song['is_liked'] = (bool)($song['is_liked'] ?? false);
            }

            return $results;
            
        } catch (PDOException $e) {
            error_log("Search error: " . $e->getMessage());
            return [];
        }
    }

    public function getLikedSongs() {
        try {
            $userId = $_SESSION['user_id'] ?? 0;
            
            $sql = "SELECT s.*, 1 as is_liked 
                    FROM songs s
                    INNER JOIN liked_songs ls ON s.id = ls.song_id
                    WHERE ls.user_id = :user_id
                    ORDER BY ls.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Xử lý đường dẫn file cho mỗi bài hát
            foreach ($results as &$song) {
                // Đảm bảo các trường không null
                $song['title'] = $song['title'] ?? '';
                $song['artist'] = $song['artist'] ?? '';
                $song['album'] = $song['album'] ?? '';
                $song['duration'] = $song['duration'] ?? '';
                
                // Xử lý đường dẫn file
                if (!empty($song['file_path'])) {
                    $song['file_path'] = '/uploads/songs/' . basename($song['file_path']);
                }
                
                // Xử lý ảnh bìa
                if (!empty($song['cover_image'])) {
                    $song['cover_image'] = '/uploads/songs/' . basename($song['cover_image']);
                } else {
                    $song['cover_image'] = '/placeholder.svg?height=40&width=40';
                }
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log("Error getting liked songs: " . $e->getMessage());
            return [];
        }
    }

    public function toggleLike($songId) {
        try {
            $userId = $_SESSION['user_id'] ?? 0;
            
            // Kiểm tra xem bài hát đã được like chưa
            $stmt = $this->db->prepare("
                SELECT id FROM liked_songs 
                WHERE song_id = ? AND user_id = ?
            ");
            $stmt->execute([$songId, $userId]);
            
            if ($stmt->fetch()) {
                // Unlike nếu đã like
                $stmt = $this->db->prepare("
                    DELETE FROM liked_songs 
                    WHERE song_id = ? AND user_id = ?
                ");
                $stmt->execute([$songId, $userId]);
                return false;
            } else {
                // Like nếu chưa like
                $stmt = $this->db->prepare("
                    INSERT INTO liked_songs (song_id, user_id) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$songId, $userId]);
                return true;
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            throw new Exception('Không thể cập nhật trạng thái yêu thích');
        }
    }

    public function getLikedSongIds() {
        try {
            $stmt = $this->db->prepare("
                SELECT song_id FROM liked_songs
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function getArtists() {
        try {
            $sql = "SELECT DISTINCT artist, 
                    COUNT(id) as song_count,
                    MIN(cover_image) as artist_image
                    FROM songs 
                    GROUP BY artist 
                    ORDER BY artist ASC";
                    
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Xử lý đường dẫn ảnh cho mỗi nghệ sĩ
            foreach ($results as &$artist) {
                if (!empty($artist['artist_image'])) {
                    $artist['artist_image'] = '/uploads/songs/' . basename($artist['artist_image']);
                } else {
                    $artist['artist_image'] = '/placeholder.svg?height=200&width=200';
                }
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log("Error in getArtists: " . $e->getMessage());
            return [];
        }
    }

    public function getSongsByArtist($artistId) {
        try {
            $sql = "SELECT s.*, a.name as artist_name, a.image as artist_image 
                    FROM songs s
                    JOIN artists a ON s.artist_id = a.id 
                    WHERE s.artist_id = ? 
                       OR (s.title LIKE CONCAT('%', (SELECT name FROM artists WHERE id = ?), '%'))
                       OR (s.artist LIKE CONCAT('%', (SELECT name FROM artists WHERE id = ?), '%'))
                    ORDER BY s.title ASC";
                    
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$artistId, $artistId, $artistId]);
            
            $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($songs as &$song) {
                // Xử lý đường dẫn file nhạc
                if (!empty($song['file_path'])) {
                    $song['url'] = '/uploads/songs/' . basename($song['file_path']);
                }
                
                // Xử lý ảnh bìa
                if (!empty($song['cover_image'])) {
                    $song['image'] = '/uploads/songs/' . basename($song['cover_image']);
                } else {
                    $song['image'] = !empty($song['artist_image']) ? 
                        '/uploads/artists/' . basename($song['artist_image']) : 
                        '/uploads/artists/placeholder.jpg';
                }
                
                // Format duration
                $song['duration'] = $this->formatDuration($song['duration']);
                
                // Đảm bảo các trường không null
                $song['title'] = $song['title'] ?? 'Không có tiêu đề';
                $song['artist'] = $song['artist_name'];
                $song['album'] = $song['album'] ?? 'Single';
            }
            
            return $songs;
        } catch (Exception $e) {
            error_log("Error in getSongsByArtist: " . $e->getMessage());
            return [];
        }
    }

    // Thêm hàm helper để format thời gian
    private function formatDuration($seconds) {
        if (!is_numeric($seconds)) return "0:00";
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        return sprintf("%d:%02d", $minutes, $remainingSeconds);
    }

    public function getTotalSongs() {
        try {
            $sql = "SELECT COUNT(*) FROM songs";
            return $this->db->query($sql)->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error in getTotalSongs: " . $e->getMessage());
            return 0;
        }
    }

    public function getSongsPaginated($offset, $limit) {
        try {
            // Lấy danh sách bài hát có phân trang và thông tin like
            $userId = $_SESSION['user_id'] ?? 0;
            
            $sql = "SELECT s.*, 
                    CASE WHEN ls.id IS NOT NULL THEN 1 ELSE 0 END as is_liked
                    FROM songs s
                    LEFT JOIN liked_songs ls ON s.id = ls.song_id AND ls.user_id = :user_id
                    ORDER BY s.created_at DESC 
                    LIMIT :offset, :limit";
                    
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getSongsPaginated: " . $e->getMessage());
            return [];
        }
    }

    public function getLyrics($songId) {
        try {
            $sql = "SELECT lyrics_file FROM songs WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$songId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['lyrics_file'])) {
                $lyricsPath = __DIR__ . '/../../public/' . $result['lyrics_file'];
                if (file_exists($lyricsPath)) {
                    return file_get_contents($lyricsPath);
                }
            }
            return null;
        } catch (PDOException $e) {
            error_log("Error in getLyrics: " . $e->getMessage());
            return null;
        }
    }
} 