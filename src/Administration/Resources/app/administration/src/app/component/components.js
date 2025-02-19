/**
 * @sw-package framework
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default () => {
    const context = import.meta.glob('./**/index!(*.spec).{j,t}s');

    return Object.values(context);
};
