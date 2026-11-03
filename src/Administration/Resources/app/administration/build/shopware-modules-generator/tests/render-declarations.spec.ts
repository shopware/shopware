/**
 * @sw-package framework
 */

import fs from 'node:fs';
import path from 'node:path';
import type { ModuleRegistry } from '../../shopware-modules';
import { renderDeclarations } from '../src/render-declarations';

const registry = JSON.parse(
    fs.readFileSync(path.resolve(__dirname, '../../../shopware-modules.json'), 'utf8'),
) as ModuleRegistry;

describe('build/shopware-modules-generator/src/render-declarations', () => {
    it('puts each re-exported name on its own line when one line would exceed the print width', () => {
        const names = Array.from({ length: 12 }, (_, index) => `exportNumber${index}`);
        const rendered = renderDeclarations({
            ...registry,
            'shopware:components': {
                exports: [],
                subpaths: { 'sw-wide': names },
                sources: { 'sw-wide': 'src/module/sw-wide' },
            },
        });

        expect(rendered).toContain(
            [
                "declare module 'shopware:components/sw-wide' {",
                '    export {',
                ...names.map((name) => `        ${name},`),
                "    } from 'src/module/sw-wide';",
            ].join('\n'),
        );
    });
});
