/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default () => {
    const context = require.context('./', true, /\.\/[a-z0-9-]+\/index\.[jt]s$/);

    // Reversing the order so, for example sw-settings will be included before sw-settings-country.
    return context.keys().reverse().reduce((accumulator, item) => {
        // do not load the sw-login by default
        if (item.includes('./sw-login/')) {
            return accumulator;
        }

        const module = context(item).default;
        accumulator.push(module);
        return accumulator;
    }, []);
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const login = () => {
    const context = require.context('./sw-login', true, /\.\/index\.[jt]s/);

    // import login dependencies
    return context.keys().map((key) => context(key).default);
};
