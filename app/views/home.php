<?php
require_once __DIR__ . '/../models/ArtistModel.php';
require_once __DIR__ . '/../models/SongModel.php';
require_once __DIR__ . '/../models/PlaylistModel.php';

// Validate page
$page = isset($_GET['page']) ? htmlspecialchars($_GET['page'], ENT_QUOTES, 'UTF-8') : 'home';

// Khởi tạo models
$songModel = new SongModel();
$artistModel = new ArtistModel();

$userId = $_SESSION['user_id'] ?? 0;
$playlistModel = new PlaylistModel();
$userPlaylists = $playlistModel->getUserPlaylists($userId);

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // THÊM BÀI HÁT VÀO PLAYLIST
        if (isset($_POST['playlist_id'], $_POST['song_id'])) {
            $playlistId = (int) $_POST['playlist_id'];
            $songId = (int) $_POST['song_id'];

            if (isset($_POST['action']) && $_POST['action'] === 'remove_song') {
                // Xóa bài hát khỏi playlist
                $result = $playlistModel->removeSongFromPlaylist($playlistId, $songId);
                if ($result['success']) {
                    $_SESSION['message'] = 'Đã xóa bài hát khỏi playlist thành công!';
                } else {
                    $_SESSION['error'] = $result['message'] ?? 'Lỗi khi xóa bài hát khỏi playlist.';
                }
            } else {
                // Thêm bài hát vào playlist
                $result = $playlistModel->addSongToPlaylist($playlistId, $songId);
                if ($result['success']) {
                    $_SESSION['message'] = 'Đã thêm bài hát vào playlist thành công!';
                } elseif ($result['error'] === 'song_exists') {
                    $_SESSION['message'] = 'Bài hát đã có trong playlist này.';
                } else {
                    $_SESSION['error'] = $result['message'] ?? 'Lỗi khi thêm bài hát vào playlist.';
                }
            }

            header("Location: /home");
            exit;
        }

        // TẠO PLAYLIST MỚI
        if (isset($_POST['name']) && isset($_SESSION['user_id'])) {
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $userId = $_SESSION['user_id'];

            if (empty($name)) {
                throw new Exception('Tên danh sách phát không được để trống');
            }

            $playlistModel->addPlaylist([
                'name' => $name,
                'description' => $description,
                'user_id' => $userId
            ]);

            $_SESSION['message'] = 'Tạo danh sách phát thành công!';
            header('Location: /home');
            exit;
        }

        // LIKE / UNLIKE
        if (($_POST['action'] ?? '') === 'toggle_like') {
            if (empty($_SESSION['user_id'])) {
                header("Location: ?page=login");
                exit;
            }
            if (!empty($_POST['song_id'])) {
                $songModel->toggleLike((int)$_POST['song_id']);
                $_SESSION['success_message'] = 'Đã cập nhật trạng thái yêu thích';
            }
            header("Location: ?page=home");
            exit;
        }

        // XÓA PLAYLIST
        if (($_POST['action'] ?? '') === 'delete' && isset($_POST['playlist_id'])) {
            if (empty($_SESSION['user_id'])) {
                header("Location: ?page=login");
                exit;
            }
            
            $playlistId = (int)$_POST['playlist_id'];
            
            // Kiểm tra xem playlist có thuộc về người dùng hiện tại không
            $playlist = $playlistModel->getPlaylistById($playlistId);
            if ($playlist && $playlist['user_id'] == $_SESSION['user_id']) {
                if ($playlistModel->deletePlaylist($playlistId)) {
                    $_SESSION['message'] = 'Đã xóa playlist thành công!';
                } else {
                    $_SESSION['error'] = 'Không thể xóa playlist. Vui lòng thử lại sau.';
                }
            } else {
                $_SESSION['error'] = 'Bạn không có quyền xóa playlist này.';
            }
            
            header("Location: /home");
            exit;
        }

    } catch (Exception $e) {
        $_SESSION['error'] = 'Lỗi: ' . htmlspecialchars($e->getMessage());
        header("Location: /home");
        exit;
    }
}

// Xử lý tìm kiếm nếu có
$songs = [];
$searchQuery = trim($_GET['search'] ?? '');
if ($page === 'home') {
    $songs = $searchQuery !== ''
        ? $songModel->searchSongs($searchQuery)
        : $songModel->getAllSongs();
}

// Lấy danh sách nghệ sĩ
$artists = $artistModel->getAllArtists();

// Phân trang bài hát
$itemsPerPage = 10;
$totalSongs = count($songs);
$totalPages = ceil($totalSongs / $itemsPerPage);
$currentPage = max(1, min((int)($_GET['p'] ?? 1), $totalPages));
$offset = ($currentPage - 1) * $itemsPerPage;
$currentPageSongs = array_slice($songs, $offset, $itemsPerPage);

// Helper: định dạng thời gian
function formatDuration($seconds) {
    if (!is_numeric($seconds)) return '0:00';
    return sprintf("%d:%02d", floor($seconds / 60), $seconds % 60);
}

