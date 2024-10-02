/**
 * @package customer-order
 */

describe('topbar-button.store', () => {
    let store;

    beforeEach(() => {
        store = Shopware.Store.get('topBarButton');
    });

    afterEach(() => {
        store.buttons = [];
    });

    it('has initial state', () => {
        expect(store.buttons).toStrictEqual([]);
    });

    it('can update buttons', () => {
        store.buttons.push({
            label: 'Test action',
            icon: 'solid-rocket',
            callback: () => {},
        });

        expect(JSON.stringify(store.buttons)).toBe(JSON.stringify([{
            label: 'Test action',
            icon: 'solid-rocket',
            callback: () => {},
        }]));
    });
});
