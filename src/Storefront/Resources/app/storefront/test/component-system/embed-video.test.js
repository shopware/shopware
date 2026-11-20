import 'src/component-system/component';
import MediaEmbedVideo from '../../../../views/components/Sw/Media/EmbedVideo';

/**
 * @sw-package framework
 */
const ALLOW = 'autoplay; fullscreen';

let components = [];

function addEmbed({ cookieName = 'vimeo-video', videoUrl = 'https://player.vimeo.com/video/1', videoTitle = 'Video' } = {}) {
    const wrapper = document.createElement('div');
    wrapper.className = 'sw-media-embed-video';
    wrapper.innerHTML = `
        <div class="sw-media-embed-video__consent">
            <div class="sw-media-embed-video__backdrop">
                <button type="button" class="sw-media-embed-video__accept">Accept</button>
            </div>
        </div>
    `;
    document.body.appendChild(wrapper);

    const consentEl = wrapper.querySelector('.sw-media-embed-video__consent');
    const component = new MediaEmbedVideo(consentEl, { videoUrl, videoTitle, cookieName, allow: ALLOW });
    components.push(component);

    return wrapper;
}

function clearCookies() {
    document.cookie.split(';').forEach((cookie) => {
        const name = cookie.split('=')[0].trim();

        if (name) {
            document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/`;
        }
    });
}

describe('views/components/Sw/Media/EmbedVideo', () => {
    afterEach(() => {
        components.forEach((component) => component.destroy());
        components = [];
        clearCookies();
        document.body.innerHTML = '';
    });

    test('is a ShopwareComponent', () => {
        addEmbed();

        expect(components[0]).toBeInstanceOf(window.ShopwareComponent);
    });

    test('replaces the placeholder with the iframe immediately when consent already exists', () => {
        document.cookie = 'vimeo-video=1;path=/';

        const wrapper = addEmbed({ videoUrl: 'https://player.vimeo.com/video/42' });

        const iframe = wrapper.querySelector('iframe.sw-media-embed-video__video');
        expect(iframe).not.toBeNull();
        expect(iframe.getAttribute('src')).toBe('https://player.vimeo.com/video/42');
        expect(iframe.getAttribute('title')).toBe('Video');
        expect(iframe.getAttribute('allow')).toBe(ALLOW);
        expect(iframe.getAttribute('allowfullscreen')).toBe('allowfullscreen');
        expect(wrapper.querySelector('.sw-media-embed-video__consent')).toBeNull();
    });

    test('keeps the consent placeholder gated when no consent is given yet', () => {
        const wrapper = addEmbed();

        expect(wrapper.querySelector('.sw-media-embed-video__consent')).not.toBeNull();
        expect(wrapper.querySelector('iframe')).toBeNull();
    });

    test('sets the consent cookie and shows the iframe when the accept button is clicked', () => {
        const wrapper = addEmbed({ videoUrl: 'https://player.vimeo.com/video/7' });

        wrapper.querySelector('.sw-media-embed-video__accept').click();

        expect(document.cookie).toContain('vimeo-video=1');
        expect(wrapper.querySelector('iframe.sw-media-embed-video__video').getAttribute('src'))
            .toBe('https://player.vimeo.com/video/7');
        expect(wrapper.querySelector('.sw-media-embed-video__consent')).toBeNull();
    });

    test('accepting one embed replaces every same-platform embed with its own iframe, leaving others gated', () => {
        const vimeoA = addEmbed({ cookieName: 'vimeo-video', videoUrl: 'https://player.vimeo.com/video/a' });
        const vimeoB = addEmbed({ cookieName: 'vimeo-video', videoUrl: 'https://player.vimeo.com/video/b' });
        const youtube = addEmbed({ cookieName: 'youtube-video', videoUrl: 'https://www.youtube.com/embed/c' });

        // Accept the first Vimeo embed.
        vimeoA.querySelector('.sw-media-embed-video__accept').click();

        // Both Vimeo embeds are replaced, each with its OWN video url.
        expect(vimeoA.querySelector('iframe.sw-media-embed-video__video').getAttribute('src'))
            .toBe('https://player.vimeo.com/video/a');
        expect(vimeoB.querySelector('iframe.sw-media-embed-video__video').getAttribute('src'))
            .toBe('https://player.vimeo.com/video/b');

        // The YouTube embed uses a different cookie and stays gated.
        expect(youtube.querySelector('iframe')).toBeNull();
        expect(youtube.querySelector('.sw-media-embed-video__consent')).not.toBeNull();
    });

    test('shows the iframe on a cookie-configuration update once the cookie is present', () => {
        const wrapper = addEmbed();

        // Update fires but the cookie is not set yet -> stays gated.
        document.dispatchEvent(new CustomEvent('CookieConfiguration_Update'));
        expect(wrapper.querySelector('iframe')).toBeNull();

        document.cookie = 'vimeo-video=1;path=/';
        document.dispatchEvent(new CustomEvent('CookieConfiguration_Update'));
        expect(wrapper.querySelector('iframe.sw-media-embed-video__video')).not.toBeNull();
    });

    test('does not render an iframe when no videoUrl is configured', () => {
        const wrapper = addEmbed({ videoUrl: null });

        wrapper.querySelector('.sw-media-embed-video__accept').click();

        expect(wrapper.querySelector('iframe')).toBeNull();
    });

    test('stops reacting to consent events after destroy', () => {
        const wrapper = addEmbed();

        components[0].destroy();

        document.cookie = 'vimeo-video=1;path=/';
        document.dispatchEvent(new CustomEvent('CookieConfiguration_Update'));

        expect(wrapper.querySelector('iframe')).toBeNull();
    });
});
