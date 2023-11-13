/* eslint-disable */
/**
 * @package admin
 */

export default (): void => {
    const context = require.context('./', false, /(?<!index)(?<!\.spec)(?<!spec\.vue3\.)\.(js|ts)$/);

    return context.keys().forEach(item => {
        context(item);
    });
};
