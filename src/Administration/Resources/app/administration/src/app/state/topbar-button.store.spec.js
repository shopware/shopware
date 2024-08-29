/**
 * @package customer-order
 * @group disabledCompat
 */
import topBarButtonState from './topbar-button.store';

describe('topbar-button.store', () => {
    Shopware.Store.register(topBarButtonState);
    let store;

    beforeEach(() => {
        store = Shopware.Store.get('topBarButtonState');
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
