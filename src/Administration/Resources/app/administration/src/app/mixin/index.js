/**
 * @sw-package framework
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default () => {
    // Loading a register.ts registers its mixin.
    const registrations = import.meta.glob('./*/register.ts', { eager: true });
    const mixins = import.meta.glob('./*/index.ts', { eager: true });

    return Object.keys(registrations).map((file) => mixins[file.replace('register.ts', 'index.ts')].default);
};
