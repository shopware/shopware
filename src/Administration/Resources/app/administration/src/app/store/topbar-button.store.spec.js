/**
 * @sw-package innovation
 */

describe('topbar-button.store', () => {
    let store;

    beforeEach(() => {
        // Set extension context to null (core context) for testing
        Shopware.Store.get('extensionContext')._setCurrentExtensionContext(null);
        store = Shopware.Store.get('topBarButton');
    });

    afterEach(() => {
        store.reset();
    });

    it('has initial state', () => {
        expect(store.buttons).toStrictEqual([]);
    });

    it('can update buttons', () => {
        store.addButton({
            label: 'Test action',
            icon: 'solid-rocket',
            callback: () => {},
        });

        expect(JSON.stringify(store.buttons)).toBe(
            JSON.stringify([
                {
                    label: 'Test action',
                    icon: 'solid-rocket',
                    callback: () => {},
                },
            ]),
        );
    });
});
