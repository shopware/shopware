export default () => {
    const context = require.context('./', false, /(?<!index)\.js|\.ts$/);
    return context.keys().reduce((accumulator, item) => {
        const service = context(item).default;

        accumulator.push(service);
        return accumulator;
    }, []);
};
