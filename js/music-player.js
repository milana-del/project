// Music Player functionality
class MusicPlayer {
    constructor() {
        this.audio = new Audio();
        this.currentTrackIndex = 0;
        this.isPlaying = false;
        this.isShuffled = false;
        this.isRepeating = false;
        this.volume = 0.5;
        this.tracks = [];
        this.originalTracks = [];
        
        this.init();
    }

    init() {
        this.loadTracks();
        this.setupAudio();
        this.setupEventListeners();
        this.renderPlaylist();
        this.loadPlayerState();
    }

    loadTracks() {
        // Треки Chase Atlantic - замените src на реальные пути к аудиофайлам
        this.tracks = [
            {
                id: 1,
                title: "Swim",
                artist: "Chase Atlantic",
                album: "Chase Atlantic",
                duration: "3:48",
                src: "assets/music/swim.mp3",
                cover: "assets/album-covers/swim.png"
            },
            {
                id: 2,
                title: "Friends",
                artist: "Chase Atlantic",
                album: "Nostalgia",
                duration: "3:50",
                src: "assets/music/friends.mp3",
                cover: "assets/album-covers/friends.jfif"
            },
            {
                id: 3,
                title: "Into It",
                artist: "Chase Atlantic",
                album: "Phases",
                duration: "3:16",
                src: "assets/music/into-it.mp3",
                cover: "assets/album-covers/into-it.jpg"
            },
            {
                id: 4,
                title: "Heaven and Back",
                artist: "Chase Atlantic",
                album: "Phases",
                duration: "4:08",
                src: "assets/music/heaven-and-back.mp3",
                cover: "assets/album-covers/heaven-and-back.jpg"
            },
            {
                id: 5,
                title: "OHMAMI",
                artist: "Chase Atlantic",
                album: "Beauty In Death",
                duration: "3:46",
                src: "assets/music/ohmami.mp3",
                cover: "assets/album-covers/ohmami.png"
            },
            {
                id: 6,
                title: "Slow Down",
                artist: "Chase Atlantic",
                album: "Beauty In Death",
                duration: "3:32",
                src: "assets/music/slow-down.mp3",
                cover: "assets/album-covers/slow-down.jpg"
            },
            {
                id: 7,
                title: "Okay",
                artist: "Chase Atlantic",
                album: "Phases",
                duration: "3:32",
                src: "assets/music/okay.mp3",
                cover: "assets/album-covers/okay.jpg"
            },
            {
                id: 8,
                title: "Consume",
                artist: "Chase Atlantic",
                album: "Phases",
                duration: "4:27",
                src: "assets/music/consume.mp3",
                cover: "assets/album-covers/consume.jpg"
            }
        ];

        this.originalTracks = [...this.tracks];
    }

    setupAudio() {
        this.audio.volume = this.volume;
        
        this.audio.addEventListener('loadedmetadata', () => {
            this.updateDuration();
        });

        this.audio.addEventListener('timeupdate', () => {
            this.updateProgress();
        });

        this.audio.addEventListener('ended', () => {
            this.nextTrack();
        });

        this.audio.addEventListener('error', (e) => {
            console.error('Audio error:', e);
            this.showError('Ошибка загрузки трека');
        });
    }

    setupEventListeners() {
        // Кнопки управления
        document.getElementById('playBtn').addEventListener('click', () => this.togglePlay());
        document.getElementById('prevBtn').addEventListener('click', () => this.previousTrack());
        document.getElementById('nextBtn').addEventListener('click', () => this.nextTrack());
        document.getElementById('shuffleBtn').addEventListener('click', () => this.toggleShuffle());
        document.getElementById('repeatBtn').addEventListener('click', () => this.toggleRepeat());

        // Прогресс и громкость
        document.getElementById('progressSlider').addEventListener('input', (e) => this.seek(e.target.value));
        document.getElementById('volumeSlider').addEventListener('input', (e) => this.setVolume(e.target.value));
        document.getElementById('volumeBtn').addEventListener('click', () => this.toggleMute());

        // Прогресс-бар клик
        document.querySelector('.progress-bar').addEventListener('click', (e) => {
            const rect = e.currentTarget.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            this.seek(percent * 100);
        });

      // Клавиши управления
document.addEventListener('keydown', (e) => {
    // Проверяем ВСЕ элементы ввода и редактирования
    if (e.target.matches('input, textarea, select, button, [contenteditable="true"]')) {
        return; // Не блокируем клавиши в элементах ввода
    }
    
    switch(e.code) {
        case 'Space':
            e.preventDefault();
            this.togglePlay();
            break;
        case 'ArrowLeft':
            if (e.altKey) {
                e.preventDefault();
                this.previousTrack();
            }
            break;
        case 'ArrowRight':
            if (e.altKey) {
                e.preventDefault();
                this.nextTrack();
            }
            break;
        case 'ArrowUp':
            if (e.altKey) {
                e.preventDefault();
                this.setVolume(Math.min(100, this.volume * 100 + 10));
            }
            break;
        case 'ArrowDown':
            if (e.altKey) {
                e.preventDefault();
                this.setVolume(Math.max(0, this.volume * 100 - 10));
            }
            break;
        case 'KeyM':
            e.preventDefault();
            this.toggleMute();
            break;
    }
});
    }

