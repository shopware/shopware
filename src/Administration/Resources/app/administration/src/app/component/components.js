/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default () => {
    const context = require.context('./', true, /(?<!components)\.(?<!spec\.)(?<!spec\.vue2\.)(js|ts)$/);
    return context.keys().reduce((accumulator, item) => {
        const service = context(item).default;

        accumulator.push(service);
        return accumulator;
    }, []);
};
