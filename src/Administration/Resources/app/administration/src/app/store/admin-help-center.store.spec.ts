// eslint-disable-next-line filename-rules/match
import { createPinia, setActivePinia } from 'pinia';
import type { AdminHelpCenterStore } from './admin-help-center.store';

describe('admin-help-center', () => {
    let store: AdminHelpCenterStore;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = Shopware.Store.get('adminHelpCenter');
    });

    it('has initial state', () => {
        expect(store.showHelpSidebar).toBe(false);
        expect(store.showShortcutModal).toBe(false);
    });
});
