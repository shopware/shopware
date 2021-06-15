export default (() => {
    const context = require.context('./svg', true, /svg$/);

    return context.keys().reduce((accumulator, item) => {
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
                        innerHTML: context(item),
                    },
                });
            },
        };

        accumulator.push(component);
        return accumulator;
    }, []);
})();
