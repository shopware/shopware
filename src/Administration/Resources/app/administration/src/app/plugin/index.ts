/* eslint-disable @typescript-eslint/no-explicit-any */
/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ((): any[] => {
    const context = require.context('./', false, /(?<!index)(?<!\.spec)\.js$/);

    return context.keys().reduce<any[]>((accumulator, item) => {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-assignment
        const service = context(item).default;
        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
        accumulator.push(service);

        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
        return accumulator;
    }, []);
})();
