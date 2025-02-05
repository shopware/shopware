// eslint-disable-next-line filename-rules/match
import { createPinia, setActivePinia } from 'pinia';
import type { ActionButtonsStore } from './action-buttons.store';

const buttonTest = {
    label: 'Test',
    name: 'test',
    entity: 'product',
    view: 'detail',
    callback: () => {},
} as const;

describe('action-button.store', () => {
    let store: ActionButtonsStore;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = Shopware.Store.get('actionButtons');
    });

    it('has initial state', () => {
        expect(store.buttons).toStrictEqual([]);
    });

    it('adds a button', () => {
        store.add(buttonTest);
        expect(store.buttons).toStrictEqual([buttonTest]);
    });
});
