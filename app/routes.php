// Thêm route cho API lấy thông tin nghệ sĩ
if (preg_match('/^admin\/artists\/get\/(\d+)$/', $page, $matches)) {
    require_once __DIR__ . '/controllers/ArtistController.php';
    $controller = new ArtistController();
    $controller->getArtist($matches[1]);
    exit;
} 