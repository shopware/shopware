// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default (() => {
    const iconKitContext = require.context('@shopware-ag/meteor-icon-kit/icons', true, /svg$/);

    return iconKitContext.keys().reduce((accumulator, item) => {
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
})();
