import CookieStorageHelper from 'src/helper/storage/cookie-storage.helper';
import CmsGdprVideoElement, { CMS_GDPR_VIDEO_ELEMENT_REPLACE_ELEMENT_WITH_VIDEO } from 'src/plugin/cms-gdpr-video-element/cms-gdpr-video-element.plugin';
import { COOKIE_CONFIGURATION_CLOSE_OFF_CANVAS } from 'src/plugin/cookie/cookie-configuration.plugin';

/**
 * @package services-settings
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
        expect(CookieStorageHelper.getItem(cmsGdprVideoElement.options.cookieName)).toBe('1');
        expect(_replaceElementWithVideo).toHaveBeenCalled();
    });

    test('should replace elements with the video when the accept button clicked', () => {
        document.$emitter.publish = jest.fn();

        cmsGdprVideoElement.onReplaceElementWithVideo({ preventDefault: jest.fn() });

        expect(CookieStorageHelper.getItem(cmsGdprVideoElement.options.cookieName)).toBe('1');
        expect(document.$emitter.publish).toHaveBeenCalledWith(CMS_GDPR_VIDEO_ELEMENT_REPLACE_ELEMENT_WITH_VIDEO);
    });
});
