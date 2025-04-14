// Khởi tạo player toàn cục
const player = {
    audio: document.getElementById('audioPlayer') || new Audio(),
    currentSong: null,
    isPlaying: false,
    currentLyricsLines: [],

    init() {
        // Xử lý khi bài hát kết thúc
        this.audio.addEventListener('ended', () => {
            this.isPlaying = false;
            this.updateUI();
            this.playNext(); // Tự động phát bài tiếp theo
        });

        // Xử lý cập nhật tiến trình và lyrics
        this.audio.addEventListener('timeupdate', () => {
            this.updateProgress();
            this.updateLyrics();
        });

        // Xử lý volume
        const volumeSlider = document.getElementById('volumeSlider');
        if (volumeSlider) {
            volumeSlider.addEventListener('input', (e) => {
                this.audio.volume = e.target.value / 100;
                this.updateVolumeIcon(e.target.value);
            });
        }

        // Xử lý progress bar
        const progressBar = document.getElementById('progressBar');
        if (progressBar) {
            progressBar.addEventListener('input', (e) => {
                const time = (e.target.value / 100) * this.audio.duration;
                this.audio.currentTime = time;
            });
        }
    },

    playSong(url, title, artist, image) {
        // Nếu đang phát bài khác thì dừng lại
        if (this.currentSong !== url) {
            this.currentSong = url;
            this.audio.src = url;
        }

        // Phát nhạc
        this.audio.play()
            .then(() => {
                this.isPlaying = true;
                this.updateUI();

                // Cập nhật thông tin bài hát
                document.getElementById('currentSongTitle').textContent = title;
                document.getElementById('currentSongArtist').textContent = artist;
                document.getElementById('currentSongImage').src = image || '/uploads/artists/placeholder.jpg';
            })
            .catch(error => {
                console.error('Không thể phát nhạc:', error);
                alert('Không thể phát bài hát này');
            });
    },

    togglePlay() {
        if (!this.currentSong) return;

        if (this.isPlaying) {
            this.audio.pause();
        } else {
            this.audio.play();
        }
        this.isPlaying = !this.isPlaying;
        this.updateUI();
    },

    playNext() {
        const songs = document.querySelectorAll('.play-song');
        if (!songs.length) return;

        // Tìm bài hát đang phát
        const currentSong = Array.from(songs).find(song => 
            song.querySelector('i').classList.contains('fa-pause')
        );

        if (currentSong) {
            // Tìm bài tiếp theo
            const nextSong = currentSong.closest('tr').nextElementSibling?.querySelector('.play-song');
            if (nextSong) {
                nextSong.click();
            } else {
                // Nếu là bài cuối, quay lại bài đầu
                songs[0].click();
            }
        } else {
            // Nếu chưa có bài nào phát, phát bài đầu tiên
            songs[0].click();
        }
    },

    playPrevious() {
        const songs = document.querySelectorAll('.play-song');
        if (!songs.length) return;

        // Nếu đang phát được hơn 3 giây, quay về đầu bài hát
        if (this.audio.currentTime > 3) {
            this.audio.currentTime = 0;
            return;
        }

        // Tìm bài hát đang phát
        const currentSong = Array.from(songs).find(song => 
            song.querySelector('i').classList.contains('fa-pause')
        );

        if (currentSong) {
            // Tìm bài trước đó
            const prevSong = currentSong.closest('tr').previousElementSibling?.querySelector('.play-song');
            if (prevSong) {
                prevSong.click();
            } else {
                // Nếu là bài đầu, chuyển đến bài cuối
                songs[songs.length - 1].click();
            }
        } else {
            // Nếu chưa có bài nào phát, phát bài cuối
            songs[songs.length - 1].click();
        }
    },

    toggleLyrics() {
        const lyricsContainer = document.getElementById('lyricsContainer');
        if (!lyricsContainer) return;

        if (lyricsContainer.classList.contains('hidden')) {
            lyricsContainer.classList.remove('hidden');
            this.loadLyrics();
        } else {
            lyricsContainer.classList.add('hidden');
        }
    },

    loadLyrics() {
        const title = document.getElementById('currentSongTitle').textContent;
        if (!title || title === 'Tên bài hát') {
            alert('Vui lòng chọn một bài hát để xem lời');
            return;
        }

        const lyricsText = document.getElementById('lyricsText');
        if (!lyricsText) return;

        // Hiển thị loading
        lyricsText.innerHTML = '<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-white mx-auto"></div>';

        // Tạo tên file lyrics từ tên bài hát
        const songTitleFormatted = title.toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '') + '.txt';

        // Thử tải lyrics
        fetch(`/uploads/lyrics/${songTitleFormatted}`)
            .then(response => response.text())
            .then(lyrics => {
                if (lyrics.trim()) {
                    const lines = lyrics.split('\n').filter(line => line.trim());
                    this.currentLyricsLines = lines;
                    lyricsText.innerHTML = lines.map(line => 
                        `<div class="lyrics-line mb-2">${line}</div>`
                    ).join('');
                } else {
                    throw new Error('Không tìm thấy lời bài hát');
                }
            })
            .catch(() => {
                lyricsText.innerHTML = '<div class="text-center text-gray-400">Không tìm thấy lời bài hát</div>';
            });
    },

    updateLyrics() {
        if (!this.currentLyricsLines.length) return;
        if (document.getElementById('lyricsContainer').classList.contains('hidden')) return;

        const currentTime = this.audio.currentTime;
        const duration = this.audio.duration;
        const timePerLine = duration / this.currentLyricsLines.length;
        const currentLineIndex = Math.floor(currentTime / timePerLine);

        document.querySelectorAll('.lyrics-line').forEach((line, index) => {
            if (index === currentLineIndex) {
                line.classList.add('text-[#1DB954]', 'font-semibold');
                line.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                line.classList.remove('text-[#1DB954]', 'font-semibold');
            }
        });
    },

    updateUI() {
        // Cập nhật icon play/pause
        const icon = document.getElementById('playPauseIcon');
        if (icon) {
            icon.className = this.isPlaying ? 'fas fa-pause' : 'fas fa-play';
        }
    },

    updateProgress() {
        if (!this.audio.duration) return;

        const progress = (this.audio.currentTime / this.audio.duration) * 100;
        const progressBar = document.getElementById('progress');
        const progressInput = document.getElementById('progressBar');
        const currentTime = document.getElementById('currentTime');
        const duration = document.getElementById('duration');

        if (progressBar) progressBar.style.width = `${progress}%`;
        if (progressInput) progressInput.value = progress;
        if (currentTime) currentTime.textContent = this.formatTime(this.audio.currentTime);
        if (duration) duration.textContent = this.formatTime(this.audio.duration);
    },

    updateVolumeIcon(value) {
        const icon = document.getElementById('volumeIcon');
        if (!icon) return;

        icon.className = 'fas ' + (
            value == 0 ? 'fa-volume-mute' :
            value < 30 ? 'fa-volume-off' :
            value < 70 ? 'fa-volume-down' :
            'fa-volume-up'
        );
    },

    formatTime(seconds) {
        if (isNaN(seconds)) return "0:00";
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.floor(seconds % 60);
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    }
};

// Khởi tạo player khi trang load xong
document.addEventListener('DOMContentLoaded', () => {
    player.init();
});

// Các hàm điều khiển toàn cục
window.playSong = (url, title, artist, image) => player.playSong(url, title, artist, image);
window.togglePlay = () => player.togglePlay();
window.nextSong = () => player.playNext();
window.previousSong = () => player.playPrevious();
window.toggleLyrics = () => player.toggleLyrics(); 