import AccountGuestAbortButtonPlugin from 'src/plugin/header/account-guest-abort-button.plugin';

describe('AccountGuestAbortButtonPlugin tests', () => {
    let accountGuestAbortButton = undefined;
    const spyInitializePlugins = jest.fn();

    beforeEach(() => {
        // mock search plugin
        const mockElement = document.createElement('a');
        mockElement.href = '/account/logout';

        window.PluginManager.getPluginInstanceFromElement = () => {
            return new AccountGuestAbortButtonPlugin(mockElement);
        };

        accountGuestAbortButton = new AccountGuestAbortButtonPlugin(mockElement);
    });

    afterEach(() => {
        accountGuestAbortButton = undefined;
        spyInitializePlugins.mockClear();
    });

    test('AccountGuestAbortButtonPlugin plugin exists', () => {
        expect(typeof accountGuestAbortButton).toBe('object');
    });

    test('AccountGuestAbortButtonPlugin should emitter guest-logout event when clicked', () => {
        accountGuestAbortButton._onButtonClicked = jest.fn();

        let logoutEventPublished = false;
        accountGuestAbortButton.$emitter.subscribe('guest-logout', () => {
            logoutEventPublished = true;
        });

        // Mock window.location
        const originalLocation = window.location;
        delete window.location;
        window.location = { assign: jest.fn() };

        accountGuestAbortButton.el.click();

        expect(logoutEventPublished).toEqual(true);
        expect(window.location.assign).toBeCalledWith(accountGuestAbortButton.el.getAttribute('href'));

        // Restore original window.location
        window.location = originalLocation;
    });
});
