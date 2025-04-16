/**
 * @sw-package framework
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default () => {
    const context = import.meta.glob('./**/index!(*.spec).{j,t}s');
    console.error('COMPONENTS CONTEXT', context);

    for (const element of Object.entries(context)) {
        // get the last name before index file, so second element from end
        const name = element[0].split('/').slice(-2, -1).join('/');
        console.error('registering name', name, 'full path', element[0]);
        Shopware.Component.register(name, () => import(element[0]));
    }

    // now get all extend-* files glob
    const extendFiles = import.meta.glob('./**/extends-*.{js,ts}');
    console.error('EXTEND FILES', extendFiles);
    for (const element of Object.entries(extendFiles)) {
        const name = element[0].split('/').slice(-2, -1).join('/');

        // get last name in path, remove extends- prefix and file extension, which can be js or ts
        const extendsName = element[0]
            .split('/')
            .slice(-1)
            .join('/')
            .replace('extends-', '')
            .replace('.js', '')
            .replace('.ts', '');

        console.error('extending name', name, 'full path', element[0], 'extends name', extendsName);
        // Shopware.Component.register(name, () => import(element[0]));
        Shopware.Component.extend(name, extendsName, () => import(element[0]));
        // element[1]?.();
    }
    return [];
};
