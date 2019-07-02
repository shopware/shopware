import { mapState, mapMutations, mapGetters, mapActions } from 'vuex';
import { mapApiErrors, mapPageErrors } from 'src/app/service/map-errors.service';
import { Component } from 'src/core/shopware';
import requireComponents from 'src/app/component/components';

export default function initializeBaseComponents() {
    Object.assign(
        Component.getComponentHelper(),
        {
            mapState,
            mapMutations,
            mapGetters,
            mapActions,
            mapApiErrors,
            mapPageErrors
        }
    );

    const components = requireComponents().filter((item) => {
        return item !== undefined;
    });

    components.forEach((component) => {
        const isExtendedComponent = (component.extendsFrom && component.extendsFrom.length);

        if (isExtendedComponent) {
            Component.extend(component.name, component.extendsFrom, component);
            return;
        }

        Component.register(component.name, component);
    });
}
