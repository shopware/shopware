/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default () => {
    if (window._features_.ADMIN_VITE) {
        const context = import.meta.glob('./**/!(*.spec).{j,t}s', {
            eager: true,
        });
        return Object.values(context).map((module) => module.default);
    }

    const context = require.context('./', true, /(?<!index)\.(?<!spec\.)(?<!spec\.vue2\.)(js|ts)$/);
    return context.keys().forEach((item) => context(item));
};
