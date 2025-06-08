/* eslint-disable */
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
