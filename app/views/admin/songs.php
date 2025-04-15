<?php
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ?page=login');
    exit;
}

require_once __DIR__ . '/../../models/SongModel.php';
require_once __DIR__ . '/../../models/ArtistModel.php';
$songModel = new SongModel();
$artistModel = new ArtistModel();
$message = '';

// Xử lý tìm kiếm và phân trang
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10; // Số bài hát mỗi trang

// Lấy tổng số bài hát và danh sách bài hát với phân trang
$totalSongs = $songModel->getTotalSongs($search);
$totalPages = ceil($totalSongs / $perPage);
$songs = $songModel->getSongsWithPagination($search, $page, $perPage);

// Thêm đoạn code này vào đầu file songs.php
$uploadDir = 'public/uploads/songs/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Lấy danh sách nghệ sĩ cho autocomplete
$artists = $artistModel->getAllArtists();

// Hàm tạo tên file an toàn
function sanitizeFileName($name) {
    // Loại bỏ dấu tiếng Việt
    $name = preg_replace('/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/u', 'a', $name);
    $name = preg_replace('/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/u', 'e', $name);
    $name = preg_replace('/(ì|í|ị|ỉ|ĩ)/u', 'i', $name);
    $name = preg_replace('/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/u', 'o', $name);
    $name = preg_replace('/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/u', 'u', $name);
    $name = preg_replace('/(ỳ|ý|ỵ|ỷ|ỹ)/u', 'y', $name);
    $name = preg_replace('/(đ)/u', 'd', $name);
    
    // Chuyển thành chữ thường và loại bỏ ký tự đặc biệt
    $name = strtolower($name);
    $name = preg_replace('/[^a-z0-9\s]/', '', $name);
    
    // Thay khoảng trắng bằng dấu gạch dưới
    $name = preg_replace('/\s+/', '_', $name);
    
    return $name;
}

