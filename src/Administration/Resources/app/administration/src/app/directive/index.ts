/* eslint-disable sw-deprecation-rules/private-feature-declarations, @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-member-access -- import.meta.glob returns untyped modules */
/**
 * @sw-package framework
 */
export default (): void | any[] => {
    // @ts-expect-error
    const context = import.meta.glob<$TSFixMe>('./**/!(*.spec).{j,t}s', {
        eager: true,
    });

    return Object.values(context).map((item) => item.default);
};
