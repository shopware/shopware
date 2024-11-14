describe('storage.helper.js', () => {
    beforeEach(() => {
        jest.resetModules();
    });

    test('returns local storage instance', () => {
        const storage = require('src/helper/storage/storage.helper').default;

        expect(storage === window.localStorage).toBeTruthy();
        expect(storage).toStrictEqual(window.localStorage);
    });

    test('it returns session storage if local storage is not available', () => {
        const localStorage = window.localStorage;
        delete window.localStorage;

        const storage = require('src/helper/storage/storage.helper').default;

        expect(storage === window.sessionStorage).toBeTruthy();
        expect(storage).toStrictEqual(window.sessionStorage);

        window.localStorage = localStorage;
    });

    test('it returns cookie storage if session is not available', () => {
        Storage.prototype.setItem = jest.fn(() => { throw new Error(); });

        const CookieStorageHelper = require('src/helper/storage/cookie-storage.helper').default;
        const storage = require('src/helper/storage/storage.helper').default;

        expect(storage.toString()).toBe(CookieStorageHelper.toString());
    });

    test('it returns memory storage if cookie is not supported', () => {
        Storage.prototype.setItem = jest.fn(() => { throw new Error(); });
        mockCookieStorage(jest);

        const storage = require('src/helper/storage/storage.helper').default;
        const MemoryStorage = require('src/helper/storage/memory-storage.helper').default;

        expect(Object.getPrototypeOf(storage)).toEqual(Object.getPrototypeOf(new MemoryStorage()));
    });

    test('it throws if setItem is not supported', () => {
        Storage.prototype.setItem = jest.fn(() => { throw new Error(); });
        mockCookieStorage(jest);

        jest.mock('src/helper/storage/memory-storage.helper', () => {
            return function () {
                return {};
            };
        });

        expect(
            () => require('src/helper/storage/storage.helper')
        ).toThrowError('The storage must have a "setItem" function');
    });

    test('it throws if getItem is not supported', () => {
        Storage.prototype.setItem = jest.fn(() => { throw new Error(); });
        mockCookieStorage(jest);

        jest.mock('src/helper/storage/memory-storage.helper', () => {
            return function () {
                return {
                    setItem: () => { return {} },
                };
            };
        });

        expect(
            () => require('src/helper/storage/storage.helper')
        ).toThrowError('The storage must have a "getItem" function');
    });

    test('it throws if removeItem is not supported', () => {
        Storage.prototype.setItem = jest.fn(() => { throw new Error(); });
        mockCookieStorage(jest);

        jest.mock('src/helper/storage/memory-storage.helper', () => {
            return function () {
                return {
                    setItem: () => {},
                    getItem: () => { return {} },
                };
            };
        });

        expect(
            () => require('src/helper/storage/storage.helper')
        ).toThrowError('The storage must have a "removeItem" function');
    });

    test('it throws if key is not supported', () => {
        Storage.prototype.setItem = jest.fn(() => { throw new Error(); });
        mockCookieStorage(jest);

        jest.mock('src/helper/storage/memory-storage.helper', () => {
            return function () {
                return {
                    setItem: () => {},
                    getItem: () => { return {} },
                    removeItem: () => {},
                };
            };
        });

        expect(
            () => require('src/helper/storage/storage.helper')
        ).toThrowError('The storage must have a "key" function');
    });

    test('it throws if clear is not supported', () => {
        Storage.prototype.setItem = jest.fn(() => { throw new Error(); });
        mockCookieStorage(jest);

        jest.mock('src/helper/storage/memory-storage.helper', () => {
            return function () {
                return {
                    setItem: () => {},
                    getItem: () => { return {} },
                    removeItem: () => {},
                    key: () => { return {} },
                };
            };
        });

        expect(
            () => require('src/helper/storage/storage.helper')
        ).toThrowError('The storage must have a "clear" function');
    });
});

function mockCookieStorage(jestInstance) {
    jestInstance.mock('src/helper/storage/cookie-storage.helper', () => {
        return {
            isSupported() {
                return false;
            },
        };
    });
}
