import iconComponents from 'src/app/assets/icons/icons';

const { Component } = Shopware;

export default function initializeSvgIcons() {
    return iconComponents.map((component) => {
        return Component.register(component.name, component);
    });
}
