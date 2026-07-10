/**
 * @package discovery
 */
describe('AgeVerificationPlugin tests', () => {
    let AgeVerificationPlugin;
    let element;
    const showMock = jest.fn();
    const hideMock = jest.fn();

    function createElement() {
        document.body.innerHTML = `
            <div class="age-verification-modal" data-age-verification="true">
                <button class="js-age-verification-confirm">Confirm</button>
                <button class="js-age-verification-decline">Decline</button>
            </div>
        `;

        return document.querySelector('.age-verification-modal');
    }

    function clearCookies() {
        document.cookie.split(';').forEach((cookie) => {
            const name = cookie.split('=')[0].trim();
            document.cookie = `${name}=;expires=${new Date(0).toUTCString()};path=/`;
        });
    }

    beforeEach(() => {
        jest.resetModules();
        clearCookies();

        showMock.mockClear();
        hideMock.mockClear();

        window.bootstrap = {
            Modal: jest.fn().mockImplementation(() => ({
                show: showMock,
                hide: hideMock,
            })),
        };

        element = createElement();

        AgeVerificationPlugin = require('src/plugin/age-verification/age-verification.plugin').default;
    });

    it('opens the modal when the confirmation cookie is absent', () => {
        new AgeVerificationPlugin(element);

        expect(window.bootstrap.Modal).toHaveBeenCalledWith(element, {
            backdrop: 'static',
            keyboard: false,
        });
        expect(showMock).toHaveBeenCalledTimes(1);
    });

    it('does not open the modal when the confirmation cookie is set', () => {
        document.cookie = 'age-verified=1;path=/';

        new AgeVerificationPlugin(element);

        expect(window.bootstrap.Modal).not.toHaveBeenCalled();
        expect(showMock).not.toHaveBeenCalled();
    });

    it('stores the cookie and hides the modal on confirm', () => {
        new AgeVerificationPlugin(element, { cookieLifetime: 30 });

        element.querySelector('.js-age-verification-confirm').click();

        expect(document.cookie).toContain('age-verified=1');
        expect(hideMock).toHaveBeenCalledTimes(1);
    });

    it('redirects to the configured decline url on decline', () => {
        const redirectSpy = jest.spyOn(AgeVerificationPlugin.prototype, '_redirect').mockImplementation(() => {});

        new AgeVerificationPlugin(element, { declineUrl: 'https://example.com/too-young' });

        element.querySelector('.js-age-verification-decline').click();

        expect(redirectSpy).toHaveBeenCalledWith('https://example.com/too-young');

        redirectSpy.mockRestore();
    });

    it('navigates back on decline when no decline url is configured', () => {
        window.history.back = jest.fn();

        new AgeVerificationPlugin(element, { declineUrl: '' });

        element.querySelector('.js-age-verification-decline').click();

        expect(window.history.back).toHaveBeenCalledTimes(1);
    });

    it('opens only a single modal even with multiple elements on the page', () => {
        document.body.innerHTML = `
            <div class="age-verification-modal age-verification-modal--first" data-age-verification="true"></div>
            <div class="age-verification-modal age-verification-modal--second" data-age-verification="true"></div>
        `;

        new AgeVerificationPlugin(document.querySelector('.age-verification-modal--first'));
        new AgeVerificationPlugin(document.querySelector('.age-verification-modal--second'));

        expect(window.bootstrap.Modal).toHaveBeenCalledTimes(1);
    });
});
