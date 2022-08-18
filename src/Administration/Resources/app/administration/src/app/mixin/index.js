// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default () => {
    const context = require.context('./', false, /(?<!index)\.j|ts$/);
    return context.keys().forEach(item => context(item));
};
