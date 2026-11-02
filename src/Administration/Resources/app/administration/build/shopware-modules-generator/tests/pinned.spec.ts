/**
 * @sw-package framework
 */

import path from 'node:path';
import type { ModuleRegistry } from '../../shopware-modules';
import { renderShopwareModules, type GeneratedFiles } from '../index';

const administrationRoot = path.resolve(__dirname, '../../..');

let files: GeneratedFiles | undefined;

/** The generated files. Rendering parses the Administration sources, so it runs once for all tests. */
function rendered(): GeneratedFiles {
    files ??= renderShopwareModules(administrationRoot);

    return files;
}

/** The registry the generator renders, parsed. */
function renderedRegistry(): ModuleRegistry {
    return JSON.parse(rendered()['shopware-modules.json']) as ModuleRegistry;
}

function renderedDeclarations(): string {
    return rendered()['src/shopware-virtual-modules.d.ts'];
}

describe('build/shopware-modules-generator: the registry', () => {
    it('gives the branch-backed families root exports and the registry-backed ones none', () => {
        // ACT
        const registry = renderedRegistry();

        // ASSERT
        expect(registry['shopware:utils'].exports.length).toBeGreaterThan(0);
        expect(registry['shopware:data'].exports.length).toBeGreaterThan(0);
        expect(registry['shopware:composables'].exports.length).toBeGreaterThan(0);
        expect(registry['shopware:mixins'].exports).toEqual([]);
        expect(registry['shopware:stores'].exports).toEqual([]);
    });

    it('makes every root export a subpath of its own', () => {
        // ACT
        const registry = renderedRegistry();

        // ASSERT
        expect(Object.keys(registry['shopware:utils'].subpaths)).toEqual(registry['shopware:utils'].exports);
        expect(Object.keys(registry['shopware:data'].subpaths)).toEqual(registry['shopware:data'].exports);
        expect(Object.keys(registry['shopware:composables'].subpaths)).toEqual(registry['shopware:composables'].exports);
    });

    it('reads the utility namespaces that can be destructured', () => {
        // ACT
        const utils = renderedRegistry()['shopware:utils'];

        // ASSERT
        expect(utils.subpaths.debug).toEqual([
            'warn',
            'error',
        ]);
        expect(utils.subpaths.createId).toEqual([]);
    });

    it('publishes only the mixins the central registry owns', () => {
        // ACT
        const registry = renderedRegistry();

        // ASSERT
        expect(Object.keys(registry['shopware:mixins'].subpaths)).toContain('sw-form-field');
        expect(Object.keys(registry['shopware:mixins'].subpaths)).not.toContain('cms-element');
        expect(Object.keys(registry['shopware:stores'].subpaths)).toContain('notification');
    });

    it('publishes each mini-module under its directory name, with the value exports of its index.ts', () => {
        // ACT
        const miniModules = renderedRegistry()['shopware:mini-modules'];

        // ASSERT
        expect(miniModules.subpaths['public-component']).toEqual(['definePublicComponent']);
        expect(miniModules.sources?.['public-component']).toBe('src/core/public-component');
    });
});

describe('build/shopware-modules-generator: the declarations', () => {
    it('re-exports the values and types of a mini-module from its index.ts', () => {
        // ACT
        const declarations = renderedDeclarations();

        // ASSERT
        expect(declarations).toContain(
            [
                "declare module 'shopware:mini-modules/public-component' {",
                "    export { definePublicComponent } from 'src/core/public-component';",
                "    export type * from 'src/core/public-component';",
                '}',
            ].join('\n'),
        );
    });

    it('lists only the named exports recorded for a namespace subpath', () => {
        // ACT
        const declarations = renderedDeclarations();

        // ASSERT
        expect(declarations).toContain("declare module 'shopware:utils/debug'");
        expect(declarations).toContain("export const warn: (typeof member)['warn'];");
        expect(declarations).toContain("export const error: (typeof member)['error'];");
    });

    it('gives a default-only subpath no named exports', () => {
        // ARRANGE
        const declarations = renderedDeclarations();

        // ACT
        const criteriaDeclaration = declarations.slice(
            declarations.indexOf("declare module 'shopware:data/Criteria'"),
            declarations.indexOf("declare module 'shopware:data/Entity'"),
        );

        // ASSERT
        expect(criteriaDeclaration).toContain('export default member;');
        expect(criteriaDeclaration).not.toContain('export const');
    });

    it('marks the composables with the stability of their own sources', () => {
        // ACT
        const declarations = renderedDeclarations();

        // ASSERT
        expect(declarations).toContain(
            [
                '/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */',
                "declare module 'shopware:composables' {",
            ].join('\n'),
        );
        expect(declarations).toContain(
            [
                '/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */',
                "declare module 'shopware:composables/useListing' {",
            ].join('\n'),
        );
        expect(declarations).toContain(
            [
                '/** @experimental stableVersion:v6.8.0 */',
                "declare module 'shopware:data' {",
            ].join('\n'),
        );
    });
});
