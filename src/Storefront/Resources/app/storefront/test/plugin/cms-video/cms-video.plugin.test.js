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

    const initPlugin = (options = {}) => {
        return new CmsVideoPlugin(document.querySelector('.cms-video-element'), options);
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
        window.localStorage.clear();
        document.body.innerHTML = template;
    });

    afterEach(() => {
        jest.clearAllMocks();
        window.localStorage.clear();
        document.body.innerHTML = '';
    });

    test('returns early from init when no video element is present', () => {
        document.body.innerHTML = '<div class="cms-video-element"></div>';

        const applyStoredVolumeSpy = jest.spyOn(CmsVideoPlugin.prototype, '_applyStoredVolume');
        const registerVolumeEventsSpy = jest.spyOn(CmsVideoPlugin.prototype, '_registerVolumeEvents');

        const plugin = initPlugin();

        expect(plugin._video).toBeNull();
        expect(applyStoredVolumeSpy).not.toHaveBeenCalled();
        expect(registerVolumeEventsSpy).not.toHaveBeenCalled();
    });

    test('applies default volume of 1 when no stored preference exists', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 0 });
        defineReadyState(video, 2);

        initPlugin();

        expect(video.volume).toBe(1);
    });

    test('handles localStorage read errors gracefully when applying volume', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 0 });
        defineReadyState(video, 2);

        const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
        jest.spyOn(window.localStorage.__proto__, 'getItem').mockImplementation(() => {
            throw new Error('SecurityError');
        });

        initPlugin();

        expect(warnSpy).toHaveBeenCalledWith('Failed to read from localStorage:', expect.any(Error));
        expect(video.volume).toBe(1);
    });

    test('applies stored volume of 0 and mutes video when controls are present and stored preference is zero', () => {
        const video = document.querySelector('video');
        video.setAttribute('controls', '');
        defineMediaProperties(video, { volume: 1, muted: false });
        defineReadyState(video, 2);

        window.localStorage.setItem('sw-cms-video-volume', '0');

        initPlugin();

        expect(video.volume).toBe(0);
        expect(video.muted).toBe(true);
    });

    test('ignores stored zero and preserves initial unmuted state when video has no controls', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 1, muted: false });
        defineReadyState(video, 2);

        window.localStorage.setItem('sw-cms-video-volume', '0');
        const setItemSpy = jest.spyOn(window.localStorage.__proto__, 'setItem');

        initPlugin();

        expect(video.volume).toBe(1);
        expect(video.muted).toBe(false);
        expect(setItemSpy).not.toHaveBeenCalled();
        expect(window.localStorage.getItem('sw-cms-video-volume')).toBe('0');
    });

    test('ignores stored zero and preserves template muted state when video has no controls', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 1, muted: true });
        defineReadyState(video, 2);

        window.localStorage.setItem('sw-cms-video-volume', '0');

        initPlugin();

        expect(video.volume).toBe(1);
        expect(video.muted).toBe(true);
    });

    test('applies stored non-zero volume when video has no controls', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 0 });
        defineReadyState(video, 2);
        video.setAttribute('poster', 'https://example.com/poster.jpg');

        window.localStorage.setItem('sw-cms-video-volume', '0.5');

        initPlugin();

        expect(video.volume).toBe(0.5);
    });

    test.each(['2', '-0.5', 'invalid', 'NaN', ''])('falls back to default volume when stored value is invalid (%s)', (invalidValue) => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 0.8 });
        defineReadyState(video, 2);
        video.setAttribute('poster', 'https://example.com/poster.jpg');

        window.localStorage.setItem('sw-cms-video-volume', invalidValue);

        initPlugin();

        expect(video.volume).toBe(1);
    });

    test('respects custom defaultVolume option when falling back', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 0 });
        defineReadyState(video, 2);

        initPlugin({ defaultVolume: 0.8 });

        expect(video.volume).toBe(0.8);
    });

    test.each([0, -1, 1.5, NaN])('falls back to 1 when options.defaultVolume is invalid (%s)', (invalidDefault) => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 0 });
        defineReadyState(video, 2);

        initPlugin({ defaultVolume: invalidDefault });

        expect(video.volume).toBe(1);
    });

    test('persists volume changes to localStorage', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 1, muted: false });
        defineReadyState(video, 2);
        video.setAttribute('poster', 'https://example.com/poster.jpg');

        const setItemSpy = jest.spyOn(window.localStorage.__proto__, 'setItem');

        initPlugin();

        video.volume = 0.7;
        video.dispatchEvent(new Event('volumechange'));

        expect(setItemSpy).toHaveBeenCalledWith('sw-cms-video-volume', '0.7');
    });

    test('handles localStorage write errors gracefully when persisting volume', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 1, muted: false });
        defineReadyState(video, 2);

        const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
        jest.spyOn(window.localStorage.__proto__, 'setItem').mockImplementation(() => {
            throw new Error('QuotaExceededError');
        });

        initPlugin();

        video.volume = 0.8;
        video.dispatchEvent(new Event('volumechange'));

        expect(warnSpy).toHaveBeenCalledWith('Failed to write to localStorage:', expect.any(Error));
    });

    test('restores default volume on muted-to-unmuted transition when volume is zero', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 0, muted: true });
        defineReadyState(video, 2);

        initPlugin();

        expect(video.volume).toBe(1);

        // Simulate external/user setting volume to 0 while muted
        video.volume = 0;

        // User un-mutes video via native controls (triggers volumechange)
        video.muted = false;
        video.dispatchEvent(new Event('volumechange'));

        expect(video.volume).toBe(1);
        expect(window.localStorage.getItem('sw-cms-video-volume')).toBe('1');
    });

    test('preserves existing non-zero volume on muted-to-unmuted transition', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 0.5, muted: true });
        defineReadyState(video, 2);

        window.localStorage.setItem('sw-cms-video-volume', '0.5');

        initPlugin();

        expect(video.volume).toBe(0.5);

        video.muted = false;
        video.dispatchEvent(new Event('volumechange'));

        expect(video.volume).toBe(0.5);
    });

    test('restores custom defaultVolume on muted-to-unmuted transition when volume is zero', () => {
        const video = document.querySelector('video');
        video.setAttribute('controls', '');
        defineMediaProperties(video, { volume: 0, muted: true });
        defineReadyState(video, 2);

        window.localStorage.setItem('sw-cms-video-volume', '0');

        initPlugin({ defaultVolume: 0.6 });

        expect(video.volume).toBe(0);
        expect(video.muted).toBe(true);

        video.muted = false;
        video.dispatchEvent(new Event('volumechange'));

        expect(video.volume).toBe(0.6);
        expect(window.localStorage.getItem('sw-cms-video-volume')).toBe('0.6');
    });

    test('persists volume zero to localStorage and mutes video when volume is set to zero', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 0.7, muted: false });
        defineReadyState(video, 2);

        const setItemSpy = jest.spyOn(window.localStorage.__proto__, 'setItem');

        initPlugin();

        setItemSpy.mockClear();

        // User changes volume to 0 (e.g. dragging slider to 0)
        video.volume = 0;
        video.dispatchEvent(new Event('volumechange'));

        expect(video.muted).toBe(true);
        expect(setItemSpy).toHaveBeenCalledWith('sw-cms-video-volume', '0');
    });

    test('mutes video and preserves zero volume when volume is set to zero while already unmuted', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 0.8, muted: false });
        defineReadyState(video, 2);

        initPlugin();

        // User changes volume to 0 while already playing unmuted
        video.volume = 0;
        video.dispatchEvent(new Event('volumechange'));

        expect(video.volume).toBe(0);
        expect(video.muted).toBe(true);
    });

    test('restores default volume when unmuted after user previously set volume to zero', () => {
        const video = document.querySelector('video');
        defineMediaProperties(video, { volume: 0.8, muted: false });
        defineReadyState(video, 2);

        initPlugin();

        // User changes volume to 0 while unmuted
        video.volume = 0;
        video.dispatchEvent(new Event('volumechange'));

        expect(video.volume).toBe(0);
        expect(video.muted).toBe(true);
        expect(window.localStorage.getItem('sw-cms-video-volume')).toBe('0');

        // User subsequently un-mutes video
        video.muted = false;
        video.dispatchEvent(new Event('volumechange'));

        expect(video.volume).toBe(1);
        expect(video.muted).toBe(false);
        expect(window.localStorage.getItem('sw-cms-video-volume')).toBe('1');
    });

    test('handles repeated volumechange events gracefully', () => {
        const video = document.querySelector('video');
        video.setAttribute('controls', '');
        defineMediaProperties(video, { volume: 0, muted: true });
        defineReadyState(video, 2);

        window.localStorage.setItem('sw-cms-video-volume', '0');

        initPlugin();

        expect(video.volume).toBe(0);
        expect(video.muted).toBe(true);

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
        mockCurrentTime(video);
        video.currentTime = 2;

        const plugin = initPlugin();

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
        video.pause = jest.fn(() => {
            paused = true;
        });

        const plugin = initPlugin();

        const event = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true });
        plugin.el.dispatchEvent(event);

        expect(video.play).toHaveBeenCalled();
    });

    test('restarts the tap animation on repeated calls', () => {
        const video = document.querySelector('video');
        defineReadyState(video, 2);
        video.setAttribute('poster', 'https://example.com/poster.jpg');

        const plugin = initPlugin();

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
