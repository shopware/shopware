export default () => {
    const context = require.context('./', true, /(?<!components)\.js$/);
    return context.keys().reduce((accumulator, item) => {
        const service = context(item).default;

        accumulator.push(service);
        return accumulator;
    }, []);
};
