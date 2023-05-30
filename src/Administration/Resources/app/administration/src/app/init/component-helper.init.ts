/**
 * @package admin
 */

import { mapState, mapMutations, mapGetters, mapActions } from 'vuex';
import * as mapErrors from 'src/app/service/map-errors.service';

const componentHelper = {
    mapState,
    mapMutations,
    mapGetters,
    mapActions,
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
