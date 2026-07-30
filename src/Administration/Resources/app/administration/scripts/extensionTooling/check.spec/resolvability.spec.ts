/**
 * @sw-package framework
 *
 * Integration: does the farm make an extension with no config at all resolve the
 * Administration? (A4.)
 *
 * The Administration here is a fixture, but it sits inside the temporary project
 * root, so the resolution semantics are the real ones: a bare specifier walks
 * `node_modules` upwards from the importing file, and the project root is the
 * ancestor both the extension and the Administration share. That is exactly why
 * the farm has to live at the project root, and why linking it in a directory
 * that is not an ancestor of the Administration would fix extension imports while
 * leaving the host's own `src/…` imports broken.
 *
 * `tsc` is invoked with files on the command line, which makes it ignore every
 * tsconfig — the closest reproduction of an editor's inferred project for a file
 * in an extension that has committed nothing.
 */

import { spawnSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { buildFarm } from '../resolution';
import { REAL_ADMINISTRATION_ROOT, createTempProject, removeTempProject, writeTree } from '../test-helpers';

jest.setTimeout(300_000);

const EXTENSION_SOURCE = `import { createApp } from 'vue';

export const label: string = Shopware.name;
export const app = createApp;

Shopware.definitelyNotAThing;
`;

describe('scripts/extensionTooling resolvability (integration)', () => {
    let projectRoot: string;
    let administrationRoot: string;
    let extensionFile: string;

    beforeAll(() => {
        projectRoot = createTempProject();
        administrationRoot = path.join(projectRoot, 'src/Administration/Resources/app/administration');

        writeTree(administrationRoot, {
            // The host type surface, reached only through the ambient package, and
            // itself importing a host module as `src/…` — the import that only the
            // farm's `src` link can resolve.
            'extension-tooling/admin-types.d.ts': "import '../src/global.types';\n",
            'src/global.types.ts': `import type { Client } from 'src/core/client';

declare global {
    const Shopware: Client;
}

export {};
`,
            'src/core/client.ts': 'export interface Client {\n    name: string;\n}\n',
            'node_modules/vue/package.json': '{ "name": "vue", "version": "3.5.22", "types": "index.d.ts" }\n',
            'node_modules/vue/index.d.ts': 'export declare function createApp(): void;\n',
            'node_modules/.bin/eslint': '#!/usr/bin/env node\n',
            'node_modules/@types/node/package.json':
                '{ "name": "@types/node", "version": "20.0.0", "types": "index.d.ts" }\n',
            'node_modules/@types/node/index.d.ts': 'declare const process: { argv: string[] };\n',
        });

        writeTree(projectRoot, {
            'custom/plugins/SwagNoConfig/composer.json':
                '{ "name": "swag/no-config", "type": "shopware-platform-plugin" }\n',
            'custom/plugins/SwagNoConfig/src/Resources/app/administration/src/main.ts': EXTENSION_SOURCE,
        });

        extensionFile = path.join(projectRoot, 'custom/plugins/SwagNoConfig/src/Resources/app/administration/src/main.ts');

        buildFarm(projectRoot, administrationRoot);
    });

    afterAll(() => {
        removeTempProject(projectRoot);
    });

    const typeCheckWithoutAnyConfig = (): string => {
        const result = spawnSync(
            path.join(REAL_ADMINISTRATION_ROOT, 'node_modules/.bin/tsc'),
            [
                '--noEmit',
                '--pretty',
                'false',
                '--strict',
                '--target',
                'es2023',
                '--module',
                'commonjs',
                '--moduleResolution',
                'node',
                extensionFile,
            ],
            { cwd: path.dirname(extensionFile), encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 },
        );

        return `${result.stdout}${result.stderr}`;
    };

    it('declares the global Shopware object without any config in the extension', () => {
        expect(typeCheckWithoutAnyConfig()).not.toContain("Cannot find name 'Shopware'");
    });

    it('gives Shopware the real host type rather than any', () => {
        // The distinction that matters: an `any` or error-typed Shopware would
        // accept this silently, and the editor would offer no completion.
        expect(typeCheckWithoutAnyConfig()).toContain(
            "error TS2339: Property 'definitelyNotAThing' does not exist on type 'Client'",
        );
    });

    it('resolves the host sources that the type surface itself imports', () => {
        // `src/core/client` is imported by the host, not by the extension: without
        // the `src` link the type surface collapses into the error type.
        expect(typeCheckWithoutAnyConfig()).not.toContain("Cannot find module 'src/core/client'");
    });

    it('resolves a host package the extension imports', () => {
        expect(typeCheckWithoutAnyConfig()).not.toContain("Cannot find module 'vue'");
    });

    it('exposes the ESLint binary of the installed Administration to the extension', () => {
        // What an editor's ESLint integration finds by walking up from the file.
        expect(fs.readFileSync(path.join(projectRoot, 'node_modules/.bin/eslint'), 'utf8')).toContain('#!/usr/bin/env node');
    });
});
