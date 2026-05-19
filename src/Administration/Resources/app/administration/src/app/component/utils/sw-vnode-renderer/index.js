/**
 * @sw-package framework
 * @private
 */
export default {
    ...(() => {
        return {};
    })(),

    render(firstArgument, secondArgument) {
        const h = firstArgument;

        // Vue2 syntax
        if (typeof h === 'function') {
            const context = secondArgument;

            return context.props.node;
        }

        // Vue3 syntax
        return this.node;
    },

    props: {
        node: {
            type: Object,
            required: true,
        },
    },
};
