<?php
// Check if user is admin
session_start(); // đảm bảo khởi tạo session

// ⚠️ GÁN QUYỀN ADMIN TẠM ĐỂ TEST:
$_SESSION['user_role'] = 'admin';

// Kiểm tra quyền
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: /');
    exit;
}

$section = $_GET['section'] ?? 'dashboard';

switch ($section) {
    case 'dashboard':
        require_once __DIR__ . '/views/admin/dashboard.php';
        break;
        
    case 'songs':
        // ... code quản lý bài hát giữ nguyên ...
        break;
        
    case 'artists':
        require_once __DIR__ . '/models/ArtistModel.php';
        $artistModel = new ArtistModel();
        
        // Xử lý các request POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // Debug POST data
                error_log("POST Request received in artists section");
                error_log("POST data: " . print_r($_POST, true));
                error_log("FILES data: " . print_r($_FILES, true));

                switch ($_POST['action']) {
                    case 'add':
                        // Validate dữ liệu
                        if (empty($_POST['name'])) {
                            throw new Exception("Tên nghệ sĩ không được để trống");
                        }

                        // Xử lý upload ảnh
                        $imagePath = null;
                        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                            $uploadDir = 'uploads/artists/';
                            $fullUploadPath = __DIR__ . '/../public/' . $uploadDir;
                            
                            // Debug upload path
                            error_log("Upload directory: " . $fullUploadPath);
                            
                            if (!file_exists($fullUploadPath)) {
                                mkdir($fullUploadPath, 0777, true);
                            }
                            
                            $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
                            $targetPath = $fullUploadPath . $fileName;
                            
                            // Debug file upload
                            error_log("Target path: " . $targetPath);
                            
                            if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                                error_log("Upload error: " . error_get_last()['message']);
                                throw new Exception("Không thể upload ảnh");
                            }
                            $imagePath = $uploadDir . $fileName;
                        }

                        // Debug data before adding
                        $artistData = [
                            'name' => trim($_POST['name']),
                            'image' => $imagePath
                        ];
                        error_log("Adding artist with data: " . print_r($artistData, true));

                        // Thêm nghệ sĩ mới
                        $artistId = $artistModel->addArtist($artistData);

                        if (!$artistId) {
                            throw new Exception("Không thể thêm nghệ sĩ");
                        }

                        $_SESSION['success_message'] = 'Thêm nghệ sĩ thành công!';
                        break;

                    case 'edit':
                        // Xử lý edit...
                        break;

                    case 'delete':
                        // Xử lý delete...
                        break;
                }
            } catch (Exception $e) {
                error_log("Error in artist management: " . $e->getMessage());
                $_SESSION['error_message'] = 'Lỗi: ' . $e->getMessage();
            }

            // Debug before redirect
            error_log("Redirecting after POST processing");
            header('Location: ?page=admin&section=artists');
            exit;
        }

        // Xử lý GET request để lấy thông tin nghệ sĩ
        if (isset($_GET['action']) && $_GET['action'] === 'get_artist') {
            header('Content-Type: application/json');
            try {
                $artistId = $_GET['id'] ?? 0;
                $artist = $artistModel->getArtistById($artistId);
                if (!$artist) {
                    throw new Exception("Không tìm thấy nghệ sĩ");
                }
                echo json_encode($artist);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
        }

        // Lấy danh sách nghệ sĩ cho view
        $artists = $artistModel->getAllArtists();
        error_log("Artists data for view: " . print_r($artists, true));
        
        // Load view
        require_once __DIR__ . '/views/admin/artists.php';
        break;
        
    default:
        require_once __DIR__ . '/views/admin/dashboard.php';
        break;
} 