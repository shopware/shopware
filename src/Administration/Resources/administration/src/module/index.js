export default (() => {
    const context = require.context('./', true, /\.\/[a-z-]+\/index\.js$/);

    // Reversing the order so, for example sw-settings will be included before sw-settings-country.
    return context.keys().reverse().reduce((accumulator, item) => {
        const module = context(item).default;
        accumulator.push(module);
        return accumulator;
    }, []);
})();