// Helper: hiển thị an toàn
function safeDisplay($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="description" content="Music streaming website">
    <title>Trang chủ - Music Chill</title>
    
    <!-- Preload quan trọng resources -->
    <link rel="preload" href="https://cdn.tailwindcss.com" as="script">
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style">
    
    <!-- Thêm nonce cho script -->
    <?php $nonce = base64_encode(random_bytes(16)); ?>
    <script nonce="<?= $nonce ?>" src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        spotify: {
                            green: '#1DB954',
                            black: '#191414',
                            darkgray: '#121212',
                            lightgray: '#282828',
                            white: '#FFFFFF',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Circular', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        }
        .progress-bar {
            -webkit-appearance: none;
            appearance: none;
            height: 4px;
            width: 100%;
        }
        .progress-bar::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 12px;
            height: 12px;
            background: white;
            border-radius: 50%;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .progress-container:hover .progress-bar::-webkit-slider-thumb {
            opacity: 1;
        }
        input[type="range"] {
            -webkit-appearance: none;
            appearance: none;
            height: 4px;
            border-radius: 2px;
        }
    </style>
</head>
<body class="bg-spotify-black text-spotify-white">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="w-64 bg-black flex-shrink-0 hidden md:block">
            <div class="p-6">
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-white mb-6">
                        <i class="fab fa-spotify mr-2"></i>Spotify
                    </h1>
                    <ul class="space-y-4">
                        <li class="flex items-center text-white font-semibold">
                            <i class="fas fa-home mr-4"></i>
                            <a href="?page=home">Trang chủ</a>
                        </li>
                    </ul>
                </div>
                <div class="mb-8">
                    <div class="flex items-center mb-4">
                        <div class="w-8 h-8 bg-spotify-green flex items-center justify-center rounded-sm mr-3">
                            <i class="fas fa-plus text-black"></i>
                        </div>
                        <span class="font-semibold cursor-pointer" onclick="<?= empty($_SESSION['user_id']) ? 'window.location.href=\'?page=login\'' : 'openCreatePlaylistModal()' ?>">Tạo Danh Sách</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-gradient-to-br from-purple-700 to-gray-400 flex items-center justify-center rounded-sm mr-3">
                            <i class="fas fa-heart text-white"></i>
                        </div>
                        <span class="font-semibold">Liked Songs</span>
                    </div>
                </div>
                <div class="border-t border-gray-800 pt-4">
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <?php
                        require_once __DIR__ . '/../models/PlaylistModel.php';
                        $playlistModel = new PlaylistModel();
                        $userPlaylists = $playlistModel->getUserPlaylists($_SESSION['user_id']);
                        ?>
                        
                        <h3 class="text-gray-400 text-sm font-semibold mb-4">DANH SÁCH PHÁT CỦA BẠN</h3>
                        <div id="userPlaylists" class="space-y-2">
                            <?php if (empty($userPlaylists)): ?>
                                <p class="text-gray-500 text-sm italic">Bạn chưa có playlist nào.</p>
                            <?php else: ?>
                                <?php foreach ($userPlaylists as $pl): ?>
    <div class="text-sm text-gray-300 hover:text-white cursor-pointer" onclick="openPlaylist(<?= $pl['id'] ?>)">
        <i class="fas fa-music mr-2 text-gray-500"></i>
        <?= htmlspecialchars($pl['name']) ?>
    </div>
<?php endforeach; ?>

                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
       
<script>
    function openPlaylist(playlistId) {
        // Chuyển hướng đến trang playlist tương ứng
        window.location.href = "?page=playlist&id=" + playlistId;
    }
</script>
</script>
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <div role="banner" class="bg-spotify-darkgray p-4 flex items-center justify-between">
                <div class="flex items-center">
                    <button cxlass="w-8 h-8 rounded-full bg-black flex items-center justify-center mr-4 md:hidden">
                        <i class="fas fa-bars text-white"></i>
                    </button>
                    <div class="flex space-x-2">
                        <button class="w-8 h-8 rounded-full bg-black flex items-center justify-center">
                            <i class="fas fa-chevron-left text-white"></i>
                        </button>
                        <button class="w-8 h-8 rounded-full bg-black flex items-center justify-center">
                            <i class="fas fa-chevron-right text-white"></i>
                        </button>
                    </div>
                </div>
                <div class="flex items-center">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="flex items-center mr-4 relative">
                            <button onclick="toggleDropdown()" class="flex items-center gap-2 p-2 rounded-full hover:bg-[#282828]">
                                <img src="<?= $_SESSION['user_avatar'] ?? '/placeholder.svg?height=32&width=32' ?>" 
                                     alt="Profile" 
                                     class="w-8 h-8 rounded-full object-cover">
                                <span class="text-sm text-gray-300">
                                    <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']) ?>
                                </span>
                                <i class="fas fa-chevron-down text-sm" id="dropdown-arrow"></i>
                            </button>

                            <!-- Dropdown Menu -->
                            <div id="dropdown-menu" class="hidden absolute right-0 top-full mt-2 w-48 bg-[#282828] rounded-md shadow-lg py-1">
                                <a href="?page=profile" class="block px-4 py-2 text-sm text-gray-300 hover:bg-[#333333]">
                                    <i class="fas fa-user mr-2"></i>
                                    Hồ sơ
                                </a>
                                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): ?>
                                    <a href="?page=admin" class="block px-4 py-2 text-sm text-gray-300 hover:bg-[#333333]">
                                        <i class="fas fa-cog mr-2"></i>
                                        Quản lý Admin
                                    </a>
                                <?php endif; ?>
                                <div class="border-t border-gray-700 my-1"></div>
                                <a href="?page=logout" class="block px-4 py-2 text-sm text-gray-300 hover:bg-[#333333]">
                                    <i class="fas fa-sign-out-alt mr-2"></i>
                                    Đăng xuất
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="space-x-4">
                            <a href="?page=register" class="text-gray-400 hover:text-white">
                                Đăng ký
                            </a>
                            <a href="?page=login" class="bg-white text-black font-bold py-2 px-4 rounded-full">
                                Đăng nhập
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Content Area -->
            <main role="main" id="main-content" class="flex-1 overflow-y-auto bg-gradient-to-b from-[#3333aa]/30 to-spotify-darkgray p-8">
                <?php if($page !== 'playlist'): ?>
                <!-- Biểu đồ ZingChart -->
                <section class="mb-8">
                    <h2 class="text-3xl font-bold mb-4">#zingchart</h2>
                    <div class="bg-[#231B2E] rounded-lg p-4">
                        <canvas id="zingChart" width="100" height="60"></canvas>
                    </div>
                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <script>
                        const ctx = document.getElementById('zingChart').getContext('2d');
                        const zingChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: ['00:00', '02:00', '04:00', '06:00', '08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00'],
                                datasets: [{
                                    label: 'Lượt nghe',
                                    data: [30, 20, 50, 40, 60, 70, 80, 90, 100, 110, 120],
                                    borderColor: 'rgba(76, 217, 217, 1)',
                                    backgroundColor: 'rgba(76, 217, 217, 0.1)',
                                    borderWidth: 2,
                                    tension: 0.4,
                                    pointRadius: 3,
                                    pointBackgroundColor: 'rgba(76, 217, 217, 1)',
                                    fill: false
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: true,
                                        position: 'top',
                                        labels: {
                                            color: 'rgba(255, 255, 255, 0.7)',
                                            boxWidth: 15
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        beginAtZero: true,
                                        grid: {
                                            color: 'rgba(255, 255, 255, 0.1)'
                                        },
                                        ticks: {
                                            color: 'rgba(255, 255, 255, 0.7)',
                                            font: {
                                                size: 9
                                            },
                                            maxRotation: 0,
                                            autoSkip: true,
                                            maxTicksLimit: 5
                                        }
                                    },
                                    y: {
                                        beginAtZero: true,
                                        grid: {
                                            color: 'rgba(255, 255, 255, 0.1)'
                                        },
                                        ticks: {
                                            color: 'rgba(255, 255, 255, 0.7)',
                                            font: {
                                                size: 9
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    </script>
                </section>

                <!-- Top Bài Hát Nghe Nhiều -->
                <?php if($page !== 'playlist'): ?>
                <section class="mb-8" id="top-songs-section">
                    <h2 class="text-2xl font-bold mb-4">Top Bài Hát Được Yêu Thích Nhất</h2>
                    <div id="top-songs-container">
                        <?php
                        $topLikedSongs = $songModel->getTopLikedSongs(5);
                        if (empty($topLikedSongs)): 
                        ?>
                            <p class="text-gray-400">Chưa có bài hát nào được yêu thích.</p>
                        <?php else: ?>
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="text-gray-400">
                                        <th class="pb-3">#</th>
                                        <th class="pb-3">Bài hát</th>
                                        <th class="pb-3">Nghệ sĩ</th>
                                        <th class="pb-3">Lượt thích</th>
                                        <th class="pb-3"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topLikedSongs as $index => $song): ?>
                                        <tr class="hover:bg-[#2f2739] group">
                                            <td class="py-2 px-2 relative">
                                                <div class="flex items-center">
                                                    <?php if ($index < 3): ?>
                                                        <span class="group-hover:hidden <?= $index === 0 ? 'text-yellow-500' : ($index === 1 ? 'text-gray-300' : 'text-yellow-600') ?> font-bold">
                                                            <?= $index === 0 ? '<i class="fas fa-crown"></i>' : ($index === 1 ? '<i class="fas fa-medal"></i>' : '<i class="fas fa-award"></i>') ?>
                                                            <?= $index + 1 ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="group-hover:hidden"><?= $index + 1 ?></span>
                                                    <?php endif; ?>
                                                    <button type="button" 
                                                            class="hidden group-hover:block text-white hover:scale-110 transition-transform absolute left-1/2 -translate-x-1/2"
                                                            onclick="playSongAndUpdatePlays(
                                                                '<?= htmlspecialchars($song['file_path']) ?>', 
                                                                '<?= htmlspecialchars($song['title']) ?>', 
                                                                '<?= htmlspecialchars($song['artist']) ?>', 
                                                                '<?= htmlspecialchars($song['cover_image']) ?>',
                                                                <?= $song['id'] ?>
                                                            )">
                                                        <i class="fas fa-play text-lg"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <div class="flex items-center cursor-pointer" 
                                                     onclick="playSongAndUpdatePlays(
                                                        '<?= htmlspecialchars($song['file_path']) ?>', 
                                                        '<?= htmlspecialchars($song['title']) ?>', 
                                                        '<?= htmlspecialchars($song['artist']) ?>', 
                                                        '<?= htmlspecialchars($song['cover_image']) ?>',
                                                        <?= $song['id'] ?>
                                                     )">
                                                    <div class="relative w-10 h-10 mr-3 flex-shrink-0">
                                                        <img src="<?= htmlspecialchars($song['cover_image']) ?>" 
                                                             alt="<?= htmlspecialchars($song['title']) ?>" 
                                                             class="w-full h-full rounded object-cover">
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <div class="text-white text-sm font-medium">
                                                            <?= htmlspecialchars($song['title']) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-2 text-gray-400"><?= htmlspecialchars($song['artist']) ?></td>
                                            <td class="py-2 text-gray-400"><?= $song['like_count'] ?></td>
                                            <td class="py-2">
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle_like">
                                                    <input type="hidden" name="song_id" value="<?= $song['id'] ?>">
                                                    <button type="submit" class="like-button <?= $song['is_liked'] ? 'text-red-500' : 'text-gray-400 hover:text-white' ?>">
                                                        <i class="<?= $song['is_liked'] ? 'fas' : 'far' ?> fa-heart"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </section>
                <?php endif; ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-md shadow-lg z-50" id="success-message">
                        <?= htmlspecialchars($_SESSION['success_message']) ?>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                    <script>
                        setTimeout(() => {
                            document.getElementById('success-message').style.display = 'none';
                        }, 3000);
                    </script>
                <?php endif; ?>
                <?php if($page === 'home'): ?>
                    <!-- Phần hiển thị thông báo -->
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="bg-green-500 text-white px-4 py-2 rounded mb-4">
                            <?= htmlspecialchars($_SESSION['success_message']) ?>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="bg-red-500 text-white px-4 py-2 rounded mb-4">
                            <?= htmlspecialchars($_SESSION['error_message']) ?>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>

                    <!-- Form tìm kiếm đơn giản -->
                    <form method="GET" action="" class="mb-6">
                        <input type="hidden" name="page" value="home">
                        <div class="relative max-w-xl">
                            <input type="text" 
                                   name="search" 
                                   value="<?= htmlspecialchars($searchQuery) ?>"
                                   placeholder="Tìm kiếm nghệ sĩ hoặc bài hát" 
                                   class="w-full bg-white text-black py-2 px-4 pl-10 rounded-full focus:outline-none text-sm">
                            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                        </div>
                    </form>

                    <?php if (empty($searchQuery) && isset($_SESSION['user_id'])): ?>
                        <!-- Phần Danh sách đã thích -->
                        <section class="mb-8">
                            <div class="flex items-center gap-3 mb-6">
                                <h2 class="text-2xl font-bold">Danh sách đã thích</h2>
                                <i class="fas fa-heart text-red-500"></i>
                                <div class="ml-auto flex gap-2">
                                    <button onclick="playAllLikedSongs()" class="bg-[#1DB954] hover:bg-[#1ed760] text-white px-4 py-2 rounded-full text-sm font-medium flex items-center gap-2">
                                        <i class="fas fa-play"></i>
                                        Phát tất cả
                                    </button>
                                    <button onclick="shuffleLikedSongs()" class="bg-[#2f2739] hover:bg-[#393243] text-white px-4 py-2 rounded-full text-sm font-medium flex items-center gap-2">
                                        <i class="fas fa-random"></i>
                                        Phát ngẫu nhiên
                                    </button>
                                </div>
                            </div>
                            <div class="bg-[#170f23] rounded-lg p-4">
                                <?php
                                $likedSongs = $songModel->getLikedSongs();
                                if (empty($likedSongs)): 
                                ?>
                                    <div class="text-center py-8">
                                        <div class="text-gray-400 mb-2">Chưa có bài hát yêu thích</div>
                                        <div class="text-sm text-gray-500">Hãy thêm bài hát vào danh sách yêu thích của bạn</div>
                                    </div>
                                <?php else: ?>
                                    <table class="w-full">
                                        <thead>
                                            <tr class="text-gray-400 text-sm border-b border-gray-700">
                                                <th class="pb-3 w-[5%] font-normal text-left">#</th>
                                                <th class="pb-3 w-[45%] font-normal text-left">Bài hát</th>
                                                <th class="pb-3 w-[25%] font-normal text-left">Album</th>
                                                <th class="pb-3 w-[15%] font-normal text-right">Thời gian</th>
                                                <th class="pb-3 w-[10%] font-normal text-center">Yêu thích</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($likedSongs as $index => $song): ?>
                                                <tr class="group hover:bg-[#2f2739] text-gray-400">
                                                    <td class="py-[10px] px-2 relative">
                                                        <div class="flex items-center">
                                                            <span class="group-hover:hidden"><?= $index + 1 ?></span>
                                                            <button type="button" 
                                                                    class="hidden group-hover:block text-white hover:scale-110 transition-transform absolute left-1/2 -translate-x-1/2"
                                                                    onclick="playSongAndUpdatePlays(
                                                                        '<?= htmlspecialchars($song['file_path']) ?>', 
                                                                        '<?= htmlspecialchars($song['title']) ?>', 
                                                                        '<?= htmlspecialchars($song['artist']) ?>', 
                                                                        '<?= htmlspecialchars($song['cover_image']) ?>'
                                                                    )">
                                                                <i class="fas fa-play text-lg"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td class="py-[10px]">
                                                        <div class="flex items-center cursor-pointer group/title" 
                                                             onclick="playSong(
                                                                '<?= htmlspecialchars($song['file_path']) ?>', 
                                                                '<?= htmlspecialchars($song['title']) ?>', 
                                                                '<?= htmlspecialchars($song['artist']) ?>', 
                                                                '<?= htmlspecialchars($song['cover_image']) ?>'
                                                             )">
                                                            <div class="relative w-10 h-10 mr-3 flex-shrink-0">
                                                                <img src="<?= htmlspecialchars($song['cover_image']) ?>" 
                                                                     alt="<?= htmlspecialchars($song['title']) ?>" 
                                                                     class="w-full h-full rounded object-cover">
                                                            </div>
                                                            <div class="flex flex-col">
                                                                <div class="text-white text-sm font-medium group-hover/title:text-[#1DB954]">
                                                                    <?= htmlspecialchars($song['title']) ?>
                                                                </div>
                                                                <div class="text-gray-400 text-xs hover:underline">
                                                                    <?= htmlspecialchars($song['artist']) ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="py-[10px] text-sm"><?= htmlspecialchars($song['album']) ?></td>
                                                    <td class="py-[10px] text-right text-sm text-gray-400">
                                                        <?= formatDuration($song['duration']) ?>
                                                    </td>
                                                    <td class="py-[10px] text-center">
                                                        <form method="POST" action="" style="display: inline;">
                                                            <input type="hidden" name="action" value="toggle_like">
                                                            <input type="hidden" name="song_id" value="<?= $song['id'] ?>">
                                                            <button type="submit" class="like-button <?= $song['is_liked'] ? 'text-red-500' : 'text-gray-400 hover:text-white' ?>">
                                                                <i class="<?= $song['is_liked'] ? 'fas' : 'far' ?> fa-heart"></i>
                                                            </button>
                                                        </form>
                                                        <button onclick="<?= empty($_SESSION['user_id']) ? 'window.location.href=\'?page=login\'' : 'openAddToPlaylistModal({
                                                            url: \'' . htmlspecialchars($song['file_path']) . '\',
                                                            title: \'' . htmlspecialchars($song['title']) . '\',
                                                            artist: \'' . htmlspecialchars($song['artist']) . '\',
                                                            image: \'' . htmlspecialchars($song['cover_image']) . '\'
                                                        })' ?>" class="text-gray-400 hover:text-white ml-2">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </section>

                        <!-- Phần danh sách nghệ sĩ -->
                        <section class="mb-8">
                            <h2 class="text-2xl font-bold mb-6">Danh Sách Nghệ Sĩ</h2>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                                <?php 
                                if (empty($artists)): ?>
                                    <p class="text-gray-400">Chưa có nghệ sĩ nào</p>
                                <?php else:
                                    foreach ($artists as $artist): 
                                        // Chỉ hiển thị nghệ sĩ có hình ảnh
                                        if (!empty($artist['image'])): 
                                    ?>
                                        <div class="bg-[#170f23] p-4 rounded-lg hover:bg-[#2f2739] transition-colors group cursor-pointer"
                                             onclick="showArtistSongs(<?= $artist['id'] ?>, '<?= htmlspecialchars($artist['name'], ENT_QUOTES) ?>')">
                                            <div class="block">
                                                <div class="relative mb-4 aspect-square">
                                                    <img src="<?= htmlspecialchars($artist['image']) ?>" 
                                                         alt="<?= htmlspecialchars($artist['name']) ?>" 
                                                         class="w-full h-full object-cover rounded-full shadow-lg"
                                                         onerror="this.src='/uploads/artists/placeholder.jpg'">
                                                    <div class="absolute bottom-2 right-2 bg-[#1DB954] rounded-full p-3 opacity-0 group-hover:opacity-100 transform group-hover:translate-y-0 translate-y-2 transition-all shadow-xl">
                                                        <i class="fas fa-play text-white"></i>
                                                    </div>
                                                </div>
                                                <div class="text-center">
                                                    <h3 class="text-white font-bold mb-1 truncate">
                                                        <?= htmlspecialchars($artist['name']) ?>
                                                    </h3>
                                                    <p class="text-gray-400 text-sm">
                                                        <?= $artist['song_count'] ?> bài hát
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php 
                                        endif;
                                    endforeach; 
                                endif; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <!-- Phần Danh sách bài hát -->
                    <section id="songs" class="mb-8">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold">
                                <?php if (!empty($searchQuery)): ?>
                                    Kết quả tìm kiếm cho "<?= htmlspecialchars($searchQuery) ?>" (<?= $totalSongs ?> bài hát)
                                <?php else: ?>
                                    Danh sách bài hát (<?= $totalSongs ?> bài hát)
                                <?php endif; ?>
                            </h2>
                        </div>

                        <div class="bg-[#170f23] rounded-lg p-4">
                            <?php if (empty($songs)): ?>
                                <div class="text-center py-8">
                                    <div class="text-gray-400 mb-2">Chưa có bài hát nào</div>
                                </div>
                            <?php else: ?>
                                <table class="w-full">
                                    <thead>
                                        <tr class="text-gray-400 text-sm border-b border-gray-700">
                                            <th class="pb-3 w-[5%] font-normal text-left">#</th>
                                            <th class="pb-3 w-[45%] font-normal text-left">Bài hát</th>
                                            <th class="pb-3 w-[25%] font-normal text-left">Album</th>
                                            <th class="pb-3 w-[15%] font-normal text-right">Thời gian</th>
                                            <th class="pb-3 w-[10%] font-normal text-center">Yêu thích</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Hiển thị số thứ tự chính xác cho mỗi trang
                                        foreach ($currentPageSongs as $index => $song): 
                                            $realIndex = $offset + $index + 1;
                                        ?>
                                            <tr class="group hover:bg-[#2f2739] text-gray-400">
                                                <td class="py-[10px] px-2 relative">
                                                    <div class="flex items-center">
                                                        <span class="group-hover:hidden"><?= $realIndex ?></span>
                                                        <button type="button" 
                                                                class="hidden group-hover:block text-white hover:scale-110 transition-transform absolute left-1/2 -translate-x-1/2"
                                                                onclick="playSongAndUpdatePlays(
                                                                    '<?= htmlspecialchars($song['file_path']) ?>', 
                                                                    '<?= htmlspecialchars($song['title']) ?>', 
                                                                    '<?= htmlspecialchars($song['artist']) ?>', 
                                                                    '<?= htmlspecialchars($song['cover_image']) ?>',
                                                                    <?= $song['id'] ?>
                                                                )">
                                                            <i class="fas fa-play text-lg"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                                <td class="py-[10px]">
                                                    <div class="flex items-center cursor-pointer group/title" 
                                                         onclick="playSongAndUpdatePlays(
                                                            '<?= htmlspecialchars($song['file_path']) ?>', 
                                                            '<?= htmlspecialchars($song['title']) ?>', 
                                                            '<?= htmlspecialchars($song['artist']) ?>', 
                                                            '<?= htmlspecialchars($song['cover_image']) ?>',
                                                            <?= $song['id'] ?>
                                                         )">
                                                        <div class="relative w-10 h-10 mr-3 flex-shrink-0">
                                                            <img src="<?= htmlspecialchars($song['cover_image']) ?>" 
                                                                 alt="<?= htmlspecialchars($song['title']) ?>" 
                                                                 class="w-full h-full rounded object-cover">
                                                        </div>
                                                        <div class="flex flex-col">
                                                            <div class="text-white text-sm font-medium group-hover/title:text-[#1DB954]">
                                                                <?= htmlspecialchars($song['title']) ?>
                                                            </div>
                                                            <div class="text-gray-400 text-xs hover:underline">
                                                                <?= htmlspecialchars($song['artist']) ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="py-[10px] text-sm"><?= htmlspecialchars($song['album']) ?></td>
                                                <td class="py-[10px] text-right text-sm text-gray-400">
                                                    <?= formatDuration($song['duration']) ?>
                                                </td>
                                                <td class="py-[10px] text-center">
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_like">
                                                        <input type="hidden" name="song_id" value="<?= $song['id'] ?>">
                                                        <button type="submit" class="like-button <?= $song['is_liked'] ? 'text-red-500' : 'text-gray-400 hover:text-white' ?>">
                                                            <i class="<?= $song['is_liked'] ? 'fas' : 'far' ?> fa-heart"></i>
                                                        </button>
                                                    </form>
                                                    <button onclick="<?= empty($_SESSION['user_id']) 
                                                        ? 'window.location.href=\'?page=login\'' 
                                                        : 'openAddToPlaylistModal(' . $song['id'] . ')' ?>" 
                                                        class="text-gray-400 hover:text-white ml-2">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <!-- Hiển thị phân trang chỉ khi có nhiều hơn 1 trang -->
                                <?php if ($totalPages > 1): ?>
                                <div class="mt-6 flex justify-center">
                                    <div class="flex items-center space-x-2">
                                        <?php if ($currentPage > 1): ?>
                                            <a href="?page=home&p=<?= $currentPage - 1 ?>" 
                                               class="px-3 py-1 rounded text-gray-400 hover:text-white transition-colors">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        <?php endif; ?>

                                        <span class="text-gray-400">
                                            Trang <?= $currentPage ?> / <?= $totalPages ?>
                                        </span>

                                        <?php if ($currentPage < $totalPages): ?>
                                            <a href="?page=home&p=<?= $currentPage + 1 ?>" 
                                               class="px-3 py-1 rounded text-gray-400 hover:text-white transition-colors">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                    <?php elseif($page == 'playlist'): ?>
    <?php 
    // Kiểm tra xem có phải là playlist của người dùng không
    $playlistId = isset($_GET['id']) ? $_GET['id'] : null;
    if ($playlistId === null) {
        // Nếu không có ID, chuyển về trang chủ
        header("Location: ?page=home");
        exit();
    }

    $userPlaylist = false;

    // Fetch playlist từ cơ sở dữ liệu
    $playlist = $playlistModel->getPlaylistById($playlistId);
    
    // Kiểm tra xem playlist có dữ liệu không
    if (!$playlist) {
        echo "<p>Playlist không tồn tại</p>";
        exit();
    } else {
        $userPlaylist = true;
    }

    // Fetch danh sách bài hát của playlist từ cơ sở dữ liệu
    $songs = $playlistModel->getSongsByPlaylistId($playlistId);
    
    // Kiểm tra xem bài hát có dữ liệu không
    if (!$songs) {
        echo "<p>Không có bài hát trong playlist này</p>";
        exit();
    }
    ?>

    <!-- Hiển thị thông tin playlist -->
    <div class="flex flex-col md:flex-row items-center md:items-end gap-6 mb-6">
        <div>
            <div class="text-sm uppercase font-bold">Playlist</div>
            <h1 id="playlist-title" class="text-3xl md:text-5xl font-bold mt-2 mb-4">
                <?= safeDisplay($playlist['name']) ?>
            </h1>
            <div id="playlist-description" class="text-gray-400 mb-2">
                <?= isset($playlist['description']) ? safeDisplay($playlist['description']) : '' ?>
            </div>
            <div class="text-sm text-gray-300">
                <span class="font-semibold"><?= $userPlaylist ? 'Playlist của bạn' : 'Spotify' ?></span> • 
                <span id="playlist-count"><?= count($songs) ?></span> bài hát
            </div>
        </div>
    </div>

    <div class="bg-[#121212]/30 py-4">
        <div class="flex items-center gap-8 mb-6">
            <button class="w-14 h-14 rounded-full bg-spotify-green text-black flex items-center justify-center shadow-lg" onclick="playPlaylist()">
                <i class="fas fa-play text-2xl"></i>
            </button>
            <button class="w-14 h-14 rounded-full bg-white/10 text-white flex items-center justify-center hover:bg-white/20" onclick="shufflePlaylist()">
                <i class="fas fa-random text-2xl"></i>
            </button>
            <button class="text-3xl text-gray-400 hover:text-white">
                <i class="far fa-heart"></i>
            </button>
            <?php if ($userPlaylist): ?>
            <button onclick="deletePlaylist('<?= $playlistId ?>')" class="text-2xl text-gray-400 hover:text-white">
                <i class="fas fa-trash-alt"></i>
            </button>
            <?php endif; ?>
            <button class="text-2xl text-gray-400 hover:text-white">
                <i class="fas fa-ellipsis-h"></i>
            </button>
        </div>
        
        <div class="overflow-x-auto md:overflow-visible">
            <table class="w-full text-left text-sm text-gray-400">
                <thead class="border-b border-gray-700">
                    <tr>
                        <th class="pb-2 w-12">#</th>
                        <th class="pb-2">Title</th>
                        <th class="pb-2 hidden md:table-cell">Album</th>
                        <th class="pb-2 hidden md:table-cell">Date added</th>
                        <th class="pb-2 text-right pr-4">
                            <i class="fas fa-trash text-gray-500"></i>
                        </th>
                    </tr>
                </thead>
                <tbody id="user-playlist-songs">
                    <?php foreach($songs as $index => $song): ?>
                     <tr class="hover:bg-white/10 group cursor-pointer" 
                        data-song-id="<?= $song['id'] ?>"
                        onclick="playSongAndUpdatePlays(
                            '<?= htmlspecialchars($song['file_path']) ?>', 
                            '<?= htmlspecialchars($song['title']) ?>', 
                            '<?= htmlspecialchars($song['artist']) ?>', 
                            '<?= !empty($song['cover_image']) ? '/uploads/songs/' . basename($song['cover_image']) : '/uploads/songs/placeholder.jpg' ?>',
                            <?= $song['id'] ?>
                        )">
                        <td class="py-3 px-2">
                            <span class="group-hover:hidden"><?= $index + 1 ?></span>
                            <span class="hidden group-hover:inline">
                                <i class="fas fa-play text-white"></i>
                            </span>
                        </td>
                        <td>
                            <div class="flex items-center">
                                <img src="<?= !empty($song['cover_image']) ? '/uploads/songs/' . basename($song['cover_image']) : '/uploads/songs/placeholder.jpg' ?>" 
                                     alt="<?= htmlspecialchars($song['title']) ?>" 
                                     class="w-10 h-10 mr-3 object-cover"
                                     onerror="this.src='/uploads/songs/placeholder.jpg'">
                                <div>
                                    <div class="text-white font-medium"><?= safeDisplay($song['title']) ?></div>
                                    <div><?= safeDisplay($song['artist']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="hidden md:table-cell"><?= safeDisplay($song['album']) ?></td>
                        <td class="hidden md:table-cell">
                            <?php
                            $addedDate = new DateTime($song['added_at']);
                            $now = new DateTime();
                            $diff = $now->diff($addedDate);
                            
                            if ($diff->y > 0) {
                                echo $diff->y . ' năm trước';
                            } elseif ($diff->m > 0) {
                                echo $diff->m . ' tháng trước';
                            } elseif ($diff->d > 0) {
                                echo $diff->d . ' ngày trước';
                            } elseif ($diff->h > 0) {
                                echo $diff->h . ' giờ trước';
                            } elseif ($diff->i > 0) {
                                echo $diff->i . ' phút trước';
                            } else {
                                echo 'Vừa xong';
                            }
                            ?>
                        </td>
                        <td class="text-right pr-4">
                            <button onclick="event.stopPropagation(); removeSongFromPlaylist('<?= $playlistId ?>', '<?= $song['id'] ?>')" 
                                    class="text-gray-400 hover:text-white">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>



<?php elseif($page == 'search'): ?>
    <div class="mb-6">
        <div class="relative">
            <label for="search-input" class="sr-only">Search</label>
            <input id="search-input" type="text" placeholder="What do you want to listen to?" class="w-full bg-white text-black py-3 px-12 rounded-full">
            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-black"></i>
        </div>
    </div>
    
    <h2 class="text-2xl font-bold mb-4">Browse all</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
        <?php 
        $categories = [
            ['name' => 'Podcasts', 'color' => 'from-blue-500 to-blue-800'],
            ['name' => 'Live Events', 'color' => 'from-purple-500 to-purple-800'],
            ['name' => 'Made For You', 'color' => 'from-green-500 to-green-800'],
            ['name' => 'New Releases', 'color' => 'from-pink-500 to-pink-800'],
            ['name' => 'Pop', 'color' => 'from-red-500 to-red-800'],
            ['name' => 'Hip-Hop', 'color' => 'from-yellow-500 to-yellow-800'],
            ['name' => 'Rock', 'color' => 'from-indigo-500 to-indigo-800'],
            ['name' => 'Latin', 'color' => 'from-orange-500 to-orange-800'],
            ['name' => 'Workout', 'color' => 'from-teal-500 to-teal-800'],
            ['name' => 'Electronic', 'color' => 'from-cyan-500 to-cyan-800'],
        ];
        
        foreach($categories as $category):
        ?>
        <div class="bg-gradient-to-br <?= $category['color'] ?> rounded-lg overflow-hidden h-48 relative">
            <div class="p-4 font-bold text-xl"><?= safeDisplay($category['name']) ?></div>
            <div class="absolute -bottom-2 -right-2 w-24 h-24 rotate-25 shadow-xl">
                <img src="/placeholder.svg?height=100&width=100" alt="Ảnh bìa thể loại" class="w-full h-full object-cover">
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php elseif($page == 'library'): ?>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <button class="bg-[#333333] hover:bg-[#444444] px-4 py-2 rounded-full">
                Playlists
            </button>
            <button class="bg-[#333333] hover:bg-[#444444] px-4 py-2 rounded-full">
                Artists
            </button>
            <button class="bg-[#333333] hover:bg-[#444444] px-4 py-2 rounded-full">
                Albums
            </button>
        </div>
        <button class="text-xl">
            <i class="fas fa-search"></i>
        </button>
    </div>
    
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <button class="text-xl">
                <i class="fas fa-sort"></i>
            </button>
            <span>Recents</span>
        </div>
        <button class="text-xl">
            <i class="fas fa-list"></i>
        </button>
    </div>
    
    <div class="grid gap-4">
        <!-- Đoạn mã hiển thị playlist đã bị xóa -->
    </div>
<?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Audio player layer - Kiểu Spotify -->
        <div id="audioPlayerLayer" class="fixed bottom-0 left-0 right-0 bg-[#181818] border-t border-[#282828] p-3 z-40">
            <div class="flex items-center justify-between max-w-screen-2xl mx-auto">
                <!-- Thông tin bài hát -->
                <div class="flex items-center w-1/4">
                    <img id="currentSongImage" src="/uploads/artists/placeholder.jpg" alt="Cover" 
                        class="w-12 h-12 object-cover rounded shadow-lg mr-3">
                    <div>
                        <h4 id="currentSongTitle" class="text-white font-medium text-sm truncate">Tên bài hát</h4>
                        <p id="currentSongArtist" class="text-gray-400 text-xs truncate">Tên nghệ sĩ</p>
                    </div>
                </div>

            <!-- Player Controls -->
            <div class="flex flex-col items-center w-2/4">
                <!-- Control buttons -->
                <div class="flex items-center justify-center mb-1">
                    <button onclick="prevSong()" class="text-gray-400 hover:text-white mx-4">
                        <i class="fas fa-step-backward"></i>
                    </button>
                    <button id="playPauseBtn" onclick="togglePlay()" 
                            class="bg-white text-black rounded-full w-8 h-8 flex items-center justify-center hover:scale-105 transition-transform">
                        <i class="fas fa-play" id="playPauseIcon"></i>
                    </button>
                    <button onclick="nextSong()" class="text-gray-400 hover:text-white mx-4">
                        <i class="fas fa-step-forward"></i>
                    </button>
                </div>
                
                <!-- Progress bar -->
                <div class="flex items-center w-full text-xs">
                    <span id="currentTime" class="text-gray-400 w-10 text-right pr-2">0:00</span>
                    <div class="progress-container flex-1 relative h-1 bg-gray-700 rounded-full group">
                    <div id="progress" class="absolute top-0 left-0 h-full bg-gray-400 group-hover:bg-[#1DB954] rounded-full"></div>
                        <input type="range" id="progressBar" class="absolute top-0 left-0 w-full h-full opacity-0 cursor-pointer" min="0" max="100" value="0">
                    </div>
                    <span id="duration" class="text-gray-400 w-10 text-left pl-2">0:00</span>
                </div>
            </div>

            <!-- Right controls -->
            <div class="w-1/4 flex items-center justify-end">
                <!-- Karaoke button -->
                <button id="karaokeBtn" onclick="toggleLyrics()" class="text-gray-400 hover:text-white mx-2 relative group">
                    <i class="fas fa-microphone-alt"></i>
                    <span class="hidden group-hover:block absolute -top-10 left-1/2 transform -translate-x-1/2 bg-[#282828] text-white text-xs py-1 px-2 rounded whitespace-nowrap">
                        Xem lời bài hát
                    </span>
                </button>
                
                <!-- Volume control -->
                <div class="flex items-center group">
                    <button id="volumeBtn" class="text-gray-400 hover:text-white mx-2">
                        <i class="fas fa-volume-up" id="volumeIcon"></i>
                    </button>
                    <div class="w-20 hidden group-hover:block">
                        <input type="range" id="volumeSlider" class="w-full h-1 bg-gray-700 rounded-full appearance-none cursor-pointer accent-white" min="0" max="100" value="100">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lyrics container - hiển thị dưới player layer -->
    <div id="lyricsContainer" class="fixed bottom-20 left-0 right-0 bg-[#121212] border-t border-[#282828] p-4 z-30 hidden max-h-[300px] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-white font-bold">Lời bài hát</h3>
            <button onclick="toggleLyrics()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="lyricsText" class="text-white text-lg leading-relaxed">
            <!-- Lyrics sẽ được thêm vào đây -->
            <div class="flex justify-center items-center py-6">
                <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-white"></div>
            </div>
        </div>
        <div id="debug-info" class="text-gray-500 text-xs mt-2 border-t border-gray-700 pt-2 hidden">
            <!-- Debug info sẽ được thêm vào đây -->
        </div>
    </div>

    <!-- Lyrics overlay - hiển thị giữa màn hình -->
    <div id="lyricsOverlay" class="fixed inset-0 bg-black bg-opacity-80 hidden z-30 overflow-hidden">
        <div class="h-full flex flex-col">
            <!-- Header của lyrics -->
            <div class="bg-gradient-to-b from-black to-transparent p-6 flex justify-between items-center">
                <div class="flex items-center">
                    <img id="lyricsImage" src="/uploads/artists/placeholder.jpg" alt="Cover" class="w-16 h-16 object-cover rounded shadow-lg mr-4">
                    <div>
                        <h3 id="lyricsTitle" class="text-white font-bold text-xl">Tên bài hát</h3>
                        <p id="lyricsArtist" class="text-gray-300">Tên nghệ sĩ</p>
                    </div>
                </div>
                <button onclick="toggleLyrics()" class="text-white hover:text-gray-200 focus:outline-none">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Lyrics content - hiển thị giữa màn hình -->
            <div class="flex-1 flex items-center justify-center p-6 overflow-hidden">
                <div id="lyricsContent" class="text-white text-center text-2xl leading-relaxed overflow-y-auto max-h-[60vh] lyrics-container">
                    <!-- Lyrics sẽ được thêm vào đây -->
                    <div class="flex justify-center items-center h-full">
                        <div class="animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-white"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Karaoke Script -->
    <script>
    // Các biến toàn cục
    var currentSong;
    var isPlaying;
    let currentLyricsLines = []; // Mảng chứa các dòng lyrics
    let lyricsTimer = null; // Timer cho việc highlight lyrics

    // Phát nhạc
    async function playSong(url, title, artist, image, songId) {
    // Nếu đang phát bài hát khác, dừng lại
    if (this.audio && !this.audio.paused) {
        this.audio.pause();
        this.audio.currentTime = 0; // reset thời gian phát
    }

    // Lưu thông tin bài hát hiện tại
    this.currentSong = { url, title, artist, image, id: songId };

    // Cập nhật source bài hát (luôn gán lại)
    this.audio.src = url;

    // Cập nhật UI trước khi phát
    this.updateUI();

    try {
        await this.audio.play();
        this.isPlaying = true;
        this.updateUI();
        if (songId) this.updatePlayCount(songId);
    } catch (error) {
        console.error('Lỗi phát nhạc:', error);
        alert('Không thể phát bài hát này');
    }
}


    // Toggle Play/Pause

    

    // Cập nhật icon play/pause
    function updatePlayPauseIcon() {
        const icon = document.getElementById('playPauseIcon');
        if (!icon) return;
        
        if (isPlaying) {
            icon.className = 'fas fa-pause';
        } else {
            icon.className = 'fas fa-play';
        }
    }

    // Xử lý tiến trình phát nhạc
    audioPlayer.addEventListener('timeupdate', () => {
        if (!audioPlayer.duration) return;
        
        const progress = (audioPlayer.currentTime / audioPlayer.duration) * 100;
        const progressBar = document.getElementById('progress');
        const progressInput = document.getElementById('progressBar');
        
        if (progressBar) progressBar.style.width = `${progress}%`;
        if (progressInput) progressInput.value = progress;
        
        // Cập nhật thời gian
        const currentTimeEl = document.getElementById('currentTime');
        const durationEl = document.getElementById('duration');
        
        if (currentTimeEl) currentTimeEl.textContent = formatTime(audioPlayer.currentTime);
        if (durationEl) durationEl.textContent = formatTime(audioPlayer.duration);
        
        // Cập nhật hiển thị lyrics theo thời gian
        if (!document.getElementById('lyricsContainer').classList.contains('hidden') || 
            !document.getElementById('lyricsOverlay').classList.contains('hidden')) {
            updateActiveLyrics(audioPlayer.currentTime);
        }
    });

    // Tùy chỉnh thanh tiến trình
    progressBar.addEventListener('input', (e) => {
        if (!audioPlayer.duration) return;

        const value = parseFloat(e.target.value);
        const seekTime = (value / 100) * audioPlayer.duration;
        audioPlayer.currentTime = seekTime;
        progress.style.width = `${value}%`;
    });

    // Xử lý volume
    document.getElementById('volumeSlider').addEventListener('input', (e) => {
        const value = e.target.value;
        audioPlayer.volume = value / 100;
        
        // Cập nhật icon volume
        const volumeIcon = document.getElementById('volumeIcon');
        if (value > 70) {
            volumeIcon.className = 'fas fa-volume-up';
        } else if (value > 30) {
            volumeIcon.className = 'fas fa-volume-down';
        } else if (value > 0) {
            volumeIcon.className = 'fas fa-volume-off';
        } else {
            volumeIcon.className = 'fas fa-volume-mute';
        }
    });

    // Format thời gian từ giây sang mm:ss
    function formatTime(seconds) {
        if (isNaN(seconds)) return "0:00";
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.floor(seconds % 60);
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    }


    // Bật/tắt lyrics
    function toggleLyrics() {
        // Lấy thông tin bài hát hiện tại
        const title = document.getElementById('currentSongTitle').textContent;
        const artist = document.getElementById('currentSongArtist').textContent;
        
        // Kiểm tra nếu không có bài hát đang phát
        if (!title || title === 'Tên bài hát') {
            alert('Vui lòng chọn một bài hát để hiển thị lời');
            return;
        }
        
        const lyricsContainer = document.getElementById('lyricsContainer');
        const debugInfo = document.getElementById('debug-info');
        
        // Hiển thị debug info
        debugInfo.classList.remove('hidden');
        debugInfo.innerHTML = `Đang tải lyrics cho bài: "${title}" - ${artist}`;
        
        if (lyricsContainer.classList.contains('hidden')) {
            // Hiển thị lyrics
            lyricsContainer.classList.remove('hidden');
            
            // Tải lyrics
            loadLyrics(title, 'lyricsText');
        } else {
            // Ẩn lyrics
            lyricsContainer.classList.add('hidden');
        }
    }

    // Tải lyrics
    function loadLyrics(songTitle, targetElementId = 'lyricsContent') {
        const lyricsElement = document.getElementById(targetElementId);
        lyricsElement.innerHTML = '<div class="flex justify-center items-center h-40"><div class="animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-white"></div></div>';
        
        // Tạo tên file dựa vào tên bài hát
        const songTitleFormatted = songTitle.toLowerCase().replace(/\s+/g, '_').replace(/[^\w]/g, '_');
        console.log('Tên file lyrics được định dạng:', songTitleFormatted);
        
        // Danh sách các file lyrics có thể - sửa lại đường dẫn để không có undefined
        const possibleLyricsFiles = [
            `uploads/lyrics/${songTitleFormatted}.txt`,  // Đường dẫn tương đối
            `uploads/lyrics/noi_nay_co_anh.txt`          // File mẫu đã biết tồn tại
        ];
        
        console.log('Danh sách file lyrics có thể:', possibleLyricsFiles);
        
        // Tìm và tải lyrics
        tryLoadLyrics(possibleLyricsFiles, 0, lyricsElement);
    }

    // Thử tải lyrics từ các file có thể
    function tryLoadLyrics(files, index, contentElement) {
        const debugInfo = document.getElementById('debug-info');
        
        if (index >= files.length) {
            // Đã thử tất cả các file nhưng không tìm thấy
            contentElement.innerHTML = `
                <div class="text-center p-8">
                    <i class="fas fa-exclamation-circle text-3xl text-yellow-500 mb-4"></i>
                    <p class="text-xl text-yellow-400">Bài hát này chưa có lời</p>
                    <p class="text-gray-400 mt-4">Hãy thử bài hát khác hoặc liên hệ với quản trị viên để thêm lời bài hát</p>
                </div>
            `;
            debugInfo.innerHTML += `<br>Đã thử tất cả các file nhưng không tìm thấy lyrics.`;
            return;
        }
        
        console.log('Đang thử tải lyrics từ:', files[index]);
        debugInfo.innerHTML += `<br>Đang thử tải từ: ${files[index]}`;
        
        fetch(files[index])
                .then(response => {
                    if (!response.ok) {
                    console.log('Không tìm thấy file:', files[index], response.status);
                    debugInfo.innerHTML += `<br>Không tìm thấy file (${response.status})`;
                    throw new Error('Không tìm thấy file');
                    }
                debugInfo.innerHTML += `<br>Tìm thấy file, đang đọc nội dung...`;
                    return response.text();
                })
                .then(lyrics => {
                console.log('Đã tải được lyrics, độ dài:', lyrics.length);
                debugInfo.innerHTML += `<br>Đã tải lyrics, độ dài: ${lyrics.length} ký tự`;
                
                    if (lyrics && lyrics.trim()) {
                        // Tạo các dòng lyrics
                        const lines = lyrics.split('\n').filter(line => line.trim());
                        currentLyricsLines = lines;
                    
                    debugInfo.innerHTML += `<br>Số dòng lyrics: ${lines.length}`;
                        
                        // Tạo HTML cho lyrics
                        let html = '';
                        lines.forEach((line, i) => {
                            html += `<div class="lyrics-line mb-4" data-line="${i}">${line}</div>`;
                        });
                        
                        // Hiển thị lyrics
                    contentElement.innerHTML = html;
                        
                        // Thiết lập timeline cho lyrics
                        setupLyricsTimeline();
                        
                        // Highlight dòng lyrics hiện tại
                        if (audioPlayer.currentTime > 0) {
                            updateActiveLyrics(audioPlayer.currentTime);
                        }
                } else {
                    console.log('File rỗng:', files[index]);
                    debugInfo.innerHTML += `<br>File rỗng hoặc không đọc được nội dung`;
                    throw new Error('File rỗng');
                }
            })
            .catch(error => {
                // Ghi log lỗi
                console.error('Lỗi khi tải lyrics:', error.message, 'từ file:', files[index]);
                debugInfo.innerHTML += `<br>Lỗi: ${error.message}`;
                
                // Thử file tiếp theo
                tryLoadLyrics(files, index + 1, contentElement);
            });
    }

    // Thiết lập timeline cho lyrics
    function setupLyricsTimeline() {
        if (!currentLyricsLines.length || !audioPlayer.duration) return;
        
        // Tính thời gian cho mỗi dòng lyrics
        const lineCount = currentLyricsLines.length;
        const timePerLine = audioPlayer.duration / lineCount;
        
        // Gán thời gian cho mỗi dòng
        document.querySelectorAll('.lyrics-line').forEach((line, index) => {
            line.setAttribute('data-start-time', index * timePerLine);
            line.setAttribute('data-end-time', (index + 1) * timePerLine);
        });
    }

    // Cập nhật hiển thị lyrics theo thời gian
    function updateActiveLyrics(currentTime) {
        if (!currentLyricsLines.length) return;
        
        let activeLineFound = false;
        
        // Cập nhật trên cả hai container lyrics
        updateLyricsInContainer('lyricsContent', currentTime);
        updateLyricsInContainer('lyricsText', currentTime);
        
        return activeLineFound;
    }

    // Cập nhật lyrics trong một container cụ thể
    function updateLyricsInContainer(containerId, currentTime) {
        const container = document.getElementById(containerId);
        if (!container) return false;
        
        let activeLineFound = false;
        
        // Duyệt qua từng dòng lyrics
        container.querySelectorAll('.lyrics-line').forEach(line => {
            const startTime = parseFloat(line.getAttribute('data-start-time'));
            const endTime = parseFloat(line.getAttribute('data-end-time'));
            
            if (currentTime >= startTime && currentTime < endTime) {
                // Highlight dòng hiện tại
                line.classList.add('text-[#1DB954]', 'font-bold', 'scale-110', 'transform', 'active');
                line.classList.remove('text-white', 'text-gray-400');
                
                // Cuộn đến dòng hiện tại
                scrollToActiveLine(line, container);
                
                activeLineFound = true;
            } else if (currentTime < startTime) {
                // Dòng chưa đến
                line.classList.add('text-gray-400');
                line.classList.remove('text-[#1DB954]', 'text-white', 'font-bold', 'scale-110', 'transform', 'active');
            } else {
                // Dòng đã qua
                line.classList.add('text-white');
                line.classList.remove('text-[#1DB954]', 'text-gray-400', 'font-bold', 'scale-110', 'transform', 'active');
            }
        });
        
        return activeLineFound;
    }

    // Cuộn đến dòng lyrics hiện tại
    function scrollToActiveLine(lineElement, container) {
        if (!container) return;
        
        // Vị trí của dòng trong container
        const containerHeight = container.clientHeight;
        const lineTop = lineElement.offsetTop;
        const lineHeight = lineElement.offsetHeight;
        
        // Tính toán vị trí scroll để dòng hiện tại nằm giữa container
        const targetScrollTop = lineTop - (containerHeight / 2) + (lineHeight / 2);
        
        // Cuộn mượt đến vị trí
        container.scrollTo({
            top: targetScrollTop,
            behavior: 'smooth'
        });
    }

    // Style for lyrics
    document.head.insertAdjacentHTML('beforeend', `
    <style>
        .lyrics-container::-webkit-scrollbar {
            width: 4px;
        }
        .lyrics-container::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        .lyrics-container::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }
        .lyrics-line {
            transition: all 0.3s ease;
        }
        #lyricsContainer {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.3) rgba(255, 255, 255, 0.1);
            box-shadow: 0 -4px 6px rgba(0, 0, 0, 0.2);
        }
        #lyricsContainer::-webkit-scrollbar {
            width: 4px;
        }
        #lyricsContainer::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        #lyricsContainer::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }
        #lyricsText .lyrics-line.active {
            color: #1DB954;
            font-weight: bold;
            transform: scale(1.05);
        }
    </style>
    `);
    </script>

    <!-- Thêm audio element -->
    <audio id="audioPlayer"></audio>

    <script>
    if (typeof currentSong === 'undefined') {
        let currentSong = null;
    }
    if (typeof audioPlayer === 'undefined') {
        let audioPlayer = document.getElementById('audioPlayer');
    }
    if (typeof isPlaying === 'undefined') {
        let isPlaying = false;
    }
    if (typeof currentLyricsLines === 'undefined') {
        let currentLyricsLines = []; // Lưu các dòng lyrics
    }

    
    

    function updatePlayPauseIcon() {
        const icon = document.getElementById('playPauseIcon');
        icon.className = isPlaying ? 'fas fa-pause' : 'fas fa-play';
    }

    // Xử lý progress bar
   

    // Click vào progress bar để tua
    document.getElementById('progressBar').addEventListener('click', (e) => {
        if (!audioPlayer.duration) return;
        
        const progressBar = e.currentTarget;
        const clickPosition = (e.pageX - progressBar.offsetLeft) / progressBar.offsetWidth;
        const seekTime = clickPosition * audioPlayer.duration;
        audioPlayer.currentTime = seekTime;
    });

    // Xử lý volume
    document.getElementById('volumeSlider').addEventListener('input', (e) => {
        audioPlayer.volume = e.target.value / 100;
    });


    // Xử lý khi bài hát kết thúc
    audioPlayer.addEventListener('ended', () => {
        isPlaying = false;
        updatePlayPauseIcon();
    });

    // Hàm hiển thị lyrics trong player
    function showLyrics() {
        // Lấy tên bài hát hiện tại
        const title = document.getElementById('currentSongTitle').textContent;
        if (!title || title === 'Tên bài hát') {
            alert('Không có bài hát đang phát');
                        return;
        }
        
        // Hiển thị container lyrics
        const lyricsContainer = document.getElementById('lyricsContainer');
        lyricsContainer.classList.remove('hidden');
        
        // Lấy element hiển thị lyrics
        const lyricsContent = document.getElementById('lyricsContent');
        lyricsContent.innerHTML = '<div class="flex justify-center items-center py-4"><div class="animate-spin rounded-full h-5 w-5 border-t-2 border-b-2 border-[#1DB954]"></div></div>';
        
        // Tạo tên file dựa vào tên bài hát
        const songTitleFormatted = title.toLowerCase().replace(/\s+/g, '_').replace(/[^\w]/g, '_');
        const possibleLyricsFiles = [
            `uploads/lyrics/${songTitleFormatted}.txt`,
            `uploads/lyrics/ch_ng_ta_c_a_hi_n_t_i.txt`, // Chúng ta của hiện tại
            `uploads/lyrics/chay_ngay_di.txt`,
            `uploads/lyrics/noi_nay_co_anh.txt`
        ];
        
        // Tải lyrics
        loadLyrics(possibleLyricsFiles, 0, lyricsContent);
    }

    // Hàm tải lyrics
    function loadLyrics(files, index, contentElement) {
        if (index >= files.length) {
            // Đã thử tất cả các file nhưng không tìm thấy
            contentElement.innerHTML = '<p class="text-center text-yellow-500 my-4">Bài hát này chưa có lyrics</p>';
            currentLyricsLines = [];
            return;
        }
        
        fetch(files[index])
            .then(response => {
                if (!response.ok) {
                    throw new Error('Không tìm thấy file lyrics');
                }
                return response.text();
            })
            .then(text => {
                if (text.trim()) {
                    // Hiển thị lyrics
                    const lines = text.split('\n').filter(line => line.trim());
                    currentLyricsLines = lines;
                    
                    // Tạo HTML cho lyrics
                    let html = '';
                    lines.forEach((line, i) => {
                        html += `<div class="lyrics-line" data-line="${i}">${line}</div>`;
                    });
                    contentElement.innerHTML = html;
                    
                    // Chuẩn bị hiệu ứng karaoke
                    setupKaraokeEffect();
                    } else {
                    throw new Error('File lyrics rỗng');
                    }
                })
                .catch(error => {
                // Thử file tiếp theo
                loadLyrics(files, index + 1, contentElement);
            });
    }

    // Ẩn lyrics
    function hideLyrics() {
        document.getElementById('lyricsContainer').classList.add('hidden');
        currentLyricsLines = [];
    }

    // Thiết lập hiệu ứng karaoke
    function setupKaraokeEffect() {
        // Đặt thời gian cho mỗi dòng lyrics
        const duration = audioPlayer.duration;
        if (!duration || !currentLyricsLines.length) return;
        
        const linesCount = currentLyricsLines.length;
        const timePerLine = duration / linesCount;
        
        // Lưu thời gian cho mỗi dòng
        currentLyricsLines.forEach((_, index) => {
            const lineElement = document.querySelector(`.lyrics-line[data-line="${index}"]`);
            if (lineElement) {
                lineElement.setAttribute('data-start-time', index * timePerLine);
                lineElement.setAttribute('data-end-time', (index + 1) * timePerLine);
            }
        });
    }

    // Cập nhật hiển thị lyrics theo thời gian
    function updateLyricsDisplay(currentTime) {
        if (!currentLyricsLines.length) return;
        
        // Duyệt qua tất cả các dòng lyrics
        document.querySelectorAll('.lyrics-line').forEach(line => {
            const startTime = parseFloat(line.getAttribute('data-start-time'));
            const endTime = parseFloat(line.getAttribute('data-end-time'));
            
            if (currentTime >= startTime && currentTime < endTime) {
                // Dòng hiện tại - highlight
                line.classList.add('text-[#1DB954]', 'font-semibold');
                
                // Cuộn đến dòng hiện tại
                scrollToActiveLine(line);
        } else {
                // Dòng khác - bỏ highlight
                line.classList.remove('text-[#1DB954]', 'font-semibold');
            }
        });
    }

    // Cuộn đến dòng lyrics đang active
    function scrollToActiveLine(lineElement) {
        const container = document.getElementById('lyricsContent');
        if (!container) return;
        
        const containerRect = container.getBoundingClientRect();
        const lineRect = lineElement.getBoundingClientRect();
        
        // Tính toán vị trí scroll
        const scrollTarget = lineElement.offsetTop - container.offsetTop - (containerRect.height / 2);
        
        // Cuộn mượt đến vị trí
        container.scrollTo({
            top: scrollTarget,
            behavior: 'smooth'
        });
    }
    </script>

    <script>
    // Cập nhật hiển thị thời gian phát và thanh tiến trình
    function updatePlayerDisplay() {
        const audio = document.querySelector('audio');
        if (!audio || audio.paused) return;
        
        const currentTime = audio.currentTime;
        const duration = audio.duration || 0;
        const progress = (currentTime / duration) * 100;
        
        // Cập nhật thanh tiến trình
        document.getElementById('progress-bar').style.width = `${progress}%`;
        
        // Cập nhật hiển thị thời gian
        document.getElementById('current-time').textContent = formatTime(currentTime);
        document.getElementById('duration').textContent = formatTime(duration);
    }

    // Cập nhật hiển thị theo chu kỳ
    setInterval(updatePlayerDisplay, 500);

    
    </script>

    <!-- Thêm loading indicator -->
    <div id="loading" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="animate-spin rounded-full h-32 w-32 border-t-2 border-b-2 border-spotify-green"></div>
    </div>

    <script nonce="<?= $nonce ?>">
    // Debounce function for performance
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }   

    document.addEventListener('DOMContentLoaded', function() {
        // Cache DOM elements
        const playButtons = document.querySelectorAll('.fa-play');
        const mainPlayButton = document.querySelector('.w-8.h-8.rounded-full.bg-white .fa-play');
        const progressBar = document.querySelector('.progress-bar');
        const currentTimeDisplay = document.querySelector('.text-xs.text-gray-400');
        
        // Optimized progress update
        const updateProgress = debounce(() => {
            if (mainPlayButton.classList.contains('fa-pause')) {
                progress = (progress + 1) % 101;
                progressBar.value = progress;
                
                const minutes = Math.floor((progress * 3.2) / 100);
                const seconds = Math.floor(((progress * 3.2) / 100 - minutes) * 60);
                currentTimeDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            }
        }, 1000);
    });

    // Show loading when changing pages
    document.querySelectorAll('a[href*="?page="]').forEach(link => {
        link.addEventListener('click', () => {
            document.getElementById('loading').classList.remove('hidden');
        });
    });
    </script>

    <!-- Thêm skip link cho người dùng bàn phím -->
    <a href="#main-content" class="sr-only focus:not-sr-only">Skip to main content</a>

    <!-- Add audio.js -->
    <script src="/js/audio.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const player = new AudioPlayer();
        const loading = document.getElementById('loading');
        let currentPlayingButton = null;
        let isRepeat = false; // Trạng thái lặp lại
        let isRandom = false; // Trạng thái phát ngẫu nhiên
        let currentSongIndex = 0; // Vị trí bài hát hiện tại

        // Lấy danh sách tất cả các bài hát
        const songs = Array.from(document.querySelectorAll('.play-song')).map(button => ({
            element: button,
            url: button.dataset.url,
            title: button.dataset.title,
            artist: button.dataset.artist,
            image: button.dataset.image
        }));

        // Xử lý click vào nút play trong danh sách bài hát 
        document.querySelectorAll('.play-song').forEach(button => {
            button.addEventListener('click', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const songData = {
                    url: this.dataset.url,
                    title: this.dataset.title,
                    artist: this.dataset.artist,
                    image: this.dataset.image
                };

                try {
                    const isCurrentSong = player.currentSong && player.currentSong.url === songData.url;
                    
                    if (isCurrentSong) {
                        if (player.audio.paused) {
                            await player.audio.play(); // Sử dụng trực tiếp audio.play()
                            this.querySelector('i').className = 'fas fa-pause text-white text-sm';
                            document.querySelector('#play-button i').className = 'fas fa-pause text-black';
                        } else {
                            player.audio.pause(); // Sử dụng trực tiếp audio.pause()
                            this.querySelector('i').className = 'fas fa-play text-white text-sm';
                            document.querySelector('#play-button i').className = 'fas fa-play text-black';
                        }
                        currentPlayingButton = this;
                    } else {
                        loading.classList.remove('hidden');
                        // Reset tất cả icon về play
                        document.querySelectorAll('.play-song i').forEach(icon => {
                            icon.className = 'fas fa-play text-white text-sm';
                        });

                        await player.loadAndPlay(songData);
                        
                        // Cập nhật UI
                        this.querySelector('i').className = 'fas fa-pause text-white text-sm';
                        document.querySelector('#play-button i').className = 'fas fa-pause text-black';
                        currentPlayingButton = this;
                        
                        // Cập nhật thông tin bài hát
                        document.querySelector('#player-title').textContent = songData.title || 'Chọn một bài hát';
                        document.querySelector('#player-artist').textContent = songData.artist || 'Nghệ sĩ';
                        if (songData.image) {
                            document.querySelector('#player-image').src = songData.image;
                        }
                    }
                } catch (error) {
                    console.error('Lỗi phát nhạc:', error);
                    alert('Không thể phát bài hát này');
                } finally {
                    loading.classList.add('hidden');
                }
            });
        });

        // Xử lý nút play/pause chính
        document.querySelector('#play-button').addEventListener('click', async () => {
            if (!player.currentSong || !currentPlayingButton) return;

            try {
                if (player.audio.paused) {
                    await player.audio.play(); // Sử dụng trực tiếp audio.play()
                    document.querySelector('#play-button i').className = 'fas fa-pause text-black';
                    currentPlayingButton.querySelector('i').className = 'fas fa-pause text-white text-sm';
                } else {
                    player.audio.pause(); // Sử dụng trực tiếp audio.pause()
                    document.querySelector('#play-button i').className = 'fas fa-play text-black';
                    currentPlayingButton.querySelector('i').className = 'fas fa-play text-white text-sm';
                }
            } catch (error) {
                console.error('Lỗi phát/dừng nhạc:', error);
            }
        });

        // Xử lý khi bài hát kết thúc
        player.audio.addEventListener('ended', () => {
            if (currentPlayingButton) {
                currentPlayingButton.querySelector('i').className = 'fas fa-play text-white text-sm';
            }
            document.querySelector('#play-button i').className = 'fas fa-play text-black';
        });

        // Xử lý nút next
        document.querySelector('#next-button').addEventListener('click', async () => {
            const songButtons = document.querySelectorAll('.play-song');
            if (!songButtons.length) return;

            try {
                // Tìm bài hát đang phát
                const currentButton = document.querySelector('.play-song i.fa-pause')?.closest('.play-song');
                if (!currentButton) {
                    // Nếu chưa có bài nào phát, phát bài đầu tiên
                    songButtons[0].click();
                    return;
                }

                // Tìm bài tiếp theo
                const nextButton = currentButton.closest('tr').nextElementSibling?.querySelector('.play-song');
                if (nextButton) {
                    nextButton.click();
                } else {
                    // Nếu là bài cuối cùng, quay lại bài đầu
                    songButtons[0].click();
                }
            } catch (error) {
                console.error('Lỗi chuyển bài:', error);
            }
        });

        // Xử lý nút previous
        document.querySelector('#prev-button').addEventListener('click', async () => {
            const songButtons = document.querySelectorAll('.play-song');
            if (!songButtons.length) return;

            try {
                // Tìm bài hát đang phát
                const currentButton = document.querySelector('.play-song i.fa-pause')?.closest('.play-song');
                if (!currentButton) {
                    // Nếu chưa có bài nào phát, phát bài cuối cùng
                    songButtons[songButtons.length - 1].click();
                    return;
                }

                // Kiểm tra thời gian phát
                if (player.audio.currentTime > 3) {
                    // Nếu đã phát hơn 3 giây, quay về đầu bài hát
                    player.audio.currentTime = 0;
                    return;
                }

                // Tìm bài trước đó
                const prevButton = currentButton.closest('tr').previousElementSibling?.querySelector('.play-song');
                if (prevButton) {
                    prevButton.click();
                } else {
                    // Nếu là bài đầu tiên, chuyển đến bài cuối
                    songButtons[songButtons.length - 1].click();
                }
            } catch (error) {
                console.error('Lỗi chuyển bài:', error);
            }
        });

        // Xử lý nút repeat
        document.querySelector('#repeat-button').addEventListener('click', () => {
            isRepeat = !isRepeat;
            const repeatButton = document.querySelector('#repeat-button');
            if (isRepeat) {
                repeatButton.classList.add('text-spotify-green');
                player.audio.loop = true;
            } else {
                repeatButton.classList.remove('text-spotify-green');
                player.audio.loop = false;
            }
        });

        // Xử lý nút random
        document.querySelector('#shuffle-button').addEventListener('click', () => {
            isRandom = !isRandom;
            const shuffleButton = document.querySelector('#shuffle-button');
            if (isRandom) {
                shuffleButton.classList.add('text-spotify-green');
            } else {
                shuffleButton.classList.remove('text-spotify-green');
            }
        });

        // Xử lý khi bài hát kết thúc - tự động chuyển bài tiếp theo
        player.audio.addEventListener('ended', () => {
            if (!isRepeat) {
                document.querySelector('#next-button').click();
            }
        });
    });
    </script>

    <script>
    function toggleDropdown() {
        const dropdownMenu = document.getElementById('dropdown-menu');
        const dropdownArrow = document.getElementById('dropdown-arrow');
        
        // Toggle dropdown visibility
        if (dropdownMenu.classList.contains('hidden')) {
            dropdownMenu.classList.remove('hidden');
            dropdownArrow.style.transform = 'rotate(180deg)';
        } else {
            dropdownMenu.classList.add('hidden');
            dropdownArrow.style.transform = 'rotate(0deg)';
        }
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('dropdown-menu');
        const dropdownButton = event.target.closest('button');
        const dropdownArrow = document.getElementById('dropdown-arrow');
        
        if (!dropdownButton && !dropdown.classList.contains('hidden')) {
            dropdown.classList.add('hidden');
            dropdownArrow.style.transform = 'rotate(0deg)';
        }
    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchForm = document.getElementById('searchForm');
        const searchInput = document.getElementById('searchInput');

        if (searchForm && searchInput) {
            // Xử lý submit form
            searchForm.addEventListener('submit', function(e) {
                if (!searchInput.value.trim()) {
                    e.preventDefault();
                }
            });

            // Xử lý live search với debounce
            let timeout = null;
            searchInput.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    if (this.value.trim().length > 0) {
                        searchForm.submit();
                    }
                }, 500);
            });
        }

        // Xử lý nút play
        const playButtons = document.querySelectorAll('.play-song');
        if (playButtons.length > 0) {
            playButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const url = this.dataset.url;
                    const title = this.dataset.title;
                    const artist = this.dataset.artist;
                    const image = this.dataset.image;
                    
                    const playerTitle = document.getElementById('player-title');
                    const playerArtist = document.getElementById('player-artist');
                    const playerImage = document.getElementById('player-image');
                    
                    if (playerTitle) playerTitle.textContent = title;
                    if (playerArtist) playerArtist.textContent = artist;
                    if (playerImage) playerImage.src = image;
                    
                    if (url) {
                        const audio = new Audio(url);
                        audio.play();
                    }
                });
            });
        }
    });
    </script>

    <script>
    let currentSongIndex = 0;
    const playlist = <?= json_encode($songs) ?>;

    // Hàm chuyển bài tiếp theo
        function nextSong() {
        if (!playlist || playlist.length === 0) return;

        currentSongIndex = (currentSongIndex + 1) % playlist.length;
        const song = playlist[currentSongIndex];

        if (song) {
            audioPlayer.playSong(
                song.file_path,
                song.title,
                song.artist,
                song.cover_image,
                song.id
            );
        }
    }
    function prevSong() {
    if (!playlist || playlist.length === 0) return;

    currentSongIndex = (currentSongIndex - 1 + playlist.length) % playlist.length;
    const song = playlist[currentSongIndex];

    if (song) {
        audioPlayer.playSong(
            song.file_path,
            song.title,
            song.artist,
            song.cover_image,
            song.id
        );
    }
}




    // Giữ nguyên các phần khác
  
    // ... (các hàm khác giữ nguyên)
    </script>

    <script>
    let lastVolume = 1; // Lưu trữ mức âm lượng trước khi tắt tiếng

    function updateVolumeSlider(volume) {
        const volumeSlider = document.getElementById('volumeSlider');
        const volumeIcon = document.getElementById('volumeIcon');
        const percentage = volume * 100;
        
        volumeSlider.value = percentage;
        volumeSlider.style.background = `linear-gradient(to right, #1DB954 ${percentage}%, #4d4d4d ${percentage}%)`;

        // Cập nhật icon dựa trên mức âm lượng
        volumeIcon.className = 'fas';
        if (volume === 0) {
            volumeIcon.classList.add('fa-volume-mute');
        } else if (volume < 0.5) {
            volumeIcon.classList.add('fa-volume-down');
        } else {
            volumeIcon.classList.add('fa-volume-up');
        }
    }

    function toggleMute() {
        const audioPlayer = document.getElementById('audioPlayer');
        if (!audioPlayer) return;

        if (audioPlayer.volume > 0) {
            lastVolume = audioPlayer.volume;
            audioPlayer.volume = 0;
        } else {
            audioPlayer.volume = lastVolume;
        }
        updateVolumeSlider(audioPlayer.volume);
    }

    // Thêm event listeners cho điều khiển âm lượng
    document.addEventListener('DOMContentLoaded', function() {
        const volumeSlider = document.getElementById('volumeSlider');
        const volumeButton = document.querySelector('.fa-volume-up').parentElement;
        
        if (volumeSlider) {
            volumeSlider.addEventListener('input', (e) => {
                const volume = e.target.value / 100;
                const audioPlayer = document.getElementById('audioPlayer');
                if (audioPlayer) {
                    audioPlayer.volume = volume;
                    lastVolume = volume;
                }
                updateVolumeSlider(volume);
            });
        }

        if (volumeButton) {
            volumeButton.addEventListener('click', toggleMute);
        }
    });
    </script>

    <style>
    /* Tùy chỉnh thanh volume slider */
    input[type="range"] {
        -webkit-appearance: none;
        appearance: none;
        height: 4px;
        border-radius: 2px;
    }

    input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: white;
        cursor: pointer;
        opacity: 0;
        transition: opacity 0.2s;
    }

    input[type="range"]:hover::-webkit-slider-thumb,
    input[type="range"]:active::-webkit-slider-thumb {
        opacity: 1;
    }

    .group:hover input[type="range"]::-webkit-slider-thumb {
        opacity: 1;
    }
    </style>

    <script>
    // Hàm cập nhật thanh progress
    function updateProgress() {
        if (!currentAudio) return;
        
        const progressBar = document.getElementById('song-progress');
        const currentTimeSpan = document.getElementById('current-time');
        const totalTimeSpan = document.getElementById('total-time');
        
        // Cập nhật thanh progress
        const progress = (currentAudio.currentTime / currentAudio.duration) * 100;
        if (progressBar) {
            progressBar.value = progress;
            progressBar.style.background = `linear-gradient(to right, #1DB954 ${progress}%, #4d4d4d ${progress}%)`;
        }
        
        // Cập nhật thời gian
        if (currentTimeSpan) {
            currentTimeSpan.textContent = formatTime(currentAudio.currentTime);
        }
        if (totalTimeSpan && !isNaN(currentAudio.duration)) {
            totalTimeSpan.textContent = formatTime(currentAudio.duration);
        }
    }
    
    // Thêm event listener cho thanh progress
    document.getElementById('song-progress')?.addEventListener('input', (e) => {
        if (!currentAudio) return;
        
        const time = (e.target.value / 100) * currentAudio.duration;
        currentAudio.currentTime = time;
    });
    </script>

    <!-- Modal hiển thị danh sách bài hát -->
    <div id="songListModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-[#170f23] rounded-lg p-6 max-w-4xl w-full mx-4 max-h-[80vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 id="modalArtistName" class="text-2xl font-bold text-white"></h3>
                <button onclick="closeSongList()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="songList" class="space-y-4">
                <!-- Danh sách bài hát sẽ được thêm vào đây bằng JavaScript -->
            </div>
        </div>
    </div>

    <script>
    function showArtistSongs(artistId, artistName) {
        const modal = document.getElementById('songListModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        document.getElementById('modalArtistName').textContent = artistName;
        
        // Lấy tất cả bài hát của nghệ sĩ mà không phân trang
        fetch(`/api/artist-songs.php?artist_id=${artistId}`)
            .then(response => response.json())
            .then(songs => {
                const songList = document.getElementById('songList');
                songList.innerHTML = '';
                
                if (songs.length === 0) {
                    songList.innerHTML = '<p class="text-gray-400">Nghệ sĩ này chưa có bài hát</p>';
                    return;
                }
                
                // Hiển thị tất cả bài hát không phân trang
                songs.forEach((song, index) => {
                    const songElement = `
                        <div class="flex items-center justify-between p-3 hover:bg-[#2f2739] rounded-lg group">
                            <div class="flex items-center space-x-4 flex-1">
                                <span class="text-gray-400 w-4">${index + 1}</span>
                                <img src="${song.image}" 
                                     alt="${song.title}" 
                                     class="w-12 h-12 rounded object-cover">
                                <div class="min-w-0">
                                    <h4 class="text-white font-medium truncate">${song.title}</h4>
                                    <p class="text-gray-400 text-sm truncate">${song.album}</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-4">
                                <span class="text-gray-400 text-sm">${song.duration}</span>
                                <button onclick="playSongAndUpdatePlays('${song.url}', '${song.title}', '${artistName}', '${song.image}')"
                                        class="text-gray-400 hover:text-white px-4">
                                    <i class="fas fa-play"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    songList.innerHTML += songElement;
                });
            })
            .catch(error => {
                console.error('Error:', error);
                songList.innerHTML = '<p class="text-red-500">Lỗi tải danh sách bài hát</p>';
            });
    }

    function closeSongList() {
        const modal = document.getElementById('songListModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    // Đóng modal khi click bên ngoài
    document.getElementById('songListModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeSongList();
        }
    });
    </script>

    <!-- Modal tạo danh sách phát -->
    <div id="createPlaylistModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-[#170f23] rounded-lg p-6 max-w-md w-full mx-4">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-white">Tạo danh sách phát mới</h3>
            <button onclick="closeCreatePlaylistModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="createPlaylistForm" method="POST">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Tên danh sách phát</label>
                    <input type="text" name="name" required 
                           class="w-full p-2 bg-[#2f2739] text-white rounded focus:outline-none focus:border-[#1DB954]">
                           <p id="playlistNameError" class="text-red-500 text-sm mt-1 hidden">Tên playlist đã tồn tại.</p>

                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Mô tả</label>
                    <textarea name="description" rows="3"
                              class="w-full p-2 bg-[#2f2739] text-white rounded focus:outline-none focus:border-[#1DB954]"></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="closeCreatePlaylistModal()"
                        class="px-4 py-2 bg-[#2f2739] text-white rounded hover:bg-[#3f3749]">
                    Hủy
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-[#1DB954] text-white rounded hover:bg-[#1ed760]">
                    Tạo
                </button>
            </div>
        </form>
    </div>
</div>



    <!-- Modal thêm bài hát vào danh sách -->
    <div id="addToPlaylistModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-[#170f23] rounded-lg p-6 max-w-md w-full mx-4">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-white">Thêm vào danh sách phát</h3>
            <button onclick="closeAddToPlaylistModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- Display message here -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="mb-4 text-green-500">
                <?= htmlspecialchars($_SESSION['message']) ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php elseif (isset($_SESSION['error'])): ?>
            <div class="mb-4 text-red-500">
                <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div id="playlistList" class="space-y-2 max-h-60 overflow-y-auto">
            <?php if (empty($userPlaylists)): ?>
                <p class="text-gray-400">Bạn chưa có playlist nào</p>
            <?php else: ?>
                <?php foreach ($userPlaylists as $playlist): ?>
                    <form method="post" class="flex items-center justify-between p-2 hover:bg-[#2f2739] rounded">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-[#2f2739] rounded mr-3 flex items-center justify-center">
                                <i class="fas fa-music text-gray-400"></i>
                            </div>
                            <div>
                                <h4 class="text-white"><?= htmlspecialchars($playlist['name']) ?></h4>
                                <p class="text-sm text-gray-400"><?= $playlist['song_count'] ?? 0 ?> bài hát</p>
                            </div>
                        </div>
                        <div>
                            <input type="hidden" name="playlist_id" value="<?= $playlist['id'] ?>">
                            <input type="hidden" name="song_id" class="modal-song-id" value=""> <!-- Sẽ được gán qua JS -->
                            <button type="submit" class="text-gray-400 hover:text-white">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="mt-4">
            <button onclick="openCreatePlaylistModal()" 
                    class="w-full px-4 py-2 bg-[#1DB954] text-white rounded hover:bg-[#1ed760]">
                <i class="fas fa-plus mr-2"></i>Tạo danh sách phát mới
            </button>
        </div>
    </div>
</div>
<div id="notification" class="fixed top-4 left-1/2 transform -translate-x-1/2 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg hidden">
        <p id="notification-message"></p>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Check if there is a session message
            <?php if (isset($_SESSION['message'])): ?>
                var message = <?= json_encode($_SESSION['message']); ?>;
                var messageType = <?= json_encode($_SESSION['message_type']); ?>;
                var notificationElement = document.getElementById('notification');
                var messageElement = document.getElementById('notification-message');

                // Set the message text and type
                messageElement.innerText = message;
                notificationElement.classList.remove('hidden');

                // Set the background color based on the message type
                if (messageType === 'success') {
                    notificationElement.classList.add('bg-green-500');
                    notificationElement.classList.remove('bg-red-500', 'bg-yellow-500');
                } else if (messageType === 'error') {
                    notificationElement.classList.add('bg-red-500');
                    notificationElement.classList.remove('bg-green-500', 'bg-yellow-500');
                } else if (messageType === 'info') {
                    notificationElement.classList.add('bg-yellow-500');
                    notificationElement.classList.remove('bg-green-500', 'bg-red-500');
                }

                // Hide the notification after 5 seconds
                setTimeout(function () {
                    notificationElement.classList.add('hidden');
                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?> // Clear session message after it's displayed
                }, 5000);
            <?php endif; ?>
        });
    </script>

    <script>
async function createPlaylist(event) {
    event.preventDefault();
    const form = document.getElementById('createPlaylistForm');
    const formData = new FormData(form);

    // Kiểm tra CSRF token có trong form hay không
    const csrfToken = form.querySelector('input[name="csrf_token"]')?.value;
    if (!csrfToken) {
        alert('CSRF token không tìm thấy!');
        return false;
    }
    
    formData.append('csrf_token', csrfToken); // Thêm token vào formData

    try {
        const response = await fetch('/path-to-your-endpoint', {  // Cập nhật đúng URL
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || 'Lỗi khi tạo playlist');
        }

        alert("Playlist đã được tạo thành công!");
        location.reload();  // Reload trang sau khi tạo playlist thành công
    } catch (error) {
        alert('Đã có lỗi xảy ra: ' + error.message);
    }

    return false;
}



    // Hàm kiểm tra bài hát đã tồn tại trong playlist
    function isSongInPlaylist(playlist, songUrl) {
        return playlist.songs && playlist.songs.some(song => song.url === songUrl);
    }

    // Hàm mở modal tạo playlist
    function openCreatePlaylistModal() {
        document.getElementById('createPlaylistModal').classList.remove('hidden');
        document.getElementById('createPlaylistModal').classList.add('flex');
    }

    // Hàm đóng modal tạo playlist
    function closeCreatePlaylistModal() {
        document.getElementById('createPlaylistModal').classList.add('hidden');
        document.getElementById('createPlaylistModal').classList.remove('flex');
    }

    // Hàm mở modal thêm vào playlist
    

    // Hàm tạo playlist mới
   

    // Hàm thêm bài hát vào playlist
   
    // Hàm xóa bài hát khỏi playlist
    

    // Hàm xóa playlist
    
    // Hiển thị danh sách playlist của người dùng
   
   
    </script>
    <script>
    // Biến toàn cục cho audio và lyrics
    let currentAudio = null;
    let currentSongData = null;

    // Hàm phát nhạc và cập nhật thông tin
    function playSongAndUpdatePlays(url, title, artist, image, songId) {
        // Lưu thông tin bài hát hiện tại
        currentSongData = {
            id: songId,
            title: title,
            artist: artist,
            url: url,
            image: image
        };

        // Cập nhật giao diện player
        document.getElementById('currentSongTitle').textContent = title;
        document.getElementById('currentSongArtist').textContent = artist;
        document.getElementById('currentSongImage').src = image || '/uploads/artists/placeholder.jpg';

        // Xử lý audio
        if (!currentAudio) {
            currentAudio = new Audio();
        }

        if (currentAudio.src !== url) {
            currentAudio.src = url;
        }

        // Phát nhạc
        currentAudio.play()
            .then(() => {
                document.getElementById('playPauseIcon').className = 'fas fa-pause';
                // Cập nhật lượt nghe
                updatePlayCount(songId);
            })
            .catch(error => {
                console.error('Lỗi phát nhạc:', error);
                alert('Không thể phát bài hát này');
            });
    }

    // Hàm hiển thị lyrics
    function toggleLyrics() {
        const lyricsContainer = document.getElementById('lyricsContainer');
        const lyricsText = document.getElementById('lyricsText');

        // Kiểm tra xem có bài hát đang phát không
        if (!currentSongData) {
            alert('Vui lòng chọn một bài hát để xem lời');
            return;
        }

        if (lyricsContainer.classList.contains('hidden')) {
            // Hiển thị container
            lyricsContainer.classList.remove('hidden');
            
            // Hiển thị loading
            lyricsText.innerHTML = '<div class="flex justify-center items-center py-4"><div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-white"></div></div>';

            // Tạo tên file lyrics từ tên bài hát
            const lyricsFileName = currentSongData.title.toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '') // Loại bỏ dấu tiếng Việt
                .replace(/[^a-z0-9]+/g, '_') // Thay thế ký tự đặc biệt bằng dấu gạch dưới
                .replace(/^_+|_+$/g, '') // Xóa dấu gạch dưới ở đầu và cuối
                + '.txt';

            // Thử tải lyrics từ cache
            const cachedLyrics = localStorage.getItem(`lyrics_${currentSongData.id}`);
            if (cachedLyrics) {
                displayLyrics(cachedLyrics, lyricsText);
                return;
            }

            // Nếu không có trong cache, thử tải từ server
            const possiblePaths = [
                `/uploads/lyrics/${lyricsFileName}`,
                `uploads/lyrics/${lyricsFileName}`
            ];

            tryLoadLyrics(possiblePaths, 0, lyricsText);
            } else {
            // Ẩn container
            lyricsContainer.classList.add('hidden');
        }
    }

   
    
    </script>

    <script>
    // Khởi tạo audio player toàn cục
    const audioPlayer = {
        audio: document.getElementById('audioPlayer'),
        isPlaying: false,
        currentSong: null,
        wasPlaying: false,

        init() {
            // Xử lý timeupdate để cập nhật thanh progress
            this.audio.addEventListener('timeupdate', () => {
                if (!this.audio.duration) return;
                
                const progress = (this.audio.currentTime / this.audio.duration) * 100;
                document.getElementById('progress').style.width = `${progress}%`;
                document.getElementById('progressBar').value = progress;
                
                // Cập nhật thời gian
                document.getElementById('currentTime').textContent = this.formatTime(this.audio.currentTime);
                document.getElementById('duration').textContent = this.formatTime(this.audio.duration);
            });

            // Xử lý sự kiện khi bài hát kết thúc
            this.audio.addEventListener('ended', () => {
                this.isPlaying = false;
                this.updateUI();
            });

            // Xử lý sự kiện khi bắt đầu phát
            this.audio.addEventListener('play', () => {
                this.isPlaying = true;
                this.updateUI();
            });

            // Xử lý sự kiện khi tạm dừng
            this.audio.addEventListener('pause', () => {
                this.isPlaying = false;
                this.updateUI();
            });

            // Xử lý progress bar
            const progressBar = document.getElementById('progressBar');
            if (progressBar) {
                // Xử lý khi kéo thanh progress
                progressBar.addEventListener('input', (e) => {
                    this.handleSeek(e.target.value);
                });

                // Xử lý khi kết thúc kéo thanh progress
                progressBar.addEventListener('change', (e) => {
                    this.handleSeek(e.target.value);
                });
            }

            // Xử lý click vào progress container
            const progressContainer = document.querySelector('.progress-container');
            if (progressContainer) {
                progressContainer.addEventListener('click', (e) => {
                    const rect = progressContainer.getBoundingClientRect();
                    const clickPosition = (e.clientX - rect.left) / rect.width;
                    const seekValue = clickPosition * 100;
                    this.handleSeek(seekValue);
                });
            }
        },

        handleSeek(value) {
            if (!this.audio.duration) return;
            
            // Lưu trạng thái phát hiện tại
            this.wasPlaying = !this.audio.paused;
            
            // Tạm dừng audio
            this.audio.pause();
            
            // Đặt thời gian mới
            const seekTime = (value / 100) * this.audio.duration;
            this.audio.currentTime = seekTime;
            
            // Tiếp tục phát nếu đang phát
            if (this.wasPlaying) {
                this.audio.play().catch(error => {
                    console.error('Lỗi phát nhạc:', error);
                });
            }
        },

        playSong(url, title, artist, image, songId) {
            this.currentSong = { url, title, artist, image, songId };
            this.audio.src = url;
            this.audio.play().catch(error => {
                console.error('Lỗi phát nhạc:', error);
            });
            this.isPlaying = true;
            this.updateUI();
        },

        togglePlay() {
            if (!this.currentSong) return;

            if (this.isPlaying) {
                this.audio.pause();
            } else {
                this.audio.play().catch(error => {
                    console.error('Lỗi phát nhạc:', error);
                });
            }
            this.isPlaying = !this.isPlaying;
            this.updateUI();
        },

        updateUI() {
            const playerLayer = document.getElementById('audioPlayerLayer');
            if (this.currentSong) {
                playerLayer.classList.remove('hidden');
                document.getElementById('currentSongTitle').textContent = this.currentSong.title;
                document.getElementById('currentSongArtist').textContent = this.currentSong.artist;
                document.getElementById('currentSongImage').src = this.currentSong.image || '/uploads/artists/placeholder.jpg';
            }

            const playPauseIcon = document.getElementById('playPauseIcon');
            if (playPauseIcon) {
                playPauseIcon.className = this.isPlaying ? 'fas fa-pause' : 'fas fa-play';
            }
        },

        formatTime(seconds) {
            if (isNaN(seconds)) return "0:00";
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = Math.floor(seconds % 60);
            return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
        }
    };

    // Khởi tạo audio player khi trang load
    audioPlayer.init();

    // Hàm phát nhạc và cập nhật lượt nghe
    function playSongAndUpdatePlays(url, title, artist, image, songId) {
        audioPlayer.playSong(url, title, artist, image, songId);
    }

    // Hàm toggle play/pause
    function togglePlay() {
        audioPlayer.togglePlay();
    }

    // Hàm chuyển bài tiếp theo
    
    // Hàm chuyển bài trước
    function previousSong() {
    const currentSongElement = document.querySelector('.play-song i.fa-pause')?.closest('.play-song');
    if (!currentSongElement) return;

    // Nếu đang phát được hơn 3 giây, quay về đầu bài hát
    if (audioPlayer.audio.currentTime > 3) {
        audioPlayer.audio.currentTime = 0;
        return;
    }

    const prevSongElement = currentSongElement.closest('tr').previousElementSibling?.querySelector('.play-song');
    if (prevSongElement) {
        prevSongElement.click();
    } else {
        const allSongs = document.querySelectorAll('.play-song'); 
        allSongs[allSongs.length - 1]?.click();
    }
}

    </script>

    <script>
    // Biến lưu danh sách bài hát đã thích
    let likedSongsList = <?= json_encode($likedSongs) ?>;

    // Hàm phát tất cả bài hát đã thích
    function playAllLikedSongs() {
        if (likedSongsList.length === 0) return;
        
        // Phát bài đầu tiên
        const firstSong = likedSongsList[0];
        playSongAndUpdatePlays(
            firstSong.file_path,
            firstSong.title,
            firstSong.artist,
            firstSong.cover_image,
            firstSong.id,
            firstSong.lyrics_file
        );
        
        // Lưu danh sách phát vào localStorage
        localStorage.setItem('currentPlaylist', JSON.stringify(likedSongsList));
        localStorage.setItem('currentPlaylistIndex', '0');
    }

    // Hàm phát ngẫu nhiên bài hát đã thích
    function shuffleLikedSongs() {
        if (likedSongsList.length === 0) return;
        
        // Tạo bản sao của mảng và xáo trộn
        const shuffledSongs = [...likedSongsList];
        for (let i = shuffledSongs.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [shuffledSongs[i], shuffledSongs[j]] = [shuffledSongs[j], shuffledSongs[i]];
        }
        
        // Phát bài đầu tiên
        const firstSong = shuffledSongs[0];
        playSongAndUpdatePlays(
            firstSong.file_path,
            firstSong.title,
            firstSong.artist,
            firstSong.cover_image,
            firstSong.id,
            firstSong.lyrics_file
        );
        
        // Lưu danh sách phát vào localStorage
        localStorage.setItem('currentPlaylist', JSON.stringify(shuffledSongs));
        localStorage.setItem('currentPlaylistIndex', '0');
    }

    // Hàm phát bài tiếp theo
    function playNextSong() {
        const playlist = JSON.parse(localStorage.getItem('currentPlaylist') || '[]');
        const currentIndex = parseInt(localStorage.getItem('currentPlaylistIndex') || '0');
        
        if (playlist.length === 0 || currentIndex >= playlist.length - 1) return;
        
        const nextSong = playlist[currentIndex + 1];
        playSongAndUpdatePlays(
            nextSong.file_path,
            nextSong.title,
            nextSong.artist,
            nextSong.cover_image,
            nextSong.id,
            nextSong.lyrics_file
        );
        
        localStorage.setItem('currentPlaylistIndex', (currentIndex + 1).toString());
    }

    // Hàm phát bài trước đó
    function playPreviousSong() {
        const playlist = JSON.parse(localStorage.getItem('currentPlaylist') || '[]');
        const currentIndex = parseInt(localStorage.getItem('currentPlaylistIndex') || '0');
        
        if (playlist.length === 0 || currentIndex <= 0) return;
        
        const prevSong = playlist[currentIndex - 1];
        playSongAndUpdatePlays(
            prevSong.file_path,
            prevSong.title,
            prevSong.artist,
            prevSong.cover_image,
            prevSong.id,
            prevSong.lyrics_file
        );
        
        localStorage.setItem('currentPlaylistIndex', (currentIndex - 1).toString());
    }

    // Thêm sự kiện khi bài hát kết thúc
    document.addEventListener('DOMContentLoaded', function() {
        const audioPlayer = document.getElementById('audioPlayer');
        if (audioPlayer) {
            audioPlayer.addEventListener('ended', function() {
                playNextSong();
            });
        }
    });
    </script>

<script>
function openAddToPlaylistModal(songId) {
    console.log("Gán song_id =", songId);
    document.querySelectorAll('.modal-song-id').forEach(input => {
        input.value = songId;
    });
    document.getElementById('addToPlaylistModal').classList.remove('hidden');
}

function closeAddToPlaylistModal() {
    document.getElementById('addToPlaylistModal').classList.add('hidden');
}
</script>

<script>
// ... existing code ...

// Hàm phát toàn bộ playlist
function playPlaylist() {
    const playlist = <?= json_encode($songs) ?>;
    if (!playlist || playlist.length === 0) return;

    // Lưu playlist vào localStorage để sử dụng ở các hàm khác
    localStorage.setItem('currentPlaylist', JSON.stringify(playlist));
    localStorage.setItem('currentPlaylistIndex', '0');

    // Phát bài hát đầu tiên
    const firstSong = playlist[0];
    playSongAndUpdatePlays(
        firstSong.file_path,
        firstSong.title,
        firstSong.artist,
        firstSong.cover_image,
        firstSong.id
    );

    // Cập nhật trạng thái nút play
    const playButton = document.querySelector('.w-14.h-14.rounded-full.bg-spotify-green i');
    if (playButton) {
        playButton.className = 'fas fa-pause text-2xl';
    }

    // Thêm event listener cho việc kết thúc bài hát
    const audioPlayer = document.getElementById('audioPlayer');
    if (audioPlayer) {
        audioPlayer.addEventListener('ended', function() {
            playNextSongInPlaylist();
        });
    }
}

// Hàm phát bài tiếp theo trong playlist
function playNextSongInPlaylist() {
    const playlist = JSON.parse(localStorage.getItem('currentPlaylist') || '[]');
    const currentIndex = parseInt(localStorage.getItem('currentPlaylistIndex') || '0');
    
    if (playlist.length === 0) return;
    
    // Tính toán index của bài tiếp theo
    const nextIndex = (currentIndex + 1) % playlist.length;
    const nextSong = playlist[nextIndex];
    
    // Phát bài tiếp theo
    playSongAndUpdatePlays(
        nextSong.file_path,
        nextSong.title,
        nextSong.artist,
        nextSong.cover_image,
        nextSong.id
    );
    
    // Cập nhật index trong localStorage
    localStorage.setItem('currentPlaylistIndex', nextIndex.toString());
}

// ... existing code ...
</script>

<script>
// ... existing code ...

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

<script>
// ... existing code ...

function removeSongFromPlaylist(playlistId, songId) {
    if (!confirm('Bạn có chắc chắn muốn xóa bài hát này khỏi playlist?')) {
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="remove_song">
        <input type="hidden" name="playlist_id" value="${playlistId}">
        <input type="hidden" name="song_id" value="${songId}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Thêm hàm hiển thị thông báo
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    } text-white z-50`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// ... existing code ...
</script>

<script>
function shufflePlaylist() {
    const songs = document.querySelectorAll('#user-playlist-songs tr');
    const songArray = Array.from(songs);
    
    // Fisher-Yates shuffle algorithm
    for (let i = songArray.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [songArray[i], songArray[j]] = [songArray[j], songArray[i]];
    }
    
    // Clear the current playlist
    const playlistContainer = document.getElementById('user-playlist-songs');
    playlistContainer.innerHTML = '';
    
    // Add shuffled songs back to the playlist
    songArray.forEach((song, index) => {
        // Update the song number
        const numberCell = song.querySelector('td:first-child span:first-child');
        if (numberCell) {
            numberCell.textContent = index + 1;
        }
        playlistContainer.appendChild(song);
    });
    
    // Play the first song in the shuffled playlist
    const firstSong = songArray[0];
    if (firstSong) {
        const playButton = firstSong.querySelector('td:first-child span:last-child i');
        if (playButton) {
            playButton.click();
        }
    }
}
</script>

</body>
</html>
