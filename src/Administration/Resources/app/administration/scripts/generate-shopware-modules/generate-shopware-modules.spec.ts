/**
 * @sw-package framework
 *
 * The `shopware:*` registry and its declarations are checked in, so a new specifier shows up in a pull
 * request diff instead of appearing at build time. That only holds while the files match the sources.
 */

import fs from 'node:fs';
import path from 'node:path';
import { extractModuleRegistry } from './extract-modules';
import { renderDeclarations, REGENERATE_COMMAND } from './render-declarations';
import { renderRegistry, REGISTRY_FILE, DECLARATIONS_FILE } from './index';

const administrationRoot = path.resolve(__dirname, '../..');

describe('scripts/generate-shopware-modules', () => {
    it('has a checked-in registry that matches the Administration sources', () => {
        expect(
            fs.readFileSync(REGISTRY_FILE, 'utf8'),
            `shopware-modules.json is stale. Run \`${REGENERATE_COMMAND}\`.`,
        ).toBe(renderRegistry(administrationRoot));
    });

    it('has checked-in declarations that match the registry', () => {
        expect(
            fs.readFileSync(DECLARATIONS_FILE, 'utf8'),
            `src/shopware-virtual-modules.d.ts is stale. Run \`${REGENERATE_COMMAND}\`.`,
        ).toBe(renderDeclarations(extractModuleRegistry(administrationRoot)));
    });

    describe('the registry it builds', () => {
        const registry = extractModuleRegistry(administrationRoot);

        it('gives the branch-backed families a barrel and the registry-backed ones none', () => {
            expect(registry['shopware:utils'].exports.length).toBeGreaterThan(0);
            expect(registry['shopware:data'].exports.length).toBeGreaterThan(0);
            expect(registry['shopware:mixins'].exports).toEqual([]);
            expect(registry['shopware:stores'].exports).toEqual([]);
        });

        it('makes every barrel member a subpath of its own', () => {
            expect(Object.keys(registry['shopware:utils'].subpaths)).toEqual(registry['shopware:utils'].exports);
            expect(Object.keys(registry['shopware:data'].subpaths)).toEqual(registry['shopware:data'].exports);
        });

        it('reads the utility namespaces that can be destructured', () => {
            expect(registry['shopware:utils'].subpaths.debug).toEqual([
                'warn',
                'error',
            ]);
            expect(registry['shopware:utils'].subpaths.createId).toEqual([]);
        });

        it('publishes the mixins and stores the global interfaces declare', () => {
            expect(Object.keys(registry['shopware:mixins'].subpaths)).toContain('sw-form-field');
            expect(Object.keys(registry['shopware:mixins'].subpaths)).toContain('cms-element');
            expect(Object.keys(registry['shopware:stores'].subpaths)).toContain('notification');
        });
    });
});
