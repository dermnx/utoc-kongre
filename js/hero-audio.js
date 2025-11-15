document.addEventListener('DOMContentLoaded', () => {
    const video = document.getElementById('heroVideo');
    const toggleButton = document.querySelector('.hero-audio-toggle');

    if (!video || !toggleButton) {
        return;
    }

    const icon = toggleButton.querySelector('.hero-audio-toggle__icon');

    const updateButtonState = () => {
        const isMuted = video.muted;
        toggleButton.setAttribute('aria-pressed', (!isMuted).toString());
        toggleButton.setAttribute('aria-label', isMuted ? 'Videonun sesini aç' : 'Videonun sesini kapat');
        if (icon) {
            icon.classList.toggle('fa-volume-off', isMuted);
            icon.classList.toggle('fa-volume-up', !isMuted);
        }
    };

    toggleButton.addEventListener('click', () => {
        video.muted = !video.muted;
        video.defaultMuted = video.muted;
        if (!video.muted) {
            const playAttempt = video.play();
            if (playAttempt && playAttempt.catch) {
                playAttempt.catch(() => {});
            }
        }
        updateButtonState();
    });

    const ensurePlayback = () => {
        const playAttempt = video.play();
        if (playAttempt && playAttempt.catch) {
            playAttempt.catch(() => {});
        }
    };

    video.muted = false;
    video.defaultMuted = false;
    ensurePlayback();
    updateButtonState();
});
