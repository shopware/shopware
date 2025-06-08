/**
 * @sw-package framework
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default async () => {
    const context = await import.meta.glob('./*/index!(*.spec).{j,t}s');

    const modules = Object.values(context)
        .reverse()
        .map((module) => module());

    return Promise.all(modules);
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const login = () => {
    let context = import.meta.glob('./sw-login/index!(*.spec).{j,t}s', {
        eager: true,
    });

    // import login dependencies
    const dependencies = Object.values(context);

    context = import.meta.glob('./sw-inactivity-login/index!(*.spec).{j,t}s', { eager: true });
    dependencies.push(...Object.values(context));

    return dependencies;
};
