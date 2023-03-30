/**
 * @package admin
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations,@typescript-eslint/no-explicit-any
export default ((): any[] => {
    // @ts-expect-error - TS does not know require.context
    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-call
    const context = require.context('./', false, /(?<!index)(?<!\.spec)\.js$/);

    // eslint-disable-next-line max-len
    // eslint-disable-next-line @typescript-eslint/no-unsafe-return,@typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
    return context.keys().reduce((accumulator: unknown, item: unknown) => {
        // eslint-disable-next-line max-len
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        const service = context(item).default;
        // @ts-expect-error
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
        accumulator.push(service);
        return accumulator;
    }, []);
})();
