import CmsVideoPlugin from 'src/plugin/cms-video/cms-video.plugin';

/**
 * @sw-package discovery
 */
describe('src/plugin/cms-video/cms-video.plugin', () => {
    const template = `
        <div class="cms-video-element">
            <div class="cms-video-play-icon"></div>
            <video class="cms-video"></video>
        </div>
    `;

    const defineReadyState = (video, readyState) => {
        Object.defineProperty(video, 'readyState', {
            get: () => readyState,
            configurable: true,
        });
    };

    const mockCurrentTime = (video) => {
        let currentTime = 0;
        const setSpy = jest.fn((value) => {
            currentTime = value;
        });

        Object.defineProperty(video, 'currentTime', {
            get: () => currentTime,
            set: setSpy,
            configurable: true,
        });

        return setSpy;
    };

    const initPlugin = () => {
        return new CmsVideoPlugin(document.querySelector('.cms-video-element'));
    };

    const definePausedState = (video, { paused, ended }) => {
        Object.defineProperty(video, 'paused', {
            get: () => paused,
            configurable: true,
        });

        Object.defineProperty(video, 'ended', {
            get: () => ended,
            configurable: true,
        });
    };

    const defineMediaProperties = (video, { volume = 1, muted = false } = {}) => {
        let currentVolume = volume;
        let currentMuted = muted;

        Object.defineProperty(video, 'volume', {
            get: () => currentVolume,
            set: (value) => {
                currentVolume = value;
            },
            configurable: true,
        });

        Object.defineProperty(video, 'muted', {
            get: () => currentMuted,
            set: (value) => {
                currentMuted = value;
            },
            configurable: true,
        });
    };

    beforeEach(() => {
        document.body.innerHTML = template;
    });

    afterEach(() => {
        jest.clearAllMocks();
        document.body.innerHTML = '';
    });

    test('applies default volume of 1 when no stored preference exists', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 0 });
        defineReadyState(video, 2);

        const plugin = initPlugin();
        plugin.init();

        expect(video.volume).toBe(1);
    });

    test('falls back to default volume when stored value is legacy zero', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 0 });
        defineReadyState(video, 2);

        window.localStorage.setItem('sw-cms-video-volume', '0');

        const plugin = initPlugin();
        plugin.init();

        expect(video.volume).toBe(1);
    });

    test('applies stored volume when valid value exists', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 0 });
        defineReadyState(video, 2);
        video.setAttribute('poster', 'https://example.com/poster.jpg');

        window.localStorage.setItem('sw-cms-video-volume', '0.5');

        const plugin = initPlugin();
        plugin.init();

        expect(video.volume).toBe(0.5);
    });

    test.each(['2', '-0.5', 'invalid', 'NaN', ''])('falls back to default volume when stored value is invalid (%s)', (invalidValue) => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 0.8 });
        defineReadyState(video, 2);
        video.setAttribute('poster', 'https://example.com/poster.jpg');

        window.localStorage.setItem('sw-cms-video-volume', invalidValue);

        const plugin = initPlugin();
        plugin.init();

        expect(video.volume).toBe(1);
    });

    test('persists volume changes to localStorage', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 1, muted: false });
        defineReadyState(video, 2);
        video.setAttribute('poster', 'https://example.com/poster.jpg');

        const setItemSpy = jest.spyOn(window.localStorage.__proto__, 'setItem');

        const plugin = initPlugin();
        plugin.init();

        video.volume = 0.7;
        video.dispatchEvent(new Event('volumechange'));

        expect(setItemSpy).toHaveBeenCalledWith('sw-cms-video-volume', '0.7');
    });

    test('restores default volume on muted-to-unmuted transition when volume is zero', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 0, muted: true });
        defineReadyState(video, 2);

        const plugin = initPlugin();
        plugin.init();

        expect(video.volume).toBe(1);

        // Simulate external/user setting volume to 0 while muted
        video.volume = 0;

        // User un-mutes video via native controls (triggers volumechange)
        video.muted = false;
        video.dispatchEvent(new Event('volumechange'));

        expect(video.volume).toBe(1);
    });

    test('preserves existing non-zero volume on muted-to-unmuted transition', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 0.5, muted: true });
        defineReadyState(video, 2);

        const plugin = initPlugin();
        plugin.init();

        video.muted = false;
        video.dispatchEvent(new Event('volumechange'));

        expect(video.volume).toBe(0.5);
    });

    test('does not overwrite stored non-zero volume when volume changes to zero', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 0.7, muted: false });
        defineReadyState(video, 2);

        const setItemSpy = jest.spyOn(window.localStorage.__proto__, 'setItem');

        const plugin = initPlugin();
        plugin.init();

        setItemSpy.mockClear();

        // User changes volume to 0 (e.g. dragging slider to 0)
        video.volume = 0;
        video.dispatchEvent(new Event('volumechange'));

        expect(setItemSpy).not.toHaveBeenCalled();
    });

    test('handles repeated volumechange events gracefully', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 0, muted: true });
        defineReadyState(video, 2);

        const plugin = initPlugin();
        plugin.init();

        // First transition: unmuting from muted=true with volume=0 restores volume to 1
        video.muted = false;
        video.dispatchEvent(new Event('volumechange'));
        expect(video.volume).toBe(1);

        // Repeated volumechange while unmuted does not re-restore or reset
        video.dispatchEvent(new Event('volumechange'));
        expect(video.volume).toBe(1);

        // Subsequent volume adjustment to 0.4 updates and persists
        video.volume = 0.4;
        video.dispatchEvent(new Event('volumechange'));
        expect(video.volume).toBe(0.4);
        expect(window.localStorage.getItem('sw-cms-video-volume')).toBe('0.4');
    });

    test('updates playing state classes with delay', () => {
        jest.useFakeTimers();

        const video = document.querySelector('video');
        definePausedState(video, { paused: true, ended: false });
        defineReadyState(video, 2);
        video.setAttribute('poster', 'https://example.com/poster.jpg');

        const plugin = initPlugin();
        plugin.init();

        definePausedState(video, { paused: false, ended: false });
        plugin._updatePlayingState({ delay: 180 });

        expect(plugin.el.classList.contains('is-playing')).toBe(false);

        jest.advanceTimersByTime(180);

        expect(plugin.el.classList.contains('is-playing')).toBe(true);
        expect(plugin.el.classList.contains('is-paused')).toBe(false);

        jest.useRealTimers();
    });

    test('triggers tap animation on play when currentTime is set', () => {
        const video = document.querySelector('video');
        defineReadyState(video, 2);
        video.setAttribute('poster', 'https://example.com/poster.jpg');
        Object.defineProperty(video, 'currentTime', { value: 2, writable: true, configurable: true });

        const plugin = initPlugin();
        plugin.init();

        const tapSpy = jest.spyOn(plugin, '_triggerTapAnimation');

        plugin._onPlay();

        expect(tapSpy).toHaveBeenCalled();
    });

    test('toggles play and pause when controls are hidden', () => {
        const video = document.querySelector('video');
        defineReadyState(video, 2);
        video.setAttribute('poster', 'https://example.com/poster.jpg');

        let paused = true;
        Object.defineProperty(video, 'paused', {
            get: () => paused,
            configurable: true,
        });

        video.play = jest.fn(() => {
            paused = false;
            return Promise.resolve();
        });
        video.pause = jest.fn(() => {
            paused = true;
        });

        const plugin = initPlugin();
        plugin.init();

        plugin._onToggleClick();
        expect(video.play).toHaveBeenCalled();

        plugin._onToggleClick();
        expect(video.pause).toHaveBeenCalled();
    });

    test('toggles play on keyboard interaction when controls are hidden', () => {
        const video = document.querySelector('video');
        defineReadyState(video, 2);
        video.setAttribute('poster', 'https://example.com/poster.jpg');

        let paused = true;
        Object.defineProperty(video, 'paused', {
            get: () => paused,
            configurable: true,
        });

        video.play = jest.fn(() => {
            paused = false;
            return Promise.resolve();
        });

        const plugin = initPlugin();
        plugin.init();

        const event = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true });
        plugin.el.dispatchEvent(event);

        expect(video.play).toHaveBeenCalled();
    });

    test('restarts the tap animation on repeated calls', () => {
        const video = document.querySelector('video');
        defineReadyState(video, 2);
        video.setAttribute('poster', 'https://example.com/poster.jpg');

        const plugin = initPlugin();
        plugin.init();

        const icon = plugin.el.querySelector('.cms-video-play-icon');
        const addSpy = jest.spyOn(icon, 'addEventListener');

        plugin._triggerTapAnimation();

        expect(plugin.el.classList.contains('is-animating')).toBe(true);
        expect(addSpy).toHaveBeenCalledWith('animationend', expect.any(Function), { once: true });

        const handler = addSpy.mock.calls[0][1];
        handler();

        expect(plugin.el.classList.contains('is-animating')).toBe(false);
    });
});
