/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default async () => {
    if (window._features_.ADMIN_VITE) {
        const context = await import.meta.glob('./*/index!(*.spec).{j,t}s');

        const modules = Object.values(context)
            .reverse()
            .map((module) => module());

        return Promise.all(modules);
    }

    const context = require.context('./', true, /\.\/[a-z0-9-]+\/index\.[jt]s$/);

    // Reversing the order so, for example sw-settings will be included before sw-settings-country.
    return context
        .keys()
        .reverse()
        .reduce((accumulator, item) => {
            // do not load the sw-login by default
            if (item.includes('./sw-login/')) {
                return accumulator;
            }

            const module = context(item).default;
            accumulator.push(module);
            return accumulator;
        }, []);
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const login = () => {
    if (window._features_.ADMIN_VITE) {
        let context = import.meta.glob('./sw-login/index!(*.spec).{j,t}s', {
            eager: true,
        });

        // import login dependencies
        const dependencies = Object.values(context);

        context = import.meta.glob('./sw-inactivity-login/index!(*.spec).{j,t}s', { eager: true });
        dependencies.push(...Object.values(context));

        return dependencies;
    }

    let context = require.context('./sw-login', true, /\.\/index\.[jt]s/);

    // import login dependencies
    const dependencies = context.keys().map((key) => context(key).default);

    context = require.context('./sw-inactivity-login', true, /\.\/index\.[jt]s/);
    dependencies.push(context.keys().map((key) => context(key).default));

    return dependencies;
};
