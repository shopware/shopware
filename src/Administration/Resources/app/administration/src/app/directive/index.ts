/* eslint-disable */
/**
 * @package admin
 */
export default (): void | any[] => {
    if (window._features_.ADMIN_VITE) {
        // @ts-expect-error
        const context = import.meta.glob<$TSFixMe>('./**/!(*.spec).{j,t}s', {
            eager: true,
        });

        return Object.values(context).map((item) => item.default);
    }

    const context = require.context('./', true, /(?<!index)\.(?<!spec\.)(?<!spec\.vue2\.)(js|ts)$/);

    return context.keys().forEach((item) => {
        context(item);
    });
};
