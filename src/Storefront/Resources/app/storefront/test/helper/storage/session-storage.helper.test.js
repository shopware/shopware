describe('session-storage.helper.js', () => {
    const originalSetItem = Storage.prototype.setItem;

    beforeEach(() => {
        jest.resetModules();
    });

    afterEach(() => {
        Storage.prototype.setItem = originalSetItem;
    });

    test('returns the session storage instance', () => {
        const storage = require('src/helper/storage/session-storage.helper').default;

        expect(storage === window.sessionStorage).toBeTruthy();
        expect(storage).toStrictEqual(window.sessionStorage);
    });

    test('it returns the memory storage if the session storage is not available', () => {
        Storage.prototype.setItem = jest.fn(() => { throw new Error(); });

        const storage = require('src/helper/storage/session-storage.helper').default;
        const MemoryStorage = require('src/helper/storage/memory-storage.helper').default;

        expect(Object.getPrototypeOf(storage)).toEqual(Object.getPrototypeOf(new MemoryStorage()));
    });

    test('it does not leave the probe key behind', () => {
        require('src/helper/storage/session-storage.helper');

        expect(window.sessionStorage.getItem('__session_storage_test')).toBeNull();
    });
});
