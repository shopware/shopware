/**
 * @sw-package framework
 */

const exampleLegacyStore = Shopware.Store.register('exampleLegacy', {
    state: () => ({
        entries: [] as string[],
    }),
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default exampleLegacyStore;
