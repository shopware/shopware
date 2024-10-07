/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default () => {
    if (window._features_.ADMIN_VITE) {
        const context = import.meta.glob('./**/index!(*.spec).{j,t}s', {
            eager: false,
        });

        return Object.values(context);
    }

    const context = require.context('./', true, /(?<!components)\.(?<!spec\.)(?<!spec\.vue2\.)(js|ts)$/);
    return context.keys().reduce((accumulator, item) => {
        const service = context(item).default;

        accumulator.push(service);
        return accumulator;
    }, []);
};
