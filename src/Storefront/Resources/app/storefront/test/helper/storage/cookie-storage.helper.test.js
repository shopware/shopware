import CookieStorageHelper from 'src/helper/storage/cookie-storage.helper';
import clock from 'jest-plugin-clock';

describe('cookie-storage.helper.js', () => {
    test('can create cookies', () => {
        expect(CookieStorageHelper.isSupported()).toBeTruthy();
    });

    test('can add and override cookies', () => {
        const mockDate = new Date(2019, 12, 18, 0, 0,0);
        const expirationDate = new Date(2019, 12, 23, 0, 0,0);

        clock.set(mockDate);

        CookieStorageHelper.setItem('jest-test-cookie', 'test value', 5);
        CookieStorageHelper.setItem('another-cookie', 'test value');

        expect(document.cookie).toBe('jest-test-cookie=test value; another-cookie=test value');

        CookieStorageHelper.setItem('jest-test-cookie', 'reset', 5);
        expect(document.cookie).toBe('jest-test-cookie=reset; another-cookie=test value');
    });

    test('it throws if you omit the name', () => {
        expect(() => { CookieStorageHelper.setItem() }).toThrowError();
    });

    test('it returns cookie value', () => {
        const cookieValue = 'this is my val';

        CookieStorageHelper.setItem('jest-test-cookie', cookieValue);

        expect(CookieStorageHelper.getItem('jest-test-cookie')).toEqual(cookieValue);
    });
    
    test('returns false if a cookie is not set', () => {
        expect(CookieStorageHelper.getItem('i-hope-this-cookie-is-not-set')).toStrictEqual(false);
    });

    test('returns false if no name is given', () => {
        const cookieName = 'undefined';

        CookieStorageHelper.setItem(cookieName, 5, 1);

        expect(CookieStorageHelper.getItem()).toStrictEqual(false);
    });

    test('it does not write cookies that were not set', () => {
        const cookieName = 'myCookie';

        CookieStorageHelper.removeItem(cookieName);
        expect(CookieStorageHelper.getItem(cookieName)).toStrictEqual(false);
    });

    test('it removes cookies that were set', () => {
        const cookieName = 'myCookie';

        CookieStorageHelper.setItem(cookieName, 5, 1);
        CookieStorageHelper.removeItem(cookieName);

        expect(CookieStorageHelper.getItem(cookieName)).toStrictEqual(false);
    });

    test('key returns an empty string', () => {
        expect(CookieStorageHelper.key()).toStrictEqual('');
        expect(CookieStorageHelper.key(1)).toStrictEqual('');
        expect(CookieStorageHelper.key(null)).toStrictEqual('');
        expect(CookieStorageHelper.key(-9000)).toStrictEqual('');
    });

    test('clear does nothing', () => {
        CookieStorageHelper.setItem('someCookie', 'value', 1);

        const cookies = document.cookie;
        CookieStorageHelper.clear()

        expect(document.cookie).toStrictEqual(cookies);
    })
});
