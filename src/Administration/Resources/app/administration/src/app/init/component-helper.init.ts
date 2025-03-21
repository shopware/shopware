/**
 * @sw-package framework
 */

// Vue 3 imports
import {
    mapState as mapVuexState,
    mapMutations as mapVuexMutations,
    mapGetters as mapVuexGetters,
    mapActions as mapVuexActions,
} from 'vuex';
import { mapState, mapActions } from 'pinia';

import * as mapErrors from 'src/app/service/map-errors.service';

const componentHelper: ComponentHelper = {
    mapState,
    mapActions,
    mapVuexState,
    mapVuexMutations,
    mapVuexGetters,
    mapVuexActions,
    ...mapErrors,
};

// Register each component helper
(Object.entries(componentHelper) as [keyof ComponentHelper, ComponentHelper[keyof ComponentHelper]][]).forEach(
    ([
        name,
        value,
    ]) => {
        Shopware.Component.registerComponentHelper(name, value);
    },
);

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeComponentHelper() {
    return Shopware.Component.getComponentHelper();
}
