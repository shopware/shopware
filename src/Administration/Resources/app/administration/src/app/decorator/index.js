/**
 * @sw-package framework
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default (() => {
    const context = import.meta.glob(['./**/*.{j,t}s', '!./**/*.spec.{j,t}s'], {
        eager: true,
    });

    return Object.values(context).map((module) => module.default);
})();
