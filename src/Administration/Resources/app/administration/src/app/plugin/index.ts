/* eslint-disable @typescript-eslint/no-explicit-any */
/**
 * @sw-package framework
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ((): any[] => {
    // `!(*.spec)` only filters filenames, so the second pattern is what keeps a split `<name>.spec/`
    // out — a helper beside those specs would otherwise install as a plugin and run at admin boot.
    // @ts-expect-error
    const context = import.meta.glob(['./**/!(*.spec).{j,t}s', '!./**/*.spec/**'], {
        eager: true,
        import: 'default',
    });

    return Object.values(context);
})();
