<?php
require_once __DIR__ . '/../core/Database.php';

class UserModel {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function getUserByEmail($email) {
        try {
            if (empty($email)) {
                return false;
            }
            $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$email]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getUserByEmail: " . $e->getMessage());
            return false;
        }
    }
    
    public function register($data) {
        try {
            if (empty($data['email']) || empty($data['password']) || empty($data['name'])) {
                throw new Exception("Thiếu thông tin bắt buộc");
            }

            // Kiểm tra email đã tồn tại
            if ($this->getUserByEmail($data['email'])) {
                throw new Exception("Email đã được sử dụng");
            }

            // Tạo username từ email
            $username = explode('@', $data['email'])[0];
            
            // Kiểm tra username đã tồn tại chưa
            $count = 1;
            $originalUsername = $username;
            while ($this->getUserByUsername($username)) {
                $username = $originalUsername . $count;
                $count++;
            }

            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO users (name, email, username, password, role, avatar) 
                    VALUES (:name, :email, :username, :password, :role, :avatar)";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':name' => $data['name'],
                ':email' => $data['email'],
                ':username' => $username,
                ':password' => $hashedPassword,
                ':role' => 'user',
                ':avatar' => '/uploads/avatars/default.png'
            ]);

        } catch (Exception $e) {
            error_log("Error in register: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function login($email, $password) {
        try {
            if (empty($email) || empty($password)) {
                throw new Exception("Vui lòng nhập email và mật khẩu");
            }

            $user = $this->getUserByEmail($email);
            if (!$user) {
                throw new Exception("Email không tồn tại");
            }

            if (!password_verify($password, $user['password'])) {
                throw new Exception("Mật khẩu không đúng");
            }

            // Không trả về password trong session
            unset($user['password']);
            return $user;

        } catch (Exception $e) {
            error_log("Error in login: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getUserById($userId) {
        $sql = "SELECT * FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updateUser($data) {
        try {
            if (empty($data['id'])) {
                throw new Exception("ID người dùng không hợp lệ");
            }

            $updates = [];
            $params = [':id' => $data['id']];

            if (!empty($data['name'])) {
                $updates[] = "name = :name";
                $params[':name'] = $data['name'];
            }

            if (!empty($data['email'])) {
                $updates[] = "email = :email";
                $params[':email'] = $data['email'];
            }

            if (!empty($data['role'])) {
                $updates[] = "role = :role";
                $params[':role'] = $data['role'];
            }

            if (!empty($data['password'])) {
                $updates[] = "password = :password";
                $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            if (!empty($data['avatar'])) {
                $updates[] = "avatar = :avatar";
                $params[':avatar'] = $data['avatar'];
            }

            if (empty($updates)) {
                return false;
            }

            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);

        } catch (Exception $e) {
            error_log("Error in updateUser: " . $e->getMessage());
            return false;
        }
    }
    
    public function getAllUsers() {
        try {
            $sql = "SELECT id, name, email, role, avatar, created_at FROM users ORDER BY created_at DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAllUsers: " . $e->getMessage());
            return [];
        }
    }
    
    public function deleteUser($id) {
        try {
            // Xóa các playlist của user
            $sql = "DELETE FROM playlists WHERE user_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);

            // Xóa user
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Error in deleteUser: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateAvatar($userId, $avatarPath) {
        try {
            $sql = "UPDATE users SET avatar = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$avatarPath, $userId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error in updateAvatar: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateProfile($userId, $data) {
        $sql = "UPDATE users SET name = :name, birthdate = :birthdate, country = :country WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':name' => $data['name'],
            ':birthdate' => $data['birthdate'],
            ':country' => $data['country'],
            ':id' => $userId
        ]);
    }
    
    

    public function countUserPlaylists($userId) {
        try {
            $sql = "SELECT COUNT(*) FROM playlists WHERE user_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0; // Trả về 0 nếu bảng chưa tồn tại
        }
    }

    public function countUserFollowers($userId) {
        try {
            $sql = "SELECT COUNT(*) FROM followers WHERE followed_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    public function countUserFollowing($userId) {
        try {
            $sql = "SELECT COUNT(*) FROM followers WHERE follower_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    // Thêm hàm kiểm tra username
    public function getUserByUsername($username) {
        try {
            $sql = "SELECT * FROM users WHERE username = ? LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$username]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getUserByUsername: " . $e->getMessage());
            return false;
        }
    }
} 