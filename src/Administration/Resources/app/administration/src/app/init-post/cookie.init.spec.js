import initializeCookies from 'src/app/init-post/cookie.init';
import { CookieStorage } from 'cookie-storage';

jest.mock('cookie-storage');

describe('src/app/init-post/cookie.init.ts', () => {
    const logoutListeners = [];

    beforeAll(() => {
        Shopware.Service().register('loginService', () => {
            return {
                addOnLogoutListener: jest.fn((cb) => {
                    logoutListeners.push(cb);
                }),
                getStorage: jest.fn(() => ({
                    getItem: jest.fn(() => {
                        return 'storage-test-key';
                    }),
                })),
                getStorageKey: jest.fn(() => {
                    return 'storage-test-key';
                }),
            };
        });
    });

    beforeEach(() => {
        if (window?.window.mock) {
            window.mockRestore();
        }
    });

    it('should initialize the logout listener', () => {
        initializeCookies();

        const addOnLogoutListener = Shopware.Service('loginService').addOnLogoutListener;

        expect(addOnLogoutListener).toHaveBeenCalledWith(expect.any(Function));
    });

    it('should execute the callback on logout in window env', () => {
        expect(logoutListeners).toHaveLength(1);

        // simulate logout
        logoutListeners[0]();

        expect(CookieStorage).toHaveBeenCalledWith({
            domain: 'localhost',
            secure: false,
            path: 0,
            sameSite: 'Strict',
        });
    });

    it('should execute the callback on logout in non-window env', () => {
        // make window undefined
        jest.spyOn(window, 'window', 'get').mockImplementation(() => undefined);

        // simulate logout
        logoutListeners[0]();

        expect(CookieStorage).toHaveBeenCalledWith({
            domain: 'localhost',
            secure: false,
            path: 0,
            sameSite: 'Strict',
        });
    });
});
