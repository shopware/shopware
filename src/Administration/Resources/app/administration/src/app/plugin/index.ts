/* eslint-disable @typescript-eslint/no-explicit-any */
/**
 * @sw-package framework
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ((): any[] => {
    // @ts-expect-error
    const context = import.meta.glob('./**/!(*.spec).{j,t}s', {
        eager: true,
        import: 'default',
    });

    return Object.values(context);
})();
