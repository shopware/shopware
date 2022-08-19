import iconComponents from 'src/app/assets/icons/icons';

const { Component } = Shopware;

/**
 * @deprecated tag:v6.5.0 - Will no longer return legacy icons.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeSvgIcons() {
    return [...iconComponents.legacy, ...iconComponents.iconKit].map((component) => {
        return Component.register(component.name, component);
    });
}
