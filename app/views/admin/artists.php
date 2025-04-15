<?php
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ?page=login');
    exit;
}

require_once __DIR__ . '/../../models/ArtistModel.php';
$artistModel = new ArtistModel();
$message = '';

// Xử lý tìm kiếm và phân trang
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10; // Số nghệ sĩ mỗi trang

// Lấy tổng số nghệ sĩ và danh sách nghệ sĩ với phân trang
$totalArtists = $artistModel->getTotalArtists($search);
$totalPages = ceil($totalArtists / $perPage);
$artists = $artistModel->getArtistsWithPagination($search, $page, $perPage);

// Xử lý AJAX request để lấy thông tin nghệ sĩ
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_artist') {
    $artistId = $_GET['id'] ?? 0;
    $artist = $artistModel->getArtistById($artistId);
    
    header('Content-Type: application/json');
    if ($artist) {
        echo json_encode($artist);
    } else {
        echo json_encode(['error' => 'Không tìm thấy nghệ sĩ']);
    }
    exit;
}

// Thêm hàm xử lý lấy thông tin nghệ sĩ vào đầu file, sau phần require
function getArtistData($id, $artistModel) {
    try {
        $artist = $artistModel->getArtistById($id);
        if (!$artist) {
            return ['error' => 'Không tìm thấy nghệ sĩ'];
        }
        return $artist;
    } catch (Exception $e) {
        return ['error' => 'Có lỗi xảy ra khi lấy thông tin nghệ sĩ'];
    }
}

// Xử lý thêm/sửa/xóa nghệ sĩ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    if (empty($_POST['name'])) {
                        throw new Exception("Tên nghệ sĩ không được để trống");
                    }

                    $data = [
                        'name' => trim($_POST['name']),
                        'bio' => trim($_POST['bio'] ?? '')
                    ];

                    // Xử lý upload ảnh
                    if (!empty($_FILES['image']['name'])) {
                        $imageFile = $_FILES['image'];
                        if ($imageFile['error'] === UPLOAD_ERR_OK) {
                            $uploadDir = 'uploads/artists/';
                            $fullUploadPath = __DIR__ . '/../../../public/' . $uploadDir;
                            
                            if (!file_exists($fullUploadPath)) {
                                mkdir($fullUploadPath, 0777, true);
                            }
                            
                            $imageFileName = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", $imageFile['name']);
                            if (move_uploaded_file($imageFile['tmp_name'], $fullUploadPath . $imageFileName)) {
                                $data['image'] = $uploadDir . $imageFileName;
                            }
                        }
                    }

                    if ($artistModel->addArtist($data)) {
                        $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            Thêm nghệ sĩ thành công!</div>';
                    }
                } catch (Exception $e) {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        Lỗi: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
                break;
            case 'edit':
                try {
                    if (empty($_POST['artist_id'])) {
                        throw new Exception("ID nghệ sĩ không hợp lệ");
                    }

                    $data = [
                        'id' => $_POST['artist_id'],
                        'name' => trim($_POST['name']),
                        'bio' => trim($_POST['bio'] ?? '')
                    ];

                    // Xử lý upload ảnh mới nếu có
                    if (!empty($_FILES['image']['name'])) {
                        $imageFile = $_FILES['image'];
                        if ($imageFile['error'] === UPLOAD_ERR_OK) {
                            $uploadDir = 'uploads/artists/';
                            $fullUploadPath = __DIR__ . '/../../../public/' . $uploadDir;
                            
                            if (!file_exists($fullUploadPath)) {
                                mkdir($fullUploadPath, 0777, true);
                            }
                            
                            $imageFileName = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", $imageFile['name']);
                            if (move_uploaded_file($imageFile['tmp_name'], $fullUploadPath . $imageFileName)) {
                                $data['image'] = $uploadDir . $imageFileName;
                            }
                        }
                    }

                    if ($artistModel->updateArtist($data)) {
                        $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            Cập nhật nghệ sĩ thành công!</div>';
                    } else {
                        throw new Exception("Không thể cập nhật nghệ sĩ");
                    }
                } catch (Exception $e) {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        Lỗi: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
                break;
            case 'delete':
                try {
                    if (empty($_POST['artist_id'])) {
                        throw new Exception("ID nghệ sĩ không hợp lệ");
                    }

                    if ($artistModel->deleteArtist($_POST['artist_id'])) {
                        $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            Xóa nghệ sĩ thành công!</div>';
                    } else {
                        throw new Exception("Không thể xóa nghệ sĩ");
                    }
                } catch (Exception $e) {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        Lỗi: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
                break;
        }
    }
}
?>

