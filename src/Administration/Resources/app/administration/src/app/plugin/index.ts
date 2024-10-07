/* eslint-disable @typescript-eslint/no-explicit-any */
/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ((): any[] => {
    if (window._features_.ADMIN_VITE) {
        // @ts-expect-error
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-call
        const context = import.meta.glob('./**/!(*.spec).{j,t}s', {
            eager: true,
            import: 'default',
        });

        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
        return Object.values(context);
    }

    const context = require.context('./', true, /(?<!index)\.(?<!spec\.)(?<!spec\.vue2\.)(js|ts)$/);

    return context.keys().reduce<any[]>((accumulator, item) => {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-assignment
        const service = context(item).default;
        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
        accumulator.push(service);

        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
        return accumulator;
    }, []);
})();
