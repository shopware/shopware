/**
 * @deprecated tag:v6.5.0 - Will no longer return legacy icons.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default (() => {
    const iconKitContext = require.context('@shopware-ag/meteor-icon-kit/icons', true, /svg$/);

    const iconKit = iconKitContext.keys().reduce((accumulator, item) => {
        const componentNameParts = item.split('.')[1].split('/');
        componentNameParts.shift();
        const componentName = componentNameParts.join('-');

        const component = {
            name: `icons-${componentName}`,
            functional: true,
            render(createElement, elementContext) {
                const data = elementContext.data;

                return createElement('span', {
                    class: [data.staticClass, data.class],
                    style: data.style,
                    attrs: data.attrs,
                    on: data.on,
                    domProps: {
                        innerHTML: iconKitContext(item),
                    },
                });
            },
        };

        accumulator.push(component);
        return accumulator;
    }, []);

    const legacyIcons = require.context('./svg', true, /svg$/);

    return legacyIcons.keys().reduce((accumulator, item) => {
        const componentName = item.split('.')[1].split('/')[1];

        const component = {
            name: componentName,
            functional: true,
            render(createElement, elementContext) {
                const data = elementContext.data;

                return createElement('span', {
                    class: [data.staticClass, data.class],
                    style: data.style,
                    attrs: data.attrs,
                    on: data.on,
                    domProps: {
                        innerHTML: legacyIcons(item),
                    },
                });
            },
        };

        accumulator.legacy.push(component);
        return accumulator;
    }, {
        legacy: [],
        iconKit,
    });
})();
