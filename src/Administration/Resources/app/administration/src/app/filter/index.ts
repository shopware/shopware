/**
 * @sw-package framework
 */
/* eslint-disable */

export default (): any[] => {
    // `!(*.spec)` only filters filenames, so the second pattern is what keeps a split `<name>.spec/` out.
    // @ts-expect-error
    const context = import.meta.glob<$TSFixMe>(['./**/!(*.spec).{j,t}s', '!./**/*.spec/**'], {
        eager: true,
    });

    return Object.values(context).map((module) => module.default);
};
