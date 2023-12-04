/**
 * @package admin
 */

// Vue 2 imports
import { mapState, mapMutations, mapGetters, mapActions } from 'vuex_v2';

// Vue 3 imports
import {
    mapState as mapStateV3,
    mapMutations as mapMutationsV3,
    mapGetters as mapGettersV3,
    mapActions as mapActionsV3,
} from 'vuex';

import * as mapErrors from 'src/app/service/map-errors.service';

const componentHelper = {
    mapState: window._features_.vue3 ? mapStateV3 : mapState,
    mapMutations: window._features_.vue3 ? mapMutationsV3 : mapMutations,
    mapGetters: window._features_.vue3 ? mapGettersV3 : mapGetters,
    mapActions: window._features_.vue3 ? mapActionsV3 : mapActions,
    ...mapErrors,
};

// Register each component helper
Object.entries(componentHelper).forEach(([name, value]) => {
    Shopware.Component.registerComponentHelper(name, value);
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeComponentHelper() {
    return Shopware.Component.getComponentHelper();
}
