import iconComponents from 'src/app/assets/icons/icons';

const { Component } = Shopware;

export default function initializeSvgIcons() {
    iconComponents.forEach((component) => {
        Component.register(component.name, component);
    });
}
