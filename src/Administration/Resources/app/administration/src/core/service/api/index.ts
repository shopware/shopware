/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations, @typescript-eslint/no-explicit-any
export default (): any[] => {
    const context = require.context('./', false, /(?<!index)(?<!\.spec)(\.js|\.ts)$/);
    return context.keys().reduce((accumulator, item) => {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
        const service = context(item).default;

        // @ts-expect-error - never can be converted to any
        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
        accumulator.push(service);
        return accumulator;
    }, []);
};
