/* eslint-disable */
/**
 * @package admin
 */

export default (): void => {
    const context = require.context('./', true, /(?<!index)\.(?<!spec\.)(?<!spec\.vue2\.)(js|ts)$/);

    return context.keys().forEach(item => {
        context(item);
    });
};
