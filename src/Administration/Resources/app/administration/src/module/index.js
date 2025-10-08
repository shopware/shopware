/**
 * @sw-package framework
 */

const importLogin = () => {
    return import.meta.glob('./sw-login/index!(*.spec).{j,t}s', {
        eager: true,
    });
};

const importInactivityLogin = () => {
    return import.meta.glob('./sw-inactivity-login/index!(*.spec).{j,t}s', { eager: true });
};

const importSSOError = () => {
    return import.meta.glob('./sw-sso-error/index!(*.spec).{j,t}s', {
        eager: true,
    });
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default async () => {
    const context = await import.meta.glob([
        './*/index!(*.spec).{j,t}s',
        '!./sw-login/index!(*.spec).{j,t}s',
        '!./sw-inactivity-login/index!(*.spec).{j,t}s',
        '!./sw-sso-error/index!(*.spec).{j,t}s',
    ]);

    // Directly trigger the import of inactivity login to ensure it's loaded.
    // Normal login is not needed here because after redirection to the login page,
    // the whole app is reloaded.
    importInactivityLogin();

    const modules = Object.values(context)
        .reverse()
        .map((module) => module());

    return Promise.all(modules);
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const login = () => {
    let context = importLogin();

    // import login dependencies
    const dependencies = Object.values(context);

    context = importInactivityLogin();
    dependencies.push(...Object.values(context));

    return dependencies;
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const ssoError = () => {
    const context = importSSOError();

    return Object.values(context);
};
