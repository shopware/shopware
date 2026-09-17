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
});
