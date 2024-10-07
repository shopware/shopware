/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations, @typescript-eslint/no-explicit-any
export default () => {
    if (window._features_.ADMIN_VITE) {
        // @ts-expect-error
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-call
        const context = import.meta.glob<$TSFixMe>('./**/!(*.spec).{j,t}s', {
            eager: false,
        });

        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
        return Object.values(context);
    }

    const context = require.context('./', false, /(?<!index)(?<!\.spec)(?<!spec\.vue2\.)(\.js|\.ts)$/);
    return context.keys().reduce((accumulator, item) => {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        const service = context(item).default as never;

        accumulator.push(service);
        return accumulator;
    }, []);
};