<div class="bg-[#2f2739] rounded-lg shadow-lg p-6">
    <?= $message ?>
    
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-semibold text-white">Danh sách nghệ sĩ</h3>
        <div class="flex items-center space-x-4">
            <!-- Search box -->
            <form method="GET" class="flex items-center">
                <input type="hidden" name="page" value="admin">
                <input type="hidden" name="section" value="artists">
                <div class="relative">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Tìm kiếm nghệ sĩ..." 
                           class="bg-[#393243] text-white px-4 py-2 rounded-full focus:outline-none focus:ring-2 focus:ring-[#1DB954] w-64">
                    <button type="submit" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-white">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
            <button onclick="openModal('addArtistModal')" class="bg-[#1DB954] text-white px-4 py-2 rounded-full hover:bg-[#1ed760] transition-colors">
                <i class="fas fa-plus mr-2"></i>Thêm nghệ sĩ
            </button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-[#393243]">
                <tr>
                    <th class="py-3 px-4 text-left text-gray-300">ID</th>
                    <th class="py-3 px-4 text-left text-gray-300">Ảnh</th>
                    <th class="py-3 px-4 text-left text-gray-300">Tên nghệ sĩ</th>
                    <th class="py-3 px-4 text-left text-gray-300">Số bài hát</th>
                    <th class="py-3 px-4 text-left text-gray-300">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($artists)): ?>
                    <tr>
                        <td colspan="5" class="py-4 px-4 text-center text-gray-400">
                            <?= $search ? 'Không tìm thấy nghệ sĩ phù hợp' : 'Chưa có nghệ sĩ nào' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($artists as $artist): ?>
                    <tr data-artist-id="<?= htmlspecialchars($artist['id']) ?>">
                        <td class="py-3 px-4 text-gray-300"><?= htmlspecialchars($artist['id']) ?></td>
                        <td class="py-3 px-4">
                            <img src="<?= htmlspecialchars($artist['image']) ?>" 
                                 alt="<?= htmlspecialchars($artist['name']) ?>"
                                 class="w-12 h-12 object-cover rounded">
                        </td>
                        <td class="py-3 px-4 text-white"><?= htmlspecialchars($artist['name']) ?></td>
                        <td class="py-3 px-4 text-gray-300"><?= htmlspecialchars($artist['song_count']) ?></td>
                        <td class="py-3 px-4">
                            <button onclick="editArtist(<?= $artist['id'] ?>)" 
                                    class="text-[#1DB954] hover:text-[#1ed760] transition-colors mr-3">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteArtist(<?= $artist['id'] ?>)" 
                                    class="text-red-400 hover:text-red-300 transition-colors">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="flex justify-center mt-6">
        <div class="flex items-center space-x-2">
            <?php if ($page > 1): ?>
                <a href="?page=admin&section=artists&search=<?= urlencode($search) ?>&page=1" 
                   class="px-3 py-1 rounded bg-[#393243] text-white hover:bg-[#1DB954]">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?page=admin&section=artists&search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>" 
                   class="px-3 py-1 rounded bg-[#393243] text-white hover:bg-[#1DB954]">
                    <i class="fas fa-angle-left"></i>
                </a>
            <?php endif; ?>

            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
                <a href="?page=admin&section=artists&search=<?= urlencode($search) ?>&page=<?= $i ?>" 
                   class="px-3 py-1 rounded <?= $i === $page ? 'bg-[#1DB954] text-white' : 'bg-[#393243] text-white hover:bg-[#1DB954]' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=admin&section=artists&search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>" 
                   class="px-3 py-1 rounded bg-[#393243] text-white hover:bg-[#1DB954]">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?page=admin&section=artists&search=<?= urlencode($search) ?>&page=<?= $totalPages ?>" 
                   class="px-3 py-1 rounded bg-[#393243] text-white hover:bg-[#1DB954]">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Thêm Nghệ Sĩ -->
