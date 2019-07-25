import { Component } from 'src/core/shopware';
import baseComponents from 'src/app/component/components';

export default function initializeBaseComponents() {
    const components = baseComponents.filter((item) => {
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
