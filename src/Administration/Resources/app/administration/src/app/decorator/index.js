// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default (() => {
    const context = require.context('./', false, /(?<!index)(?<!\.spec)\.ts$/);
    return context.keys().reduce((accumulator, item) => {
        const service = context(item).default;
        accumulator.push(service);
        return accumulator;
    }, []);
})();