    renderPlaylist() {
        const tracksList = document.getElementById('tracksList');
        tracksList.innerHTML = '';

        this.tracks.forEach((track, index) => {
            const trackElement = document.createElement('div');
            trackElement.className = `track-item ${index === this.currentTrackIndex ? 'playing' : ''}`;
            trackElement.innerHTML = `
                <div class="track-number">
                    ${index === this.currentTrackIndex ? 
                        '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M6 3.5a.5.5 0 0 1 .5.5v8a.5.5 0 0 1-1 0V4a.5.5 0 0 1 .5-.5zm4 0a.5.5 0 0 1 .5.5v8a.5.5 0 0 1-1 0V4a.5.5 0 0 1 .5-.5z"/></svg>' : 
                        (index + 1)
                    }
                </div>
                <div class="track-details">
                    <h4>${track.title}</h4>
                    <p>${track.artist}</p>
                </div>
                <div class="track-duration">${track.duration}</div>
            `;

            trackElement.addEventListener('click', () => this.playTrack(index));
            tracksList.appendChild(trackElement);
        });
    }

    playTrack(index) {
        this.currentTrackIndex = index;
        const track = this.tracks[this.currentTrackIndex];
        
        this.audio.src = track.src;
        this.updateTrackInfo(track);
        
        this.audio.play().then(() => {
            this.isPlaying = true;
            this.updatePlayButton();
            this.renderPlaylist();
            this.savePlayerState();
        }).catch(error => {
            console.error('Play failed:', error);
            this.showError('Не удалось воспроизвести трек');
        });
    }

    togglePlay() {
        if (this.audio.src === '') {
            this.playTrack(0);
            return;
        }

        if (this.isPlaying) {
            this.audio.pause();
        } else {
            this.audio.play().catch(error => {
                console.error('Play failed:', error);
                this.showError('Не удалось воспроизвести трек');
            });
        }
        
        this.isPlaying = !this.isPlaying;
        this.updatePlayButton();
        this.updateAlbumArtAnimation();
        this.savePlayerState();
    }

    updatePlayButton() {
        const playBtn = document.getElementById('playBtn');
        const playIcon = playBtn.querySelector('.play-icon');
        const pauseIcon = playBtn.querySelector('.pause-icon');

        if (this.isPlaying) {
            playIcon.style.display = 'none';
            pauseIcon.style.display = 'block';
            playBtn.title = 'Пауза';
        } else {
            playIcon.style.display = 'block';
            pauseIcon.style.display = 'none';
            playBtn.title = 'Воспроизвести';
        }
    }

    updateAlbumArtAnimation() {
        const albumArt = document.querySelector('.album-art');
        if (this.isPlaying) {
            albumArt.classList.add('playing');
        } else {
            albumArt.classList.remove('playing');
        }
    }

    previousTrack() {
        this.currentTrackIndex = (this.currentTrackIndex - 1 + this.tracks.length) % this.tracks.length;
        this.playTrack(this.currentTrackIndex);
    }

    nextTrack() {
        if (this.isRepeating) {
            this.audio.currentTime = 0;
            this.audio.play();
        } else {
            this.currentTrackIndex = (this.currentTrackIndex + 1) % this.tracks.length;
            this.playTrack(this.currentTrackIndex);
        }
    }

    toggleShuffle() {
        this.isShuffled = !this.isShuffled;
        const shuffleBtn = document.getElementById('shuffleBtn');
        
        if (this.isShuffled) {
            shuffleBtn.classList.add('active');
            this.shuffleTracks();
        } else {
            shuffleBtn.classList.remove('active');
            this.tracks = [...this.originalTracks];
            this.renderPlaylist();
        }
        
        this.savePlayerState();
    }

    shuffleTracks() {
        const currentTrack = this.tracks[this.currentTrackIndex];
        const otherTracks = this.tracks.filter((_, index) => index !== this.currentTrackIndex);
        
        // Fisher-Yates shuffle
        for (let i = otherTracks.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [otherTracks[i], otherTracks[j]] = [otherTracks[j], otherTracks[i]];
        }
        
        this.tracks = [currentTrack, ...otherTracks];
        this.renderPlaylist();
    }

