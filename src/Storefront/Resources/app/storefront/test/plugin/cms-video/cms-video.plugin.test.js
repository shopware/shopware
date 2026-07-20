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

    beforeEach(() => {
        document.body.innerHTML = template;
    });

    afterEach(() => {
        jest.clearAllMocks();
        document.body.innerHTML = '';
    });

    test('applies stored volume when valid value exists', () => {
        const video = document.querySelector('video');
        Object.defineProperty(video, 'volume', { value: 0, writable: true, configurable: true });
        defineReadyState(video, 2);
        video.setAttribute('poster', 'https://example.com/poster.jpg');

        window.localStorage.setItem('sw-cms-video-volume', '0.6');

        const plugin = initPlugin();
        plugin.init();

        expect(video.volume).toBe(0.6);
    });

    test('falls back to default volume when stored value is invalid', () => {
        const video = document.querySelector('video');
        Object.defineProperty(video, 'volume', { value: 0.8, writable: true, configurable: true });
        defineReadyState(video, 2);
        video.setAttribute('poster', 'https://example.com/poster.jpg');

        window.localStorage.setItem('sw-cms-video-volume', '2');

        const plugin = initPlugin();
        plugin.init();

        expect(video.volume).toBe(0);
    });

    test('persists volume changes to localStorage', () => {
        const video = document.querySelector('video');
        Object.defineProperty(video, 'volume', { value: 0.7, writable: true, configurable: true });
        defineReadyState(video, 2);
        video.setAttribute('poster', 'https://example.com/poster.jpg');

        const setItemSpy = jest.spyOn(window.localStorage.__proto__, 'setItem');

        const plugin = initPlugin();
        plugin.init();
        video.volume = 0.7;
        plugin._onVolumeChange();

        expect(setItemSpy).toHaveBeenCalledWith('sw-cms-video-volume', '0.7');
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
