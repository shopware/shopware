/**
 * @sw-package framework
 *
 * Makes every `shopware:*` specifier importable from a spec. Vite's plugin generates these modules for
 * the build; Jest does not run Vite, so each one is registered here instead.
 *
 * `virtual: true` is what lets them exist without a file: Jest keys a non-relative virtual mock by the
 * bare specifier, so one registration serves every importer wherever it sits in the tree. It also
 * survives `jest.resetModules()` and `jest.isolateModules()`, because the mock registry is separate from
 * the module registry those clear.
 *
 * The factory is lazy, so a spec that imports nothing virtual never loads the resolver.
 */

// eslint-disable-next-line no-undef
const specifiers = shopwareVirtualModules;

specifiers.forEach((specifier) => jest.mock(specifier, () => require('./resolve-module')(specifier), { virtual: true }));
