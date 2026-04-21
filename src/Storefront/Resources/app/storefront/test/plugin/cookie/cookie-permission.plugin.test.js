import CookieStorage from 'src/helper/storage/cookie-storage.helper';
import CookiePermissionPlugin from 'src/plugin/cookie/cookie-permission.plugin';

/**
 * @package framework
 */
describe('CookiePermissionPlugin tests', () => {
    let cookieBarElement;

    beforeEach(() => {
        window.focusHandler = {
            setFocus: jest.fn(),
        };

        document.body.innerHTML = `
            <div class="cookie-permission-container" style="display: none;">
                <div class="cookie-permission-content">
                    <p>This website uses cookies. <a href="https://shop.example.com/data-privacy">More information...</a></p>
                    <button class="js-cookie-permission-button">Accept</button>
                </div>
            </div>
        `;

        cookieBarElement = document.querySelector('.cookie-permission-container');

        jest.spyOn(CookieStorage, 'getItem').mockReturnValue(null);
        jest.spyOn(CookieStorage, 'setItem').mockImplementation(() => {});
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        jest.clearAllMocks();
    });

    test('sets focus on cookie bar when autoFocus is true', () => {
        new CookiePermissionPlugin(cookieBarElement, { autoFocus: true });

        expect(window.focusHandler.setFocus).toHaveBeenCalledWith(cookieBarElement, { preventScroll: true });
    });

    test('does not set focus on cookie bar when autoFocus is false', () => {
        new CookiePermissionPlugin(cookieBarElement, { autoFocus: false });

        expect(window.focusHandler.setFocus).not.toHaveBeenCalled();
    });

    test('does not set focus on cookie bar when the cookie preference is already set', () => {
        CookieStorage.getItem.mockReturnValue('1');

        new CookiePermissionPlugin(cookieBarElement, { autoFocus: true });

        expect(window.focusHandler.setFocus).not.toHaveBeenCalled();
    });

    test('does not set focus on cookie bar when data privacy page is visited', () => {
        jest.spyOn(CookiePermissionPlugin.prototype, '_getCurrentLocation').mockReturnValue('https://shop.example.com/data-privacy');

        new CookiePermissionPlugin(cookieBarElement, { autoFocus: true });

        expect(window.focusHandler.setFocus).not.toHaveBeenCalled();
    });

    test('sets focus on cookie bar when privacy link cannot be found in cookie bar content', () => {
        document.body.innerHTML = `
            <div class="cookie-permission-container" style="display: none;">
                <div class="cookie-permission-content">
                    <p>This website uses cookies.</p>
                    <button class="js-cookie-permission-button">Accept</button>
                </div>
            </div>
        `;
        const barWithoutLink = document.querySelector('.cookie-permission-container');

        jest.spyOn(CookiePermissionPlugin.prototype, '_getCurrentLocation').mockReturnValue('https://shop.example.com/data-privacy');

        new CookiePermissionPlugin(barWithoutLink, { autoFocus: true });

        expect(window.focusHandler.setFocus).toHaveBeenCalledWith(barWithoutLink, { preventScroll: true });
    });
});
