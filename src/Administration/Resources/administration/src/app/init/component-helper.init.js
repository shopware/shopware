import { mapState, mapMutations, mapGetters, mapActions } from 'vuex';
import { mapApiErrors, mapPageErrors } from 'src/app/service/map-errors.service';

const componentHelper = {
    mapState,
    mapMutations,
    mapGetters,
    mapActions,
    mapApiErrors,
    mapPageErrors
};

// Register each component helper
Object.entries(componentHelper).forEach(([name, value]) => {
    Shopware.Component.registerComponentHelper(name, value);
});

export default function initializeComponentHelper() {
    return Shopware.Component.getComponentHelper();
}
