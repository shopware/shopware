/**
 * @sw-package framework
 */

import { importSource, jsApiMigration, memberUsage, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.vuex-state',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Vuex is removed. Use Pinia stores through Shopware.Store.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-vuex-in-favor-of-pinia')],
    usage: [
        memberUsage({ from: 'Shopware.State', to: 'Shopware.Store' }),
        memberUsage({ from: 'this.$store', to: "Shopware.Store.get('<id>')" }),
        memberUsage({ from: '*.mapVuexState', to: 'mapState() and mapActions() of the component helper' }),
        memberUsage({ from: '*.mapVuexMutations', to: 'mapState() and mapActions() of the component helper' }),
        memberUsage({ from: '*.mapVuexGetters', to: 'mapState() and mapActions() of the component helper' }),
        memberUsage({ from: '*.mapVuexActions', to: 'mapState() and mapActions() of the component helper' }),
        importSource({ from: 'vuex', to: 'pinia' }),
    ],
});
