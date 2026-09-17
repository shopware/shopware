/**
 * @sw-package framework
 */

import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

// eslint-disable-next-line @typescript-eslint/no-require-imports
const { resolveStub, writeStubs } = require('./stubs') as {
    resolveStub: (specifier: string, stubDirectory: string) => string | undefined;
    writeStubs: (specifiers: string[], stubDirectory: string) => number;
};
// eslint-disable-next-line @typescript-eslint/no-require-imports
const resolveShopwareModule = require('./resolve-module') as (specifier: string) => unknown;

describe('shopware:* Jest stubs', () => {
    let stubDirectory: string;

    beforeEach(() => {
        stubDirectory = fs.mkdtempSync(path.join(os.tmpdir(), 'shopware-virtual-modules-'));
    });

    afterEach(() => {
        fs.rmSync(stubDirectory, { recursive: true, force: true });
    });

    it('only resolves specifiers from the latest registry', () => {
        const removed = 'shopware:utils/removed';
        const current = 'shopware:utils/current';

        writeStubs(
            [
                removed,
                current,
            ],
            stubDirectory,
        );
        expect(resolveStub(removed, stubDirectory)).toBeDefined();

        writeStubs([current], stubDirectory);

        expect(resolveStub(removed, stubDirectory)).toBeUndefined();
        expect(resolveStub(current, stubDirectory)).toBeDefined();
    });

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
