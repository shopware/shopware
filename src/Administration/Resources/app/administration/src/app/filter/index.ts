/**
 * @package admin
 */
/* eslint-disable */

export default (): any[] => {
    if (window._features_.ADMIN_VITE) {
        // @ts-expect-error
        const context = import.meta.glob<$TSFixMe>('./**/!(*.spec).{j,t}s', {
            eager: true,
        });

        return Object.values(context).map((module) => module.default);
    }

    const context = require.context('./', true, /(?<!index)\.(?<!spec\.)(?<!spec\.vue2\.)(js|ts)$/);

    return context.keys().reduce<any[]>((accumulator, item) => {
        const service = context(item).default;

        accumulator.push(service);

        return accumulator;
    }, []);
};
