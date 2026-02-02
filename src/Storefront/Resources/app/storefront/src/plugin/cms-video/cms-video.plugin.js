/*
 * @sw-package discovery
 */

import Plugin from 'src/plugin-system/plugin.class';

export default class CmsVideoPlugin extends Plugin {

    static options = {
        storageKey: 'sw-cms-video-volume',
        defaultVolume: 0,
    };

    init() {
        this._video = this.el.querySelector('video');
        this._playingStateTimeout = null;

        if (!this._video) {
            return;
        }

        this._applyStoredVolume();
        this._registerVolumeEvents();
        this._registerPlaybackEvents();
        this._updatePlayingState();

        if (!this._video.hasAttribute('controls')) {
            this._registerToggleEvent();
        }
    }

    _applyStoredVolume() {
        let storedVolume = null;

        try {
            storedVolume = window.localStorage.getItem(this.options.storageKey);
        } catch (error) {
            // localStorage might be unavailable (e.g., in private browsing mode or quota exceeded)
            console.warn('Failed to read from localStorage:', error);
        }

        let volume = parseFloat(storedVolume);

        if (Number.isNaN(volume) || volume < 0 || volume > 1) {
            volume = this.options.defaultVolume;
        }

        this._video.volume = volume;
    }

    _registerVolumeEvents() {
        this._video.addEventListener('volumechange', this._onVolumeChange.bind(this));
    }

    _registerPlaybackEvents() {
        this._video.addEventListener('play', this._onPlay.bind(this));
        this._video.addEventListener('pause', this._updatePlayingState.bind(this, { delay: 0 }));
        this._video.addEventListener('ended', this._updatePlayingState.bind(this, { delay: 0 }));
    }

    _onPlay() {
        this._updatePlayingState({ delay: 180 });

        if (this._video.currentTime > 0) {
            this._triggerTapAnimation();
        }
    }

    _updatePlayingState({ delay = 0 } = {}) {
        const isPlaying = !this._video.paused && !this._video.ended;
        const isPaused = this._video.paused || this._video.ended;

        if (this._playingStateTimeout) {
            window.clearTimeout(this._playingStateTimeout);
            this._playingStateTimeout = null;
        }

        if (isPlaying && delay > 0) {
            this._playingStateTimeout = window.setTimeout(() => {
                this.el.classList.toggle('is-playing', true);
                this.el.classList.toggle('is-paused', false);
                this._playingStateTimeout = null;
            }, delay);
            return;
        }

        this.el.classList.toggle('is-playing', isPlaying);
        this.el.classList.toggle('is-paused', isPaused);
    }

    _onVolumeChange() {
        try {
            window.localStorage.setItem(this.options.storageKey, String(this._video.volume));
        } catch (error) {
            // localStorage might be unavailable (e.g., in private browsing mode or quota exceeded)
            console.warn('Failed to write to localStorage:', error);
        }
    }

    _registerToggleEvent() {
        this.el.addEventListener('click', this._onToggleClick.bind(this));
    }

    _onToggleClick() {
        this._triggerTapAnimation();

        if (this._video.paused) {
            const playPromise = this._video.play();

            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(() => {});
            }

            return;
        }

        this._video.pause();
    }

    _triggerTapAnimation() {
        this.el.classList.remove('is-animating');
        // Force reflow to restart the animation on repeated clicks.
        this.el.offsetHeight;
        this.el.classList.add('is-animating');

        const icon = this.el.querySelector('.cms-video-play-icon');
        if (!icon) {
            return;
        }

        const onAnimationEnd = () => {
            this.el.classList.remove('is-animating');
        };

        icon.addEventListener('animationend', onAnimationEnd, { once: true });
    }
}
