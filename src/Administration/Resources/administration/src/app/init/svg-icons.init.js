import { Component } from 'src/core/shopware';
import iconComponents from 'src/app/assets/icons/icons';

export default function initializeSvgIcons() {
    iconComponents.forEach((component) => {
        Component.register(component.name, component);
    });
}