    toggleRepeat() {
        this.isRepeating = !this.isRepeating;
        const repeatBtn = document.getElementById('repeatBtn');
        
        if (this.isRepeating) {
            repeatBtn.classList.add('active');
        } else {
            repeatBtn.classList.remove('active');
        }
        
        this.savePlayerState();
    }

    seek(percent) {
        if (this.audio.duration) {
            this.audio.currentTime = (percent / 100) * this.audio.duration;
        }
    }

    setVolume(percent) {
        this.volume = percent / 100;
        this.audio.volume = this.volume;
        document.getElementById('volumeSlider').value = percent;
        
        this.updateVolumeIcon();
        this.savePlayerState();
    }

    toggleMute() {
        if (this.audio.volume > 0) {
            this.previousVolume = this.audio.volume;
            this.audio.volume = 0;
            document.getElementById('volumeSlider').value = 0;
        } else {
            this.audio.volume = this.previousVolume || 0.5;
            document.getElementById('volumeSlider').value = (this.audio.volume * 100);
        }
        
        this.updateVolumeIcon();
    }

    updateVolumeIcon() {
        const volumeBtn = document.getElementById('volumeBtn');
        const volumeHigh = volumeBtn.querySelector('.volume-high');
        const volumeMute = volumeBtn.querySelector('.volume-mute');

        if (this.audio.volume === 0) {
            volumeHigh.style.display = 'none';
            volumeMute.style.display = 'block';
            volumeBtn.title = 'Включить звук';
        } else {
            volumeHigh.style.display = 'block';
            volumeMute.style.display = 'none';
            volumeBtn.title = 'Выключить звук';
        }
    }

    updateTrackInfo(track) {
        document.getElementById('currentTrack').textContent = track.title;
        document.getElementById('currentArtist').textContent = track.artist;
        document.getElementById('currentAlbumArt').src = track.cover;
    }

    updateProgress() {
        const progress = document.getElementById('progress');
        const progressSlider = document.getElementById('progressSlider');
        const currentTime = document.getElementById('currentTime');
        
        if (this.audio.duration) {
            const percent = (this.audio.currentTime / this.audio.duration) * 100;
            progress.style.width = `${percent}%`;
            progressSlider.value = percent;
            currentTime.textContent = this.formatTime(this.audio.currentTime);
        }
    }

    updateDuration() {
        const duration = document.getElementById('duration');
        duration.textContent = this.formatTime(this.audio.duration);
    }

    formatTime(seconds) {
        if (isNaN(seconds)) return '0:00';
        
        const minutes = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${minutes}:${secs.toString().padStart(2, '0')}`;
    }

    showError(message) {
        // Создаем уведомление об ошибке
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = message;
        errorDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #f44336;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            z-index: 10000;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        `;
        
        document.body.appendChild(errorDiv);
        
        setTimeout(() => {
            errorDiv.remove();
        }, 3000);
    }

    savePlayerState() {
        const state = {
            currentTrackIndex: this.currentTrackIndex,
            currentTime: this.audio.currentTime,
            volume: this.volume,
            isPlaying: this.isPlaying,
            isShuffled: this.isShuffled,
            isRepeating: this.isRepeating
        };
        
        localStorage.setItem('musicPlayerState', JSON.stringify(state));
    }

    loadPlayerState() {
        const savedState = localStorage.getItem('musicPlayerState');
        if (savedState) {
            try {
                const state = JSON.parse(savedState);
                this.currentTrackIndex = state.currentTrackIndex || 0;
                this.volume = state.volume || 0.5;
                this.isShuffled = state.isShuffled || false;
                this.isRepeating = state.isRepeating || false;
                
                this.setVolume(this.volume * 100);
                
                if (state.isShuffled) {
                    document.getElementById('shuffleBtn').classList.add('active');
                    this.shuffleTracks();
                }
                
                if (state.isRepeating) {
                    document.getElementById('repeatBtn').classList.add('active');
                }
                
                // Загружаем первый трек
                if (this.tracks.length > 0) {
                    const track = this.tracks[this.currentTrackIndex];
                    this.updateTrackInfo(track);
                    this.audio.currentTime = state.currentTime || 0;
                    
                    if (state.isPlaying) {
                        this.audio.src = track.src;
                        this.audio.play().then(() => {
                            this.isPlaying = true;
                            this.updatePlayButton();
                            this.updateAlbumArtAnimation();
                        }).catch(() => {
                            this.isPlaying = false;
                            this.updatePlayButton();
                        });
                    }
                }
                
                this.renderPlaylist();
                
            } catch (error) {
                console.error('Error loading player state:', error);
            }
        }
    }
}

// Initialize music player when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new MusicPlayer();
});