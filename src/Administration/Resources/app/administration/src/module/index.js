export default () => {
    const context = require.context('./', true, /\.\/[a-z0-9-]+\/index\.js$/);

    // Reversing the order so, for example sw-settings will be included before sw-settings-country.
    return context.keys().reverse().reduce((accumulator, item) => {
        // do not load the sw-login by default
        if (item.includes('./sw-login/')) {
            return accumulator;
        }

        // Remove the whole if-block on feature removal, also the inner if-block
        if (Shopware.Feature.isActive('FEATURE_NEXT_12608')) {
            if (item.includes('./sw-plugin/')) {
                return accumulator;
            }
        }

        const module = context(item).default;
        accumulator.push(module);
        return accumulator;
    }, []);
};

export const login = () => {
    const context = require.context('./sw-login', true, /\.\/index\.js/);

    // import login dependencies
    return context.keys().map((key) => context(key).default);
};
