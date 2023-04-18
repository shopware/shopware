/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default (): void => {
    const context = require.context('./', false, /(?<!index)(?<!\.spec)\.(js|ts)$/);

    return context.keys().forEach(item => {
        context(item);
    });
};
