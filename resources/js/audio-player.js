import WaveSurfer from 'wavesurfer.js';

function formatTime(seconds) {
    if (!Number.isFinite(seconds)) return '0:00';

    const minutes = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60).toString().padStart(2, '0');

    return `${minutes}:${secs}`;
}

export function initAudioPlayer(root, url) {
    const waveform = root.querySelector('[data-audio-waveform]');
    const playButton = root.querySelector('[data-audio-play]');
    const volumeInput = root.querySelector('[data-audio-volume]');
    const speedSelect = root.querySelector('[data-audio-speed]');
    const currentTimeEl = root.querySelector('[data-audio-current]');
    const durationEl = root.querySelector('[data-audio-duration]');

    const wavesurfer = WaveSurfer.create({
        container: waveform,
        url,
        waveColor: '#a1a1aa',
        progressColor: '#3b82f6',
        cursorColor: '#3b82f6',
        height: 64,
        barWidth: 2,
        barGap: 1,
        barRadius: 2,
        normalize: true,
    });

    wavesurfer.on('ready', () => {
        durationEl.textContent = formatTime(wavesurfer.getDuration());
    });

    wavesurfer.on('timeupdate', (time) => {
        currentTimeEl.textContent = formatTime(time);
    });

    wavesurfer.on('play', () => playButton.setAttribute('data-playing', ''));
    wavesurfer.on('pause', () => playButton.removeAttribute('data-playing'));
    wavesurfer.on('finish', () => playButton.removeAttribute('data-playing'));

    playButton.addEventListener('click', () => wavesurfer.playPause());

    volumeInput?.addEventListener('input', (event) => {
        wavesurfer.setVolume(Number(event.target.value));
    });

    speedSelect?.addEventListener('change', (event) => {
        wavesurfer.setPlaybackRate(Number(event.target.value), true);
    });

    document.addEventListener('livewire:navigating', () => wavesurfer.destroy(), { once: true });
}

window.initAudioPlayer = initAudioPlayer;
