import CookieStorageHelper from 'src/helper/storage/cookie-storage.helper';
import CmsGdprVideoElement, { CMS_GDPR_VIDEO_ELEMENT_REPLACE_ELEMENT_WITH_VIDEO } from 'src/plugin/cms-gdpr-video-element/cms-gdpr-video-element.plugin';
import { COOKIE_CONFIGURATION_CLOSE_OFF_CANVAS, COOKIE_CONFIGURATION_UPDATE } from 'src/plugin/cookie/cookie-configuration.plugin';

/**
 * @sw-package discovery
 */
describe('src/plugin/cms-gdpr-video-element/cms-gdpr-video-element.plugin', () => {
    let cmsGdprVideoElement;

    const template = `
        <div class="cms-element">
            <button class="cms-element__accept-cookie">Accept</button>
        <div>
    `;

    function initPlugin(options = {}) {
        return new CmsGdprVideoElement(document.querySelector('.cms-element'), options);
    }

    beforeEach(() => {
        document.body.innerHTML = template;
        document.$emitter.subscribe = jest.fn();

        cmsGdprVideoElement = initPlugin();
    });

    afterEach(() => {
        jest.clearAllMocks();
        CookieStorageHelper.removeItem(cmsGdprVideoElement.options.cookieName);

        cmsGdprVideoElement = undefined;
    });

    test('is registered correctly', () => {
        expect(typeof cmsGdprVideoElement).toBe('object');
        expect(cmsGdprVideoElement).toBeInstanceOf(CmsGdprVideoElement);
    });

    test('should replace elements with the video when the plugin created', () => {
        const _replaceElementWithVideo = jest.spyOn(cmsGdprVideoElement, '_replaceElementWithVideo');
        CookieStorageHelper.setItem(cmsGdprVideoElement.options.cookieName, '1', '30');

        cmsGdprVideoElement.init();

        expect(document.$emitter.subscribe).toHaveBeenCalledWith(COOKIE_CONFIGURATION_CLOSE_OFF_CANVAS, expect.any(Function));
        expect(document.$emitter.subscribe).toHaveBeenCalledWith(COOKIE_CONFIGURATION_UPDATE, expect.any(Function));
        expect(CookieStorageHelper.getItem(cmsGdprVideoElement.options.cookieName)).toBe('1');
        expect(_replaceElementWithVideo).toHaveBeenCalled();
    });

    test('should replace elements with the video when the accept button clicked', () => {
        document.$emitter.publish = jest.fn();

        cmsGdprVideoElement.onReplaceElementWithVideo({ preventDefault: jest.fn() });

        expect(CookieStorageHelper.getItem(cmsGdprVideoElement.options.cookieName)).toBe('1');
        expect(document.$emitter.publish).toHaveBeenCalledWith(CMS_GDPR_VIDEO_ELEMENT_REPLACE_ELEMENT_WITH_VIDEO);
    });

    test('should set allowfullscreen attribute on iframe', () => {
        const videoUrl = 'https://www.youtube.com/embed/test';
        const iframeTitle = 'Test Video';
        const options = { videoUrl, iframeTitle, iframeClasses: [] };

        cmsGdprVideoElement = initPlugin(options);
        CookieStorageHelper.setItem(cmsGdprVideoElement.options.cookieName, '1', '30');
        cmsGdprVideoElement._replaceElementWithVideo();

        const iframe = document.querySelector('iframe');
        expect(iframe).not.toBeNull();
        expect(iframe.getAttribute('allowfullscreen')).toBe('allowfullscreen');
    });

    test('should not replace video when cookie is not set', () => {
        const videoUrl = 'https://www.youtube.com/embed/test';
        const iframeTitle = 'Test Video';
        const options = { videoUrl, iframeTitle, iframeClasses: [] };

        cmsGdprVideoElement = initPlugin(options);
        const result = cmsGdprVideoElement._replaceElementWithVideo();

        expect(result).toBe(false);
        expect(document.querySelector('iframe')).toBeNull();
    });

    test('should only replace video when correct cookie is set', () => {
        const videoUrl = 'https://www.vimeo.com/embed/test';
        const iframeTitle = 'Vimeo Video';
        const options = { cookieName: 'vimeo-video', videoUrl, iframeTitle, iframeClasses: [] };

        cmsGdprVideoElement = initPlugin(options);

        // Set youtube cookie instead of vimeo
        CookieStorageHelper.setItem('youtube-video', '1', '30');
        let result = cmsGdprVideoElement._replaceElementWithVideo();

        // Should not replace with wrong cookie
        expect(result).toBe(false);
        expect(document.querySelector('iframe')).toBeNull();

        // Set correct vimeo cookie
        CookieStorageHelper.setItem('vimeo-video', '1', '30');
        result = cmsGdprVideoElement._replaceElementWithVideo();

        // Should replace with correct cookie
        expect(result).toBe(true);
        expect(document.querySelector('iframe')).not.toBeNull();
    });
});
