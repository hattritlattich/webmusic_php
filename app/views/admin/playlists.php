<?php
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    exit('Unauthorized access');
}

require_once __DIR__ . '/../../models/PlaylistModel.php';
$playlistModel = new PlaylistModel();
$message = '';

// Xử lý thêm/sửa/xóa playlist
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $result = $playlistModel->addPlaylist([
                        'name' => trim($_POST['name']),
                        'user_id' => $_SESSION['user_id'],
                        'description' => trim($_POST['description'] ?? '')
                    ]);

                    $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        Thêm playlist thành công!</div>';

                } catch (Exception $e) {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        Lỗi: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
                break;

            case 'edit':
                try {
                    if (empty($_POST['playlist_id'])) {
                        throw new Exception("ID playlist không hợp lệ");
                    }

                    $data = [
                        'id' => $_POST['playlist_id'],
                        'name' => trim($_POST['name']),
                        'description' => trim($_POST['description'] ?? '')
                    ];

                    if ($playlistModel->updatePlaylist($data)) {
                        $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            Cập nhật playlist thành công!</div>';
                    } else {
                        throw new Exception("Không thể cập nhật playlist");
                    }
                } catch (Exception $e) {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        Lỗi: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
                break;

            case 'delete':
                try {
                    if (empty($_POST['playlist_id'])) {
                        throw new Exception("ID playlist không hợp lệ");
                    }

                    if ($playlistModel->deletePlaylist($_POST['playlist_id'])) {
                        $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            Xóa playlist thành công!</div>';
                    } else {
                        throw new Exception("Không thể xóa playlist");
                    }
                } catch (Exception $e) {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        Lỗi: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
                break;
        }
    }
}

$playlists = $playlistModel->getAllPlaylists();
?>

<div class="bg-white rounded-lg shadow-md p-6">
    <?= $message ?>
    
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-semibold">Danh sách Playlist</h3>
        <button onclick="openModal('addPlaylistModal')" class="bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600">
            <i class="fas fa-plus mr-2"></i>Thêm Playlist
        </button>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full bg-white">
            <thead class="bg-gray-100">
                <tr>
                    <th class="py-3 px-4 text-left">ID</th>
                    <th class="py-3 px-4 text-left">Tên Playlist</th>
                    <th class="py-3 px-4 text-left">Người tạo</th>
                    <th class="py-3 px-4 text-left">Mô tả</th>
                    <th class="py-3 px-4 text-left">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($playlists as $playlist): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 px-4"><?= htmlspecialchars($playlist['id']) ?></td>
                    <td class="py-3 px-4"><?= htmlspecialchars($playlist['name']) ?></td>
                    <td class="py-3 px-4"><?= htmlspecialchars($playlist['creator']) ?></td>
                    <td class="py-3 px-4"><?= htmlspecialchars($playlist['description'] ?? '') ?></td>
                    <td class="py-3 px-4">
                        <button onclick="editPlaylist(<?= $playlist['id'] ?>)" 
                                class="text-blue-500 hover:text-blue-700 mr-3">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deletePlaylist(<?= $playlist['id'] ?>)" 
                                class="text-red-500 hover:text-red-700">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Thêm Playlist -->
<div id="addPlaylistModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
    <div class="bg-white rounded-lg p-8 max-w-md w-full">
        <div class="flex justify-between items-center mb-6">
            <h4 class="text-xl font-semibold">Thêm Playlist mới</h4>
            <button onclick="closeModal('addPlaylistModal')" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Tên Playlist</label>
                    <input type="text" name="name" required 
                           class="w-full p-2 border rounded focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Mô tả</label>
                    <textarea name="description" rows="3"
                              class="w-full p-2 border rounded focus:outline-none focus:border-blue-500"></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('addPlaylistModal')"
                        class="px-4 py-2 border rounded hover:bg-gray-100">
                    Hủy
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                    Thêm
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Sửa Playlist -->
<div id="editPlaylistModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
    <div class="bg-white rounded-lg p-8 max-w-md w-full">
        <div class="flex justify-between items-center mb-6">
            <h4 class="text-xl font-semibold">Sửa Playlist</h4>
            <button onclick="closeModal('editPlaylistModal')" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="" method="POST" id="editPlaylistForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="playlist_id" id="edit_playlist_id">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Tên Playlist</label>
                    <input type="text" name="name" id="edit_name" required 
                           class="w-full p-2 border rounded focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Mô tả</label>
                    <textarea name="description" id="edit_description" rows="3"
                              class="w-full p-2 border rounded focus:outline-none focus:border-blue-500"></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('editPlaylistModal')"
                        class="px-4 py-2 border rounded hover:bg-gray-100">
                    Hủy
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                    Cập nhật
                </button>
            </div>
        </form>
    </div>
</div>

<script>
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

async function editPlaylist(playlistId) {
    try {
        const response = await fetch(`?page=admin/playlists/get/${playlistId}`);
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Không thể lấy thông tin playlist');
        }
        
        const playlist = await response.json();
        
        document.getElementById('edit_playlist_id').value = playlist.id;
        document.getElementById('edit_name').value = playlist.name;
        document.getElementById('edit_description').value = playlist.description || '';
        
        openModal('editPlaylistModal');
    } catch (error) {
        alert('Có lỗi xảy ra: ' + error.message);
    }
}

function deletePlaylist(playlistId) {
    if (confirm('Bạn có chắc muốn xóa playlist này?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="playlist_id" value="${playlistId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script> 