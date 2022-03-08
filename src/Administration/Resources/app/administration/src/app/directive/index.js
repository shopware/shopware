export default () => {
    const context = require.context('./', false, /(?<!index)\.(js|ts)$/);
    return context.keys().forEach(item => context(item));
};
