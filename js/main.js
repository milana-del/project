// Main JavaScript file
class ChaseAtlanticApp {
    constructor() {
        this.init();
    }

    init() {
        this.setupNavigation();
        this.setupSmoothScroll();
        this.setupHeaderVideo();
        this.setupScrollEffects();
        this.setupMusicPlayer();
        this.setupGalleryIntegration();
    }

    setupNavigation() {
        const hamburger = document.querySelector('.hamburger');
        const navMenu = document.querySelector('.nav-menu');
        const navLinks = document.querySelectorAll('.nav-link');

        // Mobile menu toggle
        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            navMenu.classList.toggle('active');
        });

        // Close mobile menu when clicking on links
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                hamburger.classList.remove('active');
                navMenu.classList.remove('active');
            });
        });

        // Navbar scroll effect
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                navbar.style.background = 'rgba(26, 26, 26, 0.98)';
                navbar.style.padding = '0.5rem 0';
            } else {
                navbar.style.background = 'rgba(26, 26, 26, 0.95)';
                navbar.style.padding = '1rem 0';
            }
        });
    }

    setupSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    setupHeaderVideo() {
        const video = document.getElementById('headerVideo');
        if (video) {
            video.playbackRate = 0.8; // Slow down video slightly
            
            // Handle video loading
            video.addEventListener('loadeddata', () => {
                console.log('Header video loaded successfully');
            });

            video.addEventListener('error', () => {
                console.error('Error loading header video');
                // Fallback to background image
                document.querySelector('.video-background').style.backgroundImage = 'url("assets/fallback-bg.jpg")';
            });
        }
    }

    setupScrollEffects() {
        // Add scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe elements for animation
        document.querySelectorAll('.about-text, .stat-item, .slide, .discography-table').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
    }

    setupMusicPlayer() {
        // Music player will auto-initialize from music-player.js
        console.log('Music player ready');
        
        // Add additional music player related functionality if needed
        this.setupMusicPlayerAnimations();
    }

    setupMusicPlayerAnimations() {
        // Add smooth appearance animation for music player section
        const musicSection = document.querySelector('.player-section');
        if (musicSection) {
            musicSection.style.opacity = '0';
            musicSection.style.transform = 'translateY(30px)';
            musicSection.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
            
            const musicObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.2 });
            
            musicObserver.observe(musicSection);
        }
        
        // Add keyboard shortcuts for music player
        this.setupMusicKeyboardShortcuts();
    }

    setupMusicKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Only trigger if not focused on input elements
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            
            const musicPlayer = window.musicPlayerInstance;
            if (!musicPlayer) return;
            
            switch(e.code) {
                case 'Space':
                    e.preventDefault();
                    if (musicPlayer.togglePlay) {
                        musicPlayer.togglePlay();
                    }
                    break;
                case 'ArrowLeft':
                    if (e.altKey && musicPlayer.previousTrack) {
                        e.preventDefault();
                        musicPlayer.previousTrack();
                    }
                    break;
                case 'ArrowRight':
                    if (e.altKey && musicPlayer.nextTrack) {
                        e.preventDefault();
                        musicPlayer.nextTrack();
                    }
                    break;
                case 'KeyM':
                    if (musicPlayer.toggleMute) {
                        e.preventDefault();
                        musicPlayer.toggleMute();
                    }
                    break;
            }
        });
    }

    setupGalleryIntegration() {
        // Add smooth animation for gallery section
        const gallerySection = document.querySelector('.gallery-section');
        if (gallerySection) {
            gallerySection.style.opacity = '0';
            gallerySection.style.transform = 'translateY(30px)';
            gallerySection.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
            
            const galleryObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.2 });
            
            galleryObserver.observe(gallerySection);
        }
        
        console.log('Gallery integration ready');
    }
}

// Initialize the application
document.addEventListener('DOMContentLoaded', () => {
    new ChaseAtlanticApp();
    
    // Make music player instance globally accessible for keyboard shortcuts
    // This assumes MusicPlayer class is available from music-player.js
    if (typeof MusicPlayer !== 'undefined') {
        window.musicPlayerInstance = new MusicPlayer();
    }
});