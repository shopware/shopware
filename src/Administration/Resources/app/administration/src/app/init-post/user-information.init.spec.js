/**
 * @sw-package framework
 */
import { nextTick } from 'vue';
import initializeUserContext from 'src/app/init-post/user-information.init';
import { initializeUserNotifications } from 'src/app/store/notification.store';
import useTheme, { USER_THEME_CONFIG_KEY } from 'src/app/composables/use-theme';

jest.mock('src/app/store/notification.store', () => ({
    initializeUserNotifications: jest.fn(),
}));

describe('src/app/init-post/user-information.init.ts', () => {
    let isLoggedIn = true;
    const logoutMock = jest.fn(() => true);
    const onLoginListeners = [];
    let userData = {
        data: {
            username: 'my-fancy-username',
            password: 'my-strong-password',
        },
    };

    beforeAll(() => {
        Shopware.Service().register('loginService', () => {
            return {
                isLoggedIn: () => isLoggedIn,
                logout: logoutMock,
                addOnLoginListener: (listener) => onLoginListeners.push(listener),
            };
        });

        Shopware.Service().register('userService', () => {
            return {
                getUser: () => Promise.resolve(userData),
            };
        });
    });

    beforeEach(() => {
        Shopware.Store.get('session').setCurrentUser(undefined);
        initializeUserNotifications.mockClear();
        logoutMock.mockClear();
        Shopware.Service('userConfigService').search.mockClear();
        onLoginListeners.length = 0;
        isLoggedIn = true;
        userData = {
            data: {
                username: 'my-fancy-username',
                password: 'my-strong-password',
            },
        };
    });

    afterEach(async () => {
        useTheme().setTheme('system');
        await nextTick();

        localStorage.removeItem('mt-theme');
    });

    it('should init the user context service correctly when user is logged in', async () => {
        expect(initializeUserNotifications).not.toHaveBeenCalled();
        expect(Shopware.Store.get('session').currentUser).toBeUndefined();

        await initializeUserContext();

        expect(initializeUserNotifications).toHaveBeenCalled();
        expect(Shopware.Store.get('session').currentUser).toEqual({
            username: 'my-fancy-username',
        });
    });

    it('should init the user context service correctly when user is not logged in', async () => {
        isLoggedIn = false;

        expect(initializeUserNotifications).not.toHaveBeenCalled();
        expect(logoutMock).not.toHaveBeenCalled();
        expect(Shopware.Store.get('session').currentUser).toBeUndefined();

        await initializeUserContext();

        expect(logoutMock).toHaveBeenCalled();
        expect(initializeUserNotifications).not.toHaveBeenCalled();
        expect(Shopware.Store.get('session').currentUser).toBeUndefined();
    });

    it('should call logout when user value is not correct', async () => {
        userData = {
            foo: 'not-working',
        };

        expect(initializeUserNotifications).not.toHaveBeenCalled();
        expect(logoutMock).not.toHaveBeenCalled();
        expect(Shopware.Store.get('session').currentUser).toBeUndefined();

        await initializeUserContext();

        expect(logoutMock).toHaveBeenCalled();
        expect(initializeUserNotifications).not.toHaveBeenCalled();
        expect(Shopware.Store.get('session').currentUser).toBeUndefined();
    });

    it('should apply the persisted theme preference when the user is logged in', async () => {
        Shopware.Service('userConfigService').search.mockResolvedValueOnce({
            data: {
                [USER_THEME_CONFIG_KEY]: { theme: 'dark' },
            },
        });

        await initializeUserContext();
        await flushPromises();

        expect(Shopware.Service('userConfigService').search).toHaveBeenCalledWith([
            USER_THEME_CONFIG_KEY,
        ]);
        expect(useTheme().theme.value).toBe('dark');
    });

    it('should keep the local theme preference when the user has not persisted one', async () => {
        useTheme().setTheme('light');

        await initializeUserContext();
        await flushPromises();

        expect(useTheme().theme.value).toBe('light');
    });

    it('should not load the theme preference when the user is not logged in', async () => {
        isLoggedIn = false;

        await initializeUserContext();
        await flushPromises();

        expect(Shopware.Service('userConfigService').search).not.toHaveBeenCalled();
    });

    it('should load the theme preference after a fresh login', async () => {
        isLoggedIn = false;

        await initializeUserContext();
        await flushPromises();

        expect(Shopware.Service('userConfigService').search).not.toHaveBeenCalled();
        expect(onLoginListeners).toHaveLength(1);

        Shopware.Service('userConfigService').search.mockResolvedValueOnce({
            data: {
                [USER_THEME_CONFIG_KEY]: { theme: 'dark' },
            },
        });

        onLoginListeners[0]();
        await flushPromises();

        expect(useTheme().theme.value).toBe('dark');
    });
});
