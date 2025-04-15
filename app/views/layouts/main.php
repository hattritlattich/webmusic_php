<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Music Website</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Thêm padding-bottom cho main content để tránh bị player che */
        #mainContent {
            padding-bottom: 100px;
        }
        /* Đảm bảo player luôn ở dưới cùng và hiển thị trên cùng */
        #audioPlayerLayer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 9999;
        }
        /* Ẩn player trong trang quản lý bài hát */
        .admin-songs-page #audioPlayerLayer {
            display: none !important;
        }
        .admin-songs-page #mainContent {
            padding-bottom: 0;
        }
    </style>
</head>
<body class="bg-[#1a1a1a] text-white min-h-screen <?= isset($_GET['page']) && $_GET['page'] === 'admin' && isset($_GET['section']) && $_GET['section'] === 'songs' ? 'admin-songs-page' : '' ?>">
    <!-- Nội dung trang -->
    <div id="mainContent">
        <?php include $content; ?>
    </div>

    <!-- Audio Player Layer -->
    <div id="audioPlayerLayer" class="bg-[#170f23] border-t border-gray-700 p-4 hidden">
        <div class="container mx-auto">
            <div class="flex items-center justify-between">
                <!-- Thông tin bài hát đang phát -->
                <div class="flex items-center space-x-4 flex-1">
                    <img id="currentSongImage" src="/uploads/artists/placeholder.jpg" 
                         alt="Song cover" 
                         class="w-16 h-16 rounded object-cover">
                    <div>
                        <h4 id="currentSongTitle" class="text-white font-medium"></h4>
                        <p id="currentSongArtist" class="text-gray-400 text-sm"></p>
                    </div>
                </div>

                <!-- Controls -->
                <div class="flex items-center space-x-6 flex-1 justify-center">
                    <button onclick="previousSong()" class="text-gray-400 hover:text-white">
                        <i class="fas fa-step-backward"></i>
                    </button>
                    <button id="playPauseBtn" onclick="togglePlay()" class="text-white bg-[#1DB954] rounded-full p-3 hover:bg-[#1ed760]">
                        <i class="fas fa-play" id="playPauseIcon"></i>
                    </button>
                    <button onclick="nextSong()" class="text-gray-400 hover:text-white">
                        <i class="fas fa-step-forward"></i>
                    </button>
                </div>

                <!-- Progress bar và volume -->
                <div class="flex items-center space-x-4 flex-1 justify-end">
                    <span id="currentTime" class="text-gray-400 text-sm">0:00</span>
                    <div class="w-48 bg-gray-600 rounded-full h-1 cursor-pointer" id="progressBar">
                        <div class="bg-white h-1 rounded-full" id="progress" style="width: 0%"></div>
                    </div>
                    <span id="duration" class="text-gray-400 text-sm">0:00</span>
                    
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-volume-up text-gray-400"></i>
                        <input type="range" id="volumeSlider" 
                               class="w-24 accent-[#1DB954]" 
                               min="0" max="100" value="100">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Audio element -->
    <audio id="audioPlayer"></audio>

    <script>
    // Khởi tạo từ localStorage hoặc giá trị mặc định
    let currentSong = localStorage.getItem('currentSong') || null;
    let currentTitle = localStorage.getItem('currentTitle') || '';
    let currentArtist = localStorage.getItem('currentArtist') || '';
    let currentImage = localStorage.getItem('currentImage') || '';
    let isPlaying = localStorage.getItem('isPlaying') === 'true';
    let currentTime = localStorage.getItem('currentTime') || 0;

    let audioPlayer = document.getElementById('audioPlayer');
    let playerLayer = document.getElementById('audioPlayerLayer');

    // Thêm các biến quản lý playlist
    let originalPlaylist = [];
    let currentPlaylist = [];
    let currentSongIndex = -1;
    let isShuffleOn = false;

    // Khôi phục trạng thái player khi load trang
    function initializePlayer() {
        if (currentSong) {
            playerLayer.classList.remove('hidden');
            
            document.getElementById('currentSongTitle').textContent = currentTitle;
            document.getElementById('currentSongArtist').textContent = currentArtist;
            document.getElementById('currentSongImage').src = currentImage;
            
            audioPlayer.src = currentSong;
            audioPlayer.currentTime = parseFloat(currentTime);
            
            if (isPlaying) {
                audioPlayer.play().catch(e => console.log('Playback failed:', e));
                updatePlayPauseIcon();
            }
        }
    }

    // Gọi hàm khởi tạo khi trang load
    window.addEventListener('load', initializePlayer);

    // Lưu trạng thái khi chuyển tab
    document.addEventListener('visibilitychange', () => {
        localStorage.setItem('isPlaying', audioPlayer.paused ? 'false' : 'true');
        localStorage.setItem('currentTime', audioPlayer.currentTime.toString());
    });

    // Lưu trạng thái trước khi rời trang
    window.addEventListener('beforeunload', () => {
        localStorage.setItem('currentSong', currentSong || '');
        localStorage.setItem('currentTitle', currentTitle || '');
        localStorage.setItem('currentArtist', currentArtist || '');
        localStorage.setItem('currentImage', currentImage || '');
        localStorage.setItem('isPlaying', isPlaying.toString());
        localStorage.setItem('currentTime', audioPlayer.currentTime.toString());
    });

    // Cập nhật hàm playSong
    function playSong(url, title, artist, image) {
        // Tìm index của bài hát trong playlist hiện tại
        currentSongIndex = currentPlaylist.findIndex(song => song.url === url);
        
        // Cập nhật thông tin bài hát
        document.getElementById('currentSongTitle').textContent = title;
        document.getElementById('currentSongArtist').textContent = artist;
        document.getElementById('currentSongImage').src = image;
        
        // Xử lý audio
        if (currentSong !== url) {
            currentSong = url;
            audioPlayer.src = url;
        }
        
        audioPlayer.play();
        isPlaying = true;
        updatePlayPauseIcon();
    }

    // Hàm chuyển bài tiếp theo
    function nextSong() {
        if (currentPlaylist.length === 0) return;
        
        // Tăng index và kiểm tra vòng lặp
        currentSongIndex = (currentSongIndex + 1) % currentPlaylist.length;
        
        const song = currentPlaylist[currentSongIndex];
        if (song) {
            playSong(song.url, song.title, song.artist, song.image);
        } else {
            // Nếu không tìm thấy bài hát, reset về đầu
            currentSongIndex = -1;
            nextSong();
        }
    }

    // Hàm chuyển bài trước
    function previousSong() {
        if (currentPlaylist.length === 0) return;
        
        // Giảm index và kiểm tra vòng lặp
        currentSongIndex = (currentSongIndex - 1 + currentPlaylist.length) % currentPlaylist.length;
        
        const song = currentPlaylist[currentSongIndex];
        if (song) {
            playSong(song.url, song.title, song.artist, song.image);
        } else {
            // Nếu không tìm thấy bài hát, reset về cuối
            currentSongIndex = currentPlaylist.length;
            previousSong();
        }
    }

    // Cập nhật khi nhấn nút shuffle
    function toggleShuffle() {
        isShuffleOn = !isShuffleOn;
        const shuffleBtn = document.getElementById('shuffleBtn');
        shuffleBtn.style.color = isShuffleOn ? '#1DB954' : '#9CA3AF';
        
        if (isShuffleOn) {
            // Tạo playlist ngẫu nhiên
            const shuffled = [...originalPlaylist];
            for (let i = shuffled.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
            }
            currentPlaylist = shuffled;
            currentSongIndex = 0;
        } else {
            // Khôi phục playlist gốc
            currentPlaylist = originalPlaylist;
            currentSongIndex = originalPlaylist.findIndex(song => song.url === currentSong);
        }
    }

    // Khởi tạo playlist khi trang load
    document.addEventListener('DOMContentLoaded', () => {
        originalPlaylist = <?= json_encode($songs) ?>;
        currentPlaylist = [...originalPlaylist];
        
        // Thêm event listener cho các nút điều khiển
        document.getElementById('next-button')?.addEventListener('click', nextSong);
        document.getElementById('prev-button')?.addEventListener('click', previousSong);
        document.getElementById('shuffleBtn')?.addEventListener('click', toggleShuffle);
    });
    </script>
</body>
</html> 