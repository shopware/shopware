/**
 * @sw-package framework
 *
 * The `shopware:*` registry and its declarations are checked in, so a new specifier shows up in a pull
 * request diff instead of appearing at build time. That only holds while the files match the sources.
 */

import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { extractModuleRegistry, extractPublicModules } from './extract-modules';
import { renderDeclarations, REGENERATE_COMMAND } from './render-declarations';
import { renderRegistry, REGISTRY_FILE, DECLARATIONS_FILE } from './index';

const administrationRoot = path.resolve(__dirname, '../..');
const registry = extractModuleRegistry(administrationRoot);

describe('scripts/generate-shopware-modules', () => {
    it('has a checked-in registry that matches the Administration sources', () => {
        expect(
            fs.readFileSync(REGISTRY_FILE, 'utf8'),
            `shopware-modules.json is stale. Run \`${REGENERATE_COMMAND}\`.`,
        ).toBe(renderRegistry(registry));
    });

    it('has checked-in declarations that match the registry', () => {
        expect(
            fs.readFileSync(DECLARATIONS_FILE, 'utf8'),
            `src/shopware-virtual-modules.d.ts is stale. Run \`${REGENERATE_COMMAND}\`.`,
        ).toBe(renderDeclarations(registry));
    });

    describe('the registry it builds', () => {
        it('gives the branch-backed families root exports and the registry-backed ones none', () => {
            expect(registry['shopware:utils'].exports.length).toBeGreaterThan(0);
            expect(registry['shopware:data'].exports.length).toBeGreaterThan(0);
            expect(registry['shopware:composables'].exports.length).toBeGreaterThan(0);
            expect(registry['shopware:mixins'].exports).toEqual([]);
            expect(registry['shopware:stores'].exports).toEqual([]);
        });

        it('makes every root export a subpath of its own', () => {
            expect(Object.keys(registry['shopware:utils'].subpaths)).toEqual(registry['shopware:utils'].exports);
            expect(Object.keys(registry['shopware:data'].subpaths)).toEqual(registry['shopware:data'].exports);
            expect(Object.keys(registry['shopware:composables'].subpaths)).toEqual(registry['shopware:composables'].exports);
        });

        it('reads the utility namespaces that can be destructured', () => {
            expect(registry['shopware:utils'].subpaths.debug).toEqual([
                'warn',
                'error',
            ]);
            expect(registry['shopware:utils'].subpaths.createId).toEqual([]);
        });

        it('publishes only the mixins the central registry owns', () => {
            expect(Object.keys(registry['shopware:mixins'].subpaths)).toContain('sw-form-field');
            expect(Object.keys(registry['shopware:mixins'].subpaths)).not.toContain('cms-element');
            expect(Object.keys(registry['shopware:stores'].subpaths)).toContain('notification');
        });
    });

    describe('the mini-modules it finds', () => {
        /** Writes a mini-module with the given `index.ts` into a temporary Administration and extracts it. */
        function extractFrom(moduleDir: string, index: string): () => ReturnType<typeof extractPublicModules> {
            return () => {
                const root = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-mini-modules-'));

                fs.mkdirSync(path.join(root, moduleDir, 'src'), { recursive: true });
                fs.mkdirSync(path.join(root, moduleDir, 'tests'));
                fs.writeFileSync(path.join(root, moduleDir, 'index.ts'), index);

                try {
                    return extractPublicModules(root);
                } finally {
                    fs.rmSync(root, { recursive: true, force: true });
                }
            };
        }

        it('publishes each mini-module under its kind and directory name, with the value exports of its index.ts', () => {
            expect(registry['shopware:framework'].subpaths['public-component']).toEqual([
                'definePrivateComponent',
                'definePublicComponent',
            ]);
            expect(registry['shopware:framework'].sources?.['public-component']).toBe('src/core/public-component');
        });

        it('publishes a component module as a component without a kind tag', () => {
            const extract = extractFrom(
                'src/module/sw-example/component/sw-example-detail',
                "export default definePublicComponent({ name: 'sw-example-detail', load: () => import('./src/x') });\n",
            );

            expect(extract()['shopware:components'].subpaths).toEqual({ 'sw-example-detail': ['default'] });
        });

        it('publishes any other module under the kind its doc comment names', () => {
            const extract = extractFrom(
                'src/module/sw-example/helper/example-delivery',
                "/**\n * @shopware-kind helpers\n */\nexport { primaryDelivery } from './src/x';\n",
            );

            expect(extract()['shopware:helpers'].subpaths).toEqual({ 'example-delivery': ['primaryDelivery'] });
        });

        it('refuses a module that is no component and names no kind', () => {
            const extract = extractFrom(
                'src/module/sw-example/helper/example-delivery',
                "export { primaryDelivery } from './src/x';\n",
            );

            expect(extract).toThrow(
                '"src/module/sw-example/helper/example-delivery/index.ts" needs exactly one kind. Add "@shopware-kind <kind>"',
            );
        });

        it('refuses a kind that does not exist', () => {
            const extract = extractFrom(
                'src/module/sw-example/helper/example-delivery',
                "/** @shopware-kind gadgets */\nexport { primaryDelivery } from './src/x';\n",
            );

            expect(extract).toThrow(
                'names the unknown kind "gadgets". Use one of: components, framework, handlers, helpers.',
            );
        });

        it('publishes a component module under the name it declares, also when its directory is named otherwise', () => {
            const extract = extractFrom(
                'src/module/sw-cms/elements/text/component',
                "export default definePublicComponent({ name: 'sw-cms-el-text', load: () => import('./src/x') });\n",
            );

            expect(Object.keys(extract()['shopware:components'].subpaths)).toEqual(['sw-cms-el-text']);
        });
    });

    describe('the declarations it renders', () => {
        const declarations = renderDeclarations(registry);

        it('re-exports the values and types of a mini-module from its index.ts', () => {
            expect(declarations).toContain(
                [
                    "declare module 'shopware:framework/public-component' {",
                    "    export { definePrivateComponent, definePublicComponent } from 'src/core/public-component';",
                    "    export type * from 'src/core/public-component';",
                    '}',
                ].join('\n'),
            );
        });

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

        it('lists only the named exports recorded for a namespace subpath', () => {
            expect(declarations).toContain("declare module 'shopware:utils/debug'");
            expect(declarations).toContain("export const warn: (typeof member)['warn'];");
            expect(declarations).toContain("export const error: (typeof member)['error'];");
        });

        it('gives a default-only subpath no named exports', () => {
            const criteriaDeclaration = declarations.slice(
                declarations.indexOf("declare module 'shopware:data/Criteria'"),
                declarations.indexOf("declare module 'shopware:data/Entity'"),
            );

            expect(criteriaDeclaration).toContain('export default member;');
            expect(criteriaDeclaration).not.toContain('export const');
        });

        it('marks the composables with the stability of their own sources', () => {
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
});
