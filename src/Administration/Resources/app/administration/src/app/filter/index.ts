/**
 * @sw-package framework
 */
/* eslint-disable */

export default (): any[] => {
    // @ts-expect-error
    const context = import.meta.glob<$TSFixMe>('./**/!(*.spec).{j,t}s', {
        eager: true,
    });

    return Object.values(context).map((module) => module.default);
};
