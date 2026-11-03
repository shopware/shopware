/**
 * @sw-package framework
 */

import fs from 'node:fs';
import path from 'node:path';
import { renderShopwareModules, type GeneratedFiles } from '../index';

const administrationRoot = path.resolve(__dirname, '../../..');

let generated: GeneratedFiles | undefined;

/** Renders the files once for all tests, because reading the Administration's sources takes seconds. */
function generate(): GeneratedFiles {
    generated ??= renderShopwareModules(administrationRoot);

    return generated;
}

/*
 * What extensions may import from the Administration is written down in two checked-in files:
 * the list of `shopware:*` imports, and the types extension developers see for them.
 * This module writes both from the Administration's own sources, so the list never has to be kept by hand,
 * and a reviewer sees every change to the public API as a diff.
 */
describe('build/shopware-modules-generator', () => {
    /**
     * @requirement The list of what extensions may import comes from the Administration's sources. A new utility
     * appears there without anyone editing the list by hand.
     */
    it('lists what the Administration publishes to extensions', () => {
        // ACT
        const registry = JSON.parse(generate()['shopware-modules.json']) as Record<string, { exports: string[] }>;

        // ASSERT
        expect(registry['shopware:data'].exports).toContain('Criteria');
        expect(registry['shopware:utils'].exports).toContain('createId');
    });

    /**
     * @requirement Extension developers get types for every import, so their editor completes the API and their
     * type check catches a misuse before the build.
     */
    it('gives extension developers types for every import', () => {
        // ACT
        const declarations = generate()['src/shopware-virtual-modules.d.ts'];

        // ASSERT
        expect(declarations).toContain("declare module 'shopware:data' {");
        expect(declarations).toContain("declare module 'shopware:utils' {");
    });

    /**
     * @requirement The public API changes only through a reviewed diff of the checked-in files. When they no longer
     * match the sources, the tests fail and name the command that brings them up to date.
     */
    it('keeps the checked-in files in step with the Administration', () => {
        // ACT
        const files = generate();

        // ASSERT
        expect(Object.keys(files)).toEqual([
            'shopware-modules.json',
            'src/shopware-virtual-modules.d.ts',
        ]);
        Object.entries(files).forEach(([file, content]) => {
            expect(
                fs.readFileSync(path.join(administrationRoot, file), 'utf8'),
                `${file} is stale. Run \`composer admin:generate-shopware-modules\`.`,
            ).toBe(content);
        });
    });
});