// Xử lý thêm/sửa/xóa bài hát trước khi có bất kỳ output nào
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    // Kiểm tra file upload
                    if (!isset($_FILES['song_file'], $_FILES['cover_image'])) {
                        throw new Exception("Vui lòng chọn đầy đủ file nhạc và ảnh bìa");
                    }

                    $songFile = $_FILES['song_file'];
                    $coverFile = $_FILES['cover_image'];

                    // Kiểm tra lỗi upload
                    if ($songFile['error'] !== UPLOAD_ERR_OK || $coverFile['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception("Lỗi khi upload file");
                    }

                    // Tạo thư mục upload nếu chưa tồn tại
                    $uploadDir = 'uploads/songs/';
                    $fullUploadPath = __DIR__ . '/../../../public/' . $uploadDir;
                    if (!file_exists($fullUploadPath)) {
                        mkdir($fullUploadPath, 0777, true);
                    }

                    // Tạo tên file an toàn
                    $songFileName = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", $songFile['name']);
                    $coverFileName = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", $coverFile['name']);

                    // Xử lý file lyrics nếu có
                    $lyricsFilePath = null;
                    if (!empty($_POST['lyrics_content'])) {
                        $lyricsContent = trim($_POST['lyrics_content']);
                        if (!empty($lyricsContent)) {
                            // Tạo tên file dựa trên tên bài hát
                            $lyricsFileName = sanitizeFileName($_POST['title']) . '.txt';
                            $lyricsDir = 'uploads/lyrics/';
                            $fullLyricsDir = __DIR__ . '/../../../public/' . $lyricsDir;
                            
                            // Tạo thư mục lyrics nếu chưa tồn tại
                            if (!file_exists($fullLyricsDir)) {
                                mkdir($fullLyricsDir, 0777, true);
                            }
                            
                            // Lưu nội dung vào file
                            if (file_put_contents($fullLyricsDir . $lyricsFileName, $lyricsContent)) {
                                $lyricsFilePath = $lyricsDir . $lyricsFileName;
                            }
                        }
                    }

                    // Upload files
                    if (!move_uploaded_file($songFile['tmp_name'], $fullUploadPath . $songFileName)) {
                        throw new Exception("Không thể lưu file nhạc");
                    }

                    if (!move_uploaded_file($coverFile['tmp_name'], $fullUploadPath . $coverFileName)) {
                        // Xóa file nhạc nếu upload ảnh thất bại
                        unlink($fullUploadPath . $songFileName);
                        throw new Exception("Không thể lưu ảnh bìa");
                    }

                    // Thêm vào database
                    $result = $songModel->addSong([
                        'title' => trim($_POST['title']),
                        'artist' => trim($_POST['artist']),
                        'album' => trim($_POST['album'] ?? ''),
                        'file_path' => $uploadDir . $songFileName,
                        'cover_image' => $uploadDir . $coverFileName,
                        'lyrics_file' => $lyricsFilePath
                    ]);

                    if ($result) {
                        $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            Thêm bài hát thành công!</div>';
                    }
                } catch (Exception $e) {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        Lỗi: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
                break;
            case 'edit':
                try {
                    if (empty($_POST['song_id'])) {
                        throw new Exception("ID bài hát không hợp lệ");
                    }

                    $data = [
                        'id' => $_POST['song_id'],
                        'title' => trim($_POST['title']),
                        'artist' => trim($_POST['artist']),
                        'album' => trim($_POST['album'] ?? '')
                    ];

                    // Xử lý upload file mới nếu có
                    if (!empty($_FILES['song_file']['name'])) {
                        $songFile = $_FILES['song_file'];
                        if ($songFile['error'] === UPLOAD_ERR_OK) {
                            $songFileName = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", $songFile['name']);
                            if (move_uploaded_file($songFile['tmp_name'], $fullUploadPath . $songFileName)) {
                                $data['file_path'] = $uploadDir . $songFileName;
                            }
                        }
                    }

                    if (!empty($_FILES['cover_image']['name'])) {
                        $coverFile = $_FILES['cover_image'];
                        if ($coverFile['error'] === UPLOAD_ERR_OK) {
                            $coverFileName = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", $coverFile['name']);
                            if (move_uploaded_file($coverFile['tmp_name'], $fullUploadPath . $coverFileName)) {
                                $data['cover_image'] = $uploadDir . $coverFileName;
                            }
                        }
                    }

                    // Xử lý file lyrics mới nếu có
                    if (!empty($_POST['lyrics_content'])) {
                        $lyricsContent = trim($_POST['lyrics_content']);
                        if (!empty($lyricsContent)) {
                            // Tạo tên file dựa trên tên bài hát
                            $lyricsFileName = sanitizeFileName($_POST['title']) . '.txt';
                            $lyricsDir = 'uploads/lyrics/';
                            $fullLyricsDir = __DIR__ . '/../../../public/' . $lyricsDir;
                            
                            // Tạo thư mục lyrics nếu chưa tồn tại
                            if (!file_exists($fullLyricsDir)) {
                                mkdir($fullLyricsDir, 0777, true);
                            }
                            
                            // Lưu nội dung vào file
                            if (file_put_contents($fullLyricsDir . $lyricsFileName, $lyricsContent)) {
                                $data['lyrics_file'] = $lyricsDir . $lyricsFileName;
                            }
                        }
                    }

                    if ($songModel->updateSong($data)) {
                        $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            Cập nhật bài hát thành công!</div>';
                    } else {
                        throw new Exception("Không thể cập nhật bài hát");
                    }
                } catch (Exception $e) {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        Lỗi: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
                break;
            case 'delete':
                try {
                    if (empty($_POST['song_id'])) {
                        throw new Exception("ID bài hát không hợp lệ");
                    }

                    if ($songModel->deleteSong($_POST['song_id'])) {
                        $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            Xóa bài hát thành công!</div>';
                    } else {
                        throw new Exception("Không thể xóa bài hát");
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
        <h3 class="text-xl font-semibold text-white">Danh sách bài hát</h3>
        <div class="flex items-center space-x-4">
            <!-- Search box -->
            <form method="GET" class="flex items-center">
                <input type="hidden" name="page" value="admin">
                <input type="hidden" name="section" value="songs">
                <div class="relative">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Tìm kiếm bài hát..." 
                           class="bg-[#393243] text-white px-4 py-2 rounded-full focus:outline-none focus:ring-2 focus:ring-[#1DB954] w-64">
                    <button type="submit" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-white">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
            <button onclick="openModal('addSongModal')" class="bg-[#1DB954] text-white px-4 py-2 rounded-full hover:bg-[#1ed760] transition-colors">
                <i class="fas fa-plus mr-2"></i>Thêm bài hát
            </button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-[#393243]">
                <tr>
                    <th class="py-3 px-4 text-left text-gray-300">ID</th>
                    <th class="py-3 px-4 text-left text-gray-300">Ảnh</th>
                    <th class="py-3 px-4 text-left text-gray-300">Tên bài hát</th>
                    <th class="py-3 px-4 text-left text-gray-300">Nghệ sĩ</th>
                    <th class="py-3 px-4 text-left text-gray-300">Album</th>
                    <th class="py-3 px-4 text-left text-gray-300">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($songs)): ?>
                    <tr>
                        <td colspan="6" class="py-4 px-4 text-center text-gray-400">
                            <?= $search ? 'Không tìm thấy bài hát phù hợp' : 'Chưa có bài hát nào' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($songs as $song): ?>
                    <tr data-song-id="<?= htmlspecialchars($song['id']) ?>">
                        <td class="py-3 px-4 text-gray-300"><?= htmlspecialchars($song['id']) ?></td>
                        <td class="py-3 px-4">
                            <img src="<?= htmlspecialchars($song['cover_image']) ?>" 
                                 alt="<?= htmlspecialchars($song['title']) ?>"
                                 class="w-12 h-12 object-cover rounded">
                        </td>
                        <td class="py-3 px-4 text-white" data-title="<?= htmlspecialchars($song['title']) ?>"><?= htmlspecialchars($song['title']) ?></td>
                        <td class="py-3 px-4 text-gray-300"><?= htmlspecialchars($song['artist']) ?></td>
                        <td class="py-3 px-4 text-gray-300"><?= htmlspecialchars($song['album']) ?></td>
                        <td class="py-3 px-4" data-lyrics-file="<?= htmlspecialchars($song['lyrics_file'] ?? '') ?>">
                            <button onclick="editSong(<?= $song['id'] ?>)" 
                                    class="text-[#1DB954] hover:text-[#1ed760] transition-colors mr-3">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteSong(<?= $song['id'] ?>)" 
                                    class="text-red-400 hover:text-red-300 transition-colors mr-3">
                                <i class="fas fa-trash"></i>
                            </button>
                            <button onclick="showLyrics(<?= $song['id'] ?>)"
                                    class="text-blue-400 hover:text-blue-300 transition-colors">
                                <i class="fas fa-microphone-alt"></i>
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
                <a href="?page=admin&section=songs&search=<?= urlencode($search) ?>&page=1" 
                   class="px-3 py-1 rounded bg-[#393243] text-white hover:bg-[#1DB954]">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?page=admin&section=songs&search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>" 
                   class="px-3 py-1 rounded bg-[#393243] text-white hover:bg-[#1DB954]">
                    <i class="fas fa-angle-left"></i>
                </a>
            <?php endif; ?>

            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
                <a href="?page=admin&section=songs&search=<?= urlencode($search) ?>&page=<?= $i ?>" 
                   class="px-3 py-1 rounded <?= $i === $page ? 'bg-[#1DB954] text-white' : 'bg-[#393243] text-white hover:bg-[#1DB954]' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=admin&section=songs&search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>" 
                   class="px-3 py-1 rounded bg-[#393243] text-white hover:bg-[#1DB954]">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?page=admin&section=songs&search=<?= urlencode($search) ?>&page=<?= $totalPages ?>" 
                   class="px-3 py-1 rounded bg-[#393243] text-white hover:bg-[#1DB954]">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Thêm Bài Hát -->
<div id="addSongModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
    <div class="bg-[#2f2739] rounded-lg p-5 max-w-md w-full max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-lg font-semibold text-white">Thêm bài hát mới</h4>
            <button onclick="closeModal('addSongModal')" class="text-gray-400 hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Tên bài hát</label>
                    <input type="text" name="title" required 
                           class="w-full p-2 bg-[#393243] border border-[#393243] rounded text-white focus:outline-none focus:border-[#1DB954]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Nghệ sĩ</label>
                    <input type="text" name="artist" id="artistInput" list="artistList" required 
                           class="w-full p-2 bg-[#393243] border border-[#393243] rounded text-white focus:outline-none focus:border-[#1DB954]"
                           placeholder="Nhập tên nghệ sĩ">
                    <datalist id="artistList">
                        <?php foreach ($artists as $artist): ?>
                            <option value="<?= htmlspecialchars($artist['name']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Album</label>
                    <input type="text" name="album"
                           class="w-full p-2 bg-[#393243] border border-[#393243] rounded text-white focus:outline-none focus:border-[#1DB954]"
                           placeholder="Nhập tên album (không bắt buộc)">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">File nhạc</label>
                    <input type="file" name="song_file" accept="audio/*" required 
                           class="w-full p-2 bg-[#393243] border border-[#393243] rounded text-gray-300 focus:outline-none focus:border-[#1DB954]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Ảnh bìa</label>
                    <input type="file" name="cover_image" accept="image/*" required 
                           class="w-full p-2 bg-[#393243] border border-[#393243] rounded text-gray-300 focus:outline-none focus:border-[#1DB954]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Lyrics (Lời bài hát)</label>
                    <textarea name="lyrics_content" rows="4"
                              class="w-full p-2 bg-[#393243] border border-[#393243] rounded text-white focus:outline-none focus:border-[#1DB954]"
                              placeholder="Nhập lời bài hát vào đây"></textarea>
                    <span class="text-gray-400 text-xs">Lời bài hát sẽ được lưu tự động với tên file theo tên bài hát</span>
                </div>
            </div>
            <div class="mt-4 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('addSongModal')"
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

<!-- Modal Sửa Bài Hát -->
<div id="editSongModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
    <div class="bg-[#2f2739] rounded-lg p-5 max-w-md w-full max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-lg font-semibold text-white">Sửa bài hát</h4>
            <button onclick="closeModal('editSongModal')" class="text-gray-400 hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="" method="POST" enctype="multipart/form-data" id="editSongForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="song_id" id="edit_song_id">
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Tên bài hát</label>
                    <input type="text" name="title" id="edit_title" required 
                           class="w-full p-2 bg-[#393243] border border-[#393243] rounded text-white focus:outline-none focus:border-[#1DB954]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Nghệ sĩ</label>
                    <input type="text" name="artist" id="edit_artist" list="artistList" required 
                           class="w-full p-2 bg-[#393243] border border-[#393243] rounded text-white focus:outline-none focus:border-[#1DB954]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Album</label>
                    <input type="text" name="album" id="edit_album"
                           class="w-full p-2 bg-[#393243] border border-[#393243] rounded text-white focus:outline-none focus:border-[#1DB954]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">File nhạc mới (không bắt buộc)</label>
                    <input type="file" name="song_file" accept="audio/*"
                           class="w-full p-2 bg-[#393243] border border-[#393243] rounded text-gray-300 focus:outline-none focus:border-[#1DB954]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Ảnh bìa mới (không bắt buộc)</label>
                    <input type="file" name="cover_image" accept="image/*"
                           class="w-full p-2 bg-[#393243] border border-[#393243] rounded text-gray-300 focus:outline-none focus:border-[#1DB954]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Lyrics mới (không bắt buộc)</label>
                    <textarea name="lyrics_content" rows="4"
                              class="w-full p-2 bg-[#393243] border border-[#393243] rounded text-white focus:outline-none focus:border-[#1DB954]"
                              placeholder="Nhập lời bài hát mới vào đây"></textarea>
                    <span class="text-gray-400 text-xs">Để trống nếu không muốn thay đổi lời bài hát</span>
                </div>
            </div>
            <div class="mt-4 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('editSongModal')"
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

<!-- Container Lyrics -->
<div id="lyricsContainer" class="fixed bottom-20 left-0 right-0 bg-[#121212] border-t border-[#282828] p-4 z-30 hidden max-h-[300px] overflow-y-auto">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-white font-bold">Lời bài hát</h3>
        <button onclick="hideLyrics()" class="text-gray-400 hover:text-white">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div id="lyricsText" class="text-white text-lg leading-relaxed">
        <!-- Lyrics sẽ được thêm vào đây -->
    </div>
</div>

<script>
const songsData = <?= json_encode($songs) ?>;
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

async function editSong(id) {
    try {
        // Tìm bài hát trong dữ liệu có sẵn
        const song = songsData.find(s => s.id == id);
        
        if (song) {
            document.getElementById('edit_song_id').value = song.id;
            document.getElementById('edit_title').value = song.title;
            document.getElementById('edit_artist').value = song.artist;
            document.getElementById('edit_album').value = song.album || '';
            openModal('editSongModal');
        } else {
            throw new Error('Không tìm thấy bài hát');
        }
    } catch (error) {
        alert('Có lỗi xảy ra: ' + error.message);
    }
}

function deleteSong(id) {
    if (confirm('Bạn có chắc muốn xóa bài hát này?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="song_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Hàm xử lý hiển thị lyrics khi phát nhạc
function playSongWithLyrics(url, title, artist, image, songId, lyricsFile) {
    // Phát nhạc
    playSong(url, title, artist, image);
    
    // Tải lyrics nếu có
    if (lyricsFile) {
        loadAndShowLyrics(songId, title, lyricsFile);
    }
}

// Hàm tải và hiển thị lyrics
function loadAndShowLyrics(songId, title, lyricsFile) {
    const lyricsContainer = document.getElementById('lyricsContainer');
    const lyricsText = document.getElementById('lyricsText');
    
    if (!lyricsContainer || !lyricsText) {
        console.error('Không tìm thấy container lyrics');
        return;
    }
    
    // Hiển thị loading
    lyricsText.innerHTML = '<div class="flex justify-center items-center py-4"><div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-white"></div></div>';
    lyricsContainer.classList.remove('hidden');
    
    // Kiểm tra cache
    const cachedLyrics = localStorage.getItem(`lyrics_${songId}`);
    if (cachedLyrics) {
        displayLyrics(cachedLyrics, lyricsText);
        return;
    }
    
    // Nếu có đường dẫn lyrics file cụ thể
    if (lyricsFile) {
        fetch(lyricsFile)
            .then(response => {
                if (!response.ok) throw new Error('Không tìm thấy file lyrics');
                return response.text();
            })
            .then(lyrics => {
                if (lyrics.trim()) {
                    localStorage.setItem(`lyrics_${songId}`, lyrics);
                    displayLyrics(lyrics, lyricsText);
                } else {
                    throw new Error('File lyrics rỗng');
                }
            })
            .catch(error => {
                console.error('Lỗi khi tải lyrics:', error);
                lyricsText.innerHTML = '<div class="text-center text-gray-400 py-4">Không tìm thấy lời bài hát</div>';
            });
        return;
    }
    
    // Nếu không có đường dẫn cụ thể, thử tìm theo tên bài hát
    const lyricsFileName = title.toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '') + '.txt';
    
    const possiblePaths = [
        `/uploads/lyrics/${lyricsFileName}`,
        `uploads/lyrics/${lyricsFileName}`
    ];
    
    tryLoadFromPaths(possiblePaths, 0, lyricsText, songId);
}

// Hàm hiển thị lyrics
function displayLyrics(lyrics, container) {
    const lines = lyrics.split('\n').filter(line => line.trim());
    container.innerHTML = lines.map(line => 
        `<div class="lyrics-line mb-2">${line}</div>`
    ).join('');
}

// Hàm thử tải lyrics từ nhiều đường dẫn
function tryLoadFromPaths(paths, index, container, songId) {
    if (index >= paths.length) {
        container.innerHTML = '<div class="text-center text-gray-400 py-4">Không tìm thấy lời bài hát</div>';
        return;
    }

    fetch(paths[index])
        .then(response => {
            if (!response.ok) throw new Error('Không tìm thấy file');
            return response.text();
        })
        .then(lyrics => {
            if (lyrics.trim()) {
                if (songId) {
                    localStorage.setItem(`lyrics_${songId}`, lyrics);
                }
                displayLyrics(lyrics, container);
            } else {
                throw new Error('File rỗng');
            }
        })
        .catch(error => {
            console.log(`Lỗi khi tải từ ${paths[index]}:`, error);
            tryLoadFromPaths(paths, index + 1, container, songId);
        });
}

// Hàm hiển thị lyrics từ nút trong bảng
function showLyrics(songId) {
    const row = document.querySelector(`tr[data-song-id="${songId}"]`);
    if (!row) {
        console.error('Không tìm thấy thông tin bài hát');
        return;
    }
    
    const title = row.querySelector('[data-title]').getAttribute('data-title');
    const lyricsFile = row.querySelector('[data-lyrics-file]').getAttribute('data-lyrics-file');
    
    loadAndShowLyrics(songId, title, lyricsFile);
}

// Hàm ẩn lyrics
function hideLyrics() {
    const container = document.getElementById('lyricsContainer');
    if (container) {
        container.classList.add('hidden');
    }
}

// Thêm sự kiện đóng lyrics khi click nút close
document.addEventListener('DOMContentLoaded', function() {
    const closeBtn = document.querySelector('#lyricsContainer .fa-times');
    if (closeBtn) {
        closeBtn.parentElement.addEventListener('click', hideLyrics);
    }
});
</script>
</body>
</html> 