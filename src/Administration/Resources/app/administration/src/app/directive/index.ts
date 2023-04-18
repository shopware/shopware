/* eslint-disable */
/**
 * @package admin
 */

export default (): void => {
    const context = require.context('./', false, /(?<!index)(?<!\.spec)\.(js|ts)$/);

    return context.keys().forEach(item => {
        context(item);
    });
};
