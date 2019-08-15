import baseComponents from 'src/app/component/components';

const { Component } = Shopware;
const { warn } = Shopware.Utils.debug;

export default function initializeBaseComponents() {
    const components = baseComponents.filter((item) => {
        return item !== undefined;
    });

    components.forEach((component) => {
        if (component.extendsFrom && typeof component.extendsFrom !== 'string') {
            warn(
                'component.init',
                'extendsFrom always must be a string.',
                component
            );
            return;
        }

        const isExtendedComponent = (component.extendsFrom && component.extendsFrom.length);

        if (isExtendedComponent) {
            Component.extend(component.name, component.extendsFrom, component);
            return;
        }

        Component.register(component.name, component);
    });
}
