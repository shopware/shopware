export default (() => {
    const context = require.context('./', false, /(?<!index)\.js$/);
    return context.keys().reduce((accumulator, item) => {
        const service = context(item).default;
        const serviceName = service.name.replace(/^\w/, (char) => {
            return char.toLowerCase();
        }).replace('Api', '');

        accumulator[serviceName] = {
            endpoint: null,
            name: serviceName,
            service
        };
        return accumulator;
    }, {});
})();
