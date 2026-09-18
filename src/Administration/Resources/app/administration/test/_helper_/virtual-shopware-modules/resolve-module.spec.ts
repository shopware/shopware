/**
 * @sw-package framework
 */

// eslint-disable-next-line @typescript-eslint/no-require-imports
const resolveShopwareModule = require('./resolve-module') as (specifier: string) => unknown;

describe('shopware:* Jest module resolution', () => {
    it('reads named exports and stores from the current global object', () => {
        const originalShopware = Object.getOwnPropertyDescriptor(global, 'Shopware');
        const firstCreateId = jest.fn(() => 'first');
        const secondCreateId = jest.fn(() => 'second');
        const firstStore = { source: 'first' };
        const secondStore = { source: 'second' };
        const utils = resolveShopwareModule('shopware:utils') as { createId: () => string };
        const useStore = resolveShopwareModule('shopware:stores/notification') as () => unknown;
        const setShopware = (createId: () => string, store: unknown): void => {
            Object.defineProperty(global, 'Shopware', {
                configurable: true,
                value: {
                    Utils: { createId },
                    Store: { get: () => store },
                },
            });
        };

        try {
            setShopware(firstCreateId, firstStore);
            expect(utils.createId()).toBe('first');
            expect(useStore()).toBe(firstStore);

            setShopware(secondCreateId, secondStore);
            expect(utils.createId()).toBe('second');
            expect(useStore()).toBe(secondStore);
        } finally {
            if (originalShopware) {
                Object.defineProperty(global, 'Shopware', originalShopware);
            } else {
                Reflect.deleteProperty(global, 'Shopware');
            }
        }
    });
});
