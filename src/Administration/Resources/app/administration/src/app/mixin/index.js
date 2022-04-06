export default () => {
    const context = require.context('./', false, /(?<!index)\.j|ts$/);
    return context.keys().forEach(item => context(item));
};
