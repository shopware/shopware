/**
 * @sw-package discovery
 */

describe('src/init/notification.init.js', () => {
    beforeAll(() => {
        require('./notification.init');
    });

    it.each([
        [
            'sw-theme-manager.detail.asyncCompilation.started',
            'sw-theme-manager.detail.asyncCompilation.startedTitle',
        ],
        [
            'sw-theme-manager.detail.asyncCompilation.completed',
            'sw-theme-manager.detail.asyncCompilation.completedTitle',
        ],
    ])('adds the title snippet key for the "%s" message', (message, expectedTitle) => {
        const store = Shopware.Store.get('notification');

        const uuid = store.createNotification({ variant: 'info', message, growl: false });

        expect(store.notifications[uuid].title).toBe(expectedTitle);
        expect(store.notifications[uuid].message).toBe(message);
    });

    it('keeps unrelated notifications untouched', () => {
        const store = Shopware.Store.get('notification');

        const uuid = store.createNotification({ variant: 'info', message: 'some.other.message', growl: false });

        expect(store.notifications[uuid].title).toBeUndefined();
    });
});