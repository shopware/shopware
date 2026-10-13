/**
 * @sw-package framework
 */

// eslint-disable-next-line @typescript-eslint/no-require-imports
const resolveShopwareModule = require('./resolve-module') as (specifier: string) => unknown;

// `Shopware` is declared `const` on the global, which puts it outside the keys `replaceProperty` accepts.
const globalObject = global as unknown as { Shopware: unknown };

describe('shopware:* Jest module resolution', () => {
    it('reads a named export off the global at access time, not at import', () => {
        // Resolved before the global is replaced, which is the case the getters exist for: a spec swaps
        // `window.Shopware` for a partial mock long after the module graph is built.
        const utils = resolveShopwareModule('shopware:utils') as { createId: () => string };
        const shopware = jest.replaceProperty(globalObject, 'Shopware', { Utils: { createId: () => 'first' } });

        expect(utils.createId()).toBe('first');

        shopware.replaceValue({ Utils: { createId: () => 'second' } });

        expect(utils.createId()).toBe('second');
    });

    it('resolves a store on every call', () => {
        const useStore = resolveShopwareModule('shopware:stores/notification') as () => unknown;
        const first = { source: 'first' };
        const second = { source: 'second' };
        const shopware = jest.replaceProperty(globalObject, 'Shopware', { Store: { get: () => first } });

        expect(useStore()).toBe(first);

        shopware.replaceValue({ Store: { get: () => second } });

        expect(useStore()).toBe(second);
    });
});
