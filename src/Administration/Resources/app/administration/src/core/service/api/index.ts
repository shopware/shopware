/**
 * @sw-package framework
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations, @typescript-eslint/no-explicit-any
export default () => {
    // @ts-expect-error
    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-call
    const context = import.meta.glob('./**/!(*.spec).{j,t}s');

    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
    return Object.values(context);
};