<div id="addArtistModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
    <div class="bg-[#2f2739] rounded-lg p-5 max-w-md w-full max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-lg font-semibold text-white">Thêm nghệ sĩ mới</h4>
            <button onclick="closeModal('addArtistModal')" class="text-gray-400 hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Tên nghệ sĩ</label>
                    <input type="text" name="name" required 
                           class="w-full p-2 bg-[#393243] border border-[#393243] rounded text-white focus:outline-none focus:border-[#1DB954]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Ảnh đại diện</label>
                    <input type="file" name="image" accept="image/*" required 
                           class="w-full p-2 bg-[#393243] border border-[#393243] rounded text-gray-300 focus:outline-none focus:border-[#1DB954]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Tiểu sử</label>
                    <textarea name="bio" rows="4"
                              class="w-full p-2 bg-[#393243] border border-[#393243] rounded text-white focus:outline-none focus:border-[#1DB954]"
                              placeholder="Nhập tiểu sử nghệ sĩ"></textarea>
                </div>
            </div>
            <div class="mt-4 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('addArtistModal')"
                        class="px-4 py-2 border border-gray-600 rounded text-gray-300 hover:bg-[#393243] transition-colors">
                    Hủy
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-[#1DB954] text-white rounded hover:bg-[#1ed760] transition-colors">
                    Thêm
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Sửa Nghệ Sĩ -->
<div id="editArtistModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
    <div class="bg-[#2f2739] rounded-lg p-5 max-w-md w-full max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-lg font-semibold text-white">Sửa thông tin nghệ sĩ</h4>
            <button onclick="closeModal('editArtistModal')" class="text-gray-400 hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="" method="POST" enctype="multipart/form-data" id="editArtistForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="artist_id" id="edit_artist_id">
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Tên nghệ sĩ</label>
                    <input type="text" name="name" id="edit_name" required 
                           class="w-full p-2 bg-[#393243] border border-[#393243] rounded text-white focus:outline-none focus:border-[#1DB954]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Ảnh đại diện mới (không bắt buộc)</label>
                    <input type="file" name="image" accept="image/*"
                           class="w-full p-2 bg-[#393243] border border-[#393243] rounded text-gray-300 focus:outline-none focus:border-[#1DB954]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Tiểu sử</label>
                    <textarea name="bio" id="edit_bio" rows="4"
                              class="w-full p-2 bg-[#393243] border border-[#393243] rounded text-white focus:outline-none focus:border-[#1DB954]"
                              placeholder="Nhập tiểu sử nghệ sĩ"></textarea>
                </div>
            </div>
            <div class="mt-4 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('editArtistModal')"
                        class="px-4 py-2 border border-gray-600 rounded text-gray-300 hover:bg-[#393243] transition-colors">
                    Hủy
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-[#1DB954] text-white rounded hover:bg-[#1ed760] transition-colors">
                    Cập nhật
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const artistsData = <?= json_encode($artists) ?>;

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        modal.classList.add('hidden');
    }
}

async function editArtist(id) {
    try {
        // Tìm nghệ sĩ trong dữ liệu có sẵn
        const artist = artistsData.find(a => a.id == id);
        
        if (artist) {
            document.getElementById('edit_artist_id').value = artist.id;
            document.getElementById('edit_name').value = artist.name;
            document.getElementById('edit_bio').value = artist.bio || '';
            openModal('editArtistModal');
        } else {
            throw new Error('Không tìm thấy nghệ sĩ');
        }
    } catch (error) {
        alert('Có lỗi xảy ra: ' + error.message);
    }
}

function deleteArtist(id) {
    if (confirm('Bạn có chắc muốn xóa nghệ sĩ này?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="artist_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script> 