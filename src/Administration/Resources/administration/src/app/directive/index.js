export default () => {
    const context = require.context('./', false, /(?<!index)\.js$/);
    return context.keys().forEach(item => context(item));
};
