/**
 * @package buyers-experience
 */
import Vuex from 'vuex';
import adminHelpCenterStore from './admin-help-center.store';

describe('src/app/state/admin-help-center.store', () => {
    let store;

    beforeEach(() => {
        store = new Vuex.Store(adminHelpCenterStore);
    });

    afterEach(() => {
        store.state.showHelpSidebar = false;
        store.state.showShortcutModal = false;
    });

    it('should initialize state correctly', () => {
        expect(store.state.showHelpSidebar).toBe(false);
        expect(store.state.showShortcutModal).toBe(false);
    });

    it('should be able to change state', () => {
        store.commit('setShowHelpSidebar', true);
        store.commit('setShowShortcutModal', true);

        expect(store.state.showHelpSidebar).toBe(true);
        expect(store.state.showShortcutModal).toBe(true);
    });
});
