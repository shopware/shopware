import iconComponents from 'src/app/assets/icons/icons';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeSvgIcons() {
    return iconComponents.map((component) => {
        return Component.register(component.name, component);
    });
}
