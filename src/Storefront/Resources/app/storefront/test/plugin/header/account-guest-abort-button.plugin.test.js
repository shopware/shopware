import AccountGuestAbortButtonPlugin from 'src/plugin/header/account-guest-abort-button.plugin';

describe('AccountGuestAbortButtonPlugin tests', () => {
    let accouctGuestAbortButton = undefined;
    let spyInitializePlugins = jest.fn();

    beforeEach(() => {
        // mock search plugin
        const mockElement = document.createElement('a');
        mockElement.href = '/account/logout';

        window.PluginManager.getPluginInstanceFromElement = () => {
            return new AccountGuestAbortButtonPlugin(mockElement);
        };

        accouctGuestAbortButton = new AccountGuestAbortButtonPlugin(mockElement);
    });

    afterEach(() => {
        accouctGuestAbortButton = undefined;
        spyInitializePlugins.mockClear();
    });

    test('AccountGuestAbortButtonPlugin plugin exists', () => {
        expect(typeof accouctGuestAbortButton).toBe('object');
    });

    test('AccountGuestAbortButtonPlugin should emitter guest-logout event when clicked', () => {
        accouctGuestAbortButton._onButtonClicked = jest.fn();

        let logoutEventPublished = false
        accouctGuestAbortButton.$emitter.subscribe('guest-logout', () => {
            logoutEventPublished = true;
        });

        window.location.assign = jest.fn();
        accouctGuestAbortButton.el.click();

        expect(logoutEventPublished).toEqual(true);

        expect(window.location.assign).toBeCalledWith(accouctGuestAbortButton.el.getAttribute('href'));
        window.location.assign.mockClear();
    });
});


