/**
 * @sw-package framework
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default () => {
    // `!(*.spec)` only filters filenames, so the second pattern is what keeps a split `<name>.spec/` out.
    // @ts-expect-error
    const context = import.meta.glob(['./**/!(*.spec).{j,t}s', '!./**/*.spec/**'], {
        eager: true,
        import: 'default',
    });

    return Object.values(context);
};
