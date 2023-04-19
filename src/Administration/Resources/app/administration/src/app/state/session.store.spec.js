/**
 * @package admin
 */

import Vuex from 'vuex';
import SessionStore from 'src/app/state/session.store';

describe('src/app/state/session.store.js', () => {
    let sessionStore = null;

    beforeEach(async () => {
        sessionStore = new Vuex.Store(SessionStore);
    });

    afterEach(() => {
        sessionStore.commit('removeCurrentUser');
    });

    it('returns all user privileges', async () => {
        sessionStore.commit('setCurrentUser', {
            aclRoles: [
                {
                    privileges: [
                        'system.core_update',
                        'system:core:update',
                        'system.clear_cache',
                        'system:clear:cache',
                    ],
                },
                {
                    privileges: [
                        'system.plugin_maintain',
                        'system:plugin:maintain',
                        'orders.create_discounts',
                        'order:create:discount',
                    ],
                },
            ],
        });

        expect(sessionStore.getters.userPrivileges).toContain('system.core_update');
        expect(sessionStore.getters.userPrivileges).toContain('system:core:update');
        expect(sessionStore.getters.userPrivileges).toContain('system.clear_cache');
        expect(sessionStore.getters.userPrivileges).toContain('system:clear:cache');
        expect(sessionStore.getters.userPrivileges).toContain('system.plugin_maintain');
        expect(sessionStore.getters.userPrivileges).toContain('system:plugin:maintain');
        expect(sessionStore.getters.userPrivileges).toContain('orders.create_discounts');
        expect(sessionStore.getters.userPrivileges).toContain('order:create:discount');
    });

    it('returns an empty array if no user is set', async () => {
        expect(sessionStore.getters.userPrivileges).toEqual([]);
    });
});
