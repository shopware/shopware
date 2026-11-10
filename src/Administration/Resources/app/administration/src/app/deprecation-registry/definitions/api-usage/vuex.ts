/**
 * @sw-package framework
 */

import { apiUsageMigration, importSource, memberUsage, upgradeGuide } from '../helpers';

const MESSAGE =
    'Vuex is removed in 6.8. Use Pinia stores: Shopware.Store.get(id) and Shopware.Store.register({ id, state, getters, actions }).';

export default apiUsageMigration({
    id: 'api.vuex',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'Vuex, Shopware.State, the StateFactory and the Vuex component helpers are removed. All stores are Pinia stores.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-vuex-in-favor-of-pinia'),
    ],
    usage: [
        memberUsage({ object: 'Shopware', properties: ['State'], message: MESSAGE }),
        memberUsage({ object: 'this', properties: ['$store'], message: MESSAGE }),
        memberUsage({
            properties: [
                'mapVuexState',
                'mapVuexMutations',
                'mapVuexGetters',
                'mapVuexActions',
            ],
            message: `${MESSAGE} Map them with mapState and mapActions of the component helper.`,
        }),
        importSource({ sources: ['vuex'], message: MESSAGE }),
        importSource({
            sources: ['src/core/factory/state.factory'],
            message: `${MESSAGE} The StateFactory is removed without replacement.`,
        }),
    ],
});
