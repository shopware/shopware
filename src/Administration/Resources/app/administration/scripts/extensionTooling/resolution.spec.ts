/**
 * @sw-package framework
 */

import fs from 'fs';
import path from 'path';
import { AMBIENT_TYPES_PACKAGE, buildFarm, isManagedFarm, planFarm } from './resolution';
import { createTempProject, removeTempProject, writeTree } from './test-helpers';
import type { FarmOperation } from './resolution';

function symlinks(operations: FarmOperation[]): Record<string, string> {
    return Object.fromEntries(
        operations
            .filter((operation): operation is Extract<FarmOperation, { kind: 'symlink' }> => operation.kind === 'symlink')
            .map((operation) => [
                operation.path,
                operation.target,
            ]),
    );
}

const relative = (from: string, to: string): string => path.relative(from, to);

describe('scripts/extensionTooling/resolution', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        projectRoot = createTempProject();
        administrationRoot = path.join(projectRoot, 'src/Administration/Resources/app/administration');

        writeTree(administrationRoot, {
            'src/global.types.ts': 'export {};\n',
            'extension-tooling/admin-types.d.ts': 'declare const Shopware: { name: string };\n',
            'node_modules/vue/package.json': '{ "name": "vue" }\n',
            'node_modules/axios/package.json': '{ "name": "axios" }\n',
            'node_modules/@shopware-ag/meteor-admin-sdk/package.json': '{ "name": "@shopware-ag/meteor-admin-sdk" }\n',
            'node_modules/.bin/eslint': '#!/usr/bin/env node\n',
            'node_modules/.package-lock.json': '{}\n',
            'node_modules/@types/node/index.d.ts': 'declare const process: unknown;\n',
            'node_modules/@types/jest/index.d.ts': 'declare const jest: unknown;\n',
        });
    });

    afterEach(() => {
        removeTempProject(projectRoot);
    });

    describe('planFarm', () => {
        it('links every top-level entry of the Administration node_modules', () => {
            const plan = planFarm(projectRoot, administrationRoot);
            const links = symlinks(plan.operations);
            const farm = path.join(projectRoot, 'node_modules');
            const modules = path.join(administrationRoot, 'node_modules');

            // Complete, not curated: a curated list is provably incomplete, since an
            // extension may import any package of the Administration tree.
            expect(links[path.join(farm, 'vue')]).toBe(relative(farm, path.join(modules, 'vue')));
            expect(links[path.join(farm, 'axios')]).toBe(relative(farm, path.join(modules, 'axios')));
            expect(links[path.join(farm, '@shopware-ag')]).toBe(relative(farm, path.join(modules, '@shopware-ag')));
            expect(links[path.join(farm, '.bin')]).toBe(relative(farm, path.join(modules, '.bin')));
            expect(links[path.join(farm, '.package-lock.json')]).toBe(
                relative(farm, path.join(modules, '.package-lock.json')),
            );
        });

        it('links the Administration sources as the package its own sources import', () => {
            const links = symlinks(planFarm(projectRoot, administrationRoot).operations);

            expect(links[path.join(projectRoot, 'node_modules/src')]).toBe(
                relative(path.join(projectRoot, 'node_modules'), path.join(administrationRoot, 'src')),
            );
        });

        it('mirrors @types instead of linking it, so the ambient package can live beside the host types', () => {
            const plan = planFarm(projectRoot, administrationRoot);
            const links = symlinks(plan.operations);
            const farmTypes = path.join(projectRoot, 'node_modules/@types');

            expect(links[farmTypes]).toBeUndefined();
            expect(plan.operations).toEqual(expect.arrayContaining([{ kind: 'directory', path: farmTypes }]));
            expect(links[path.join(farmTypes, 'node')]).toBe(
                relative(farmTypes, path.join(administrationRoot, 'node_modules/@types/node')),
            );
            expect(links[path.join(farmTypes, 'jest')]).toBe(
                relative(farmTypes, path.join(administrationRoot, 'node_modules/@types/jest')),
            );
        });

        it('adds an ambient types package referencing the installed type surface', () => {
            const plan = planFarm(projectRoot, administrationRoot);
            const ambient = plan.operations.find(
                (operation): operation is Extract<FarmOperation, { kind: 'file' }> =>
                    operation.kind === 'file' && operation.path.endsWith(`${AMBIENT_TYPES_PACKAGE}/index.d.ts`),
            );

            expect(ambient?.content).toContain(
                `/// <reference path="${relative(
                    path.join(projectRoot, 'node_modules', AMBIENT_TYPES_PACKAGE),
                    path.join(administrationRoot, 'extension-tooling/admin-types.d.ts'),
                )}" />`,
            );
        });

        it('writes the self-ignoring .gitignore last, so the signature only exists on a complete farm', () => {
            const operations = planFarm(projectRoot, administrationRoot).operations;
            const last = operations[operations.length - 1];

            expect(last).toEqual({ kind: 'file', path: path.join(projectRoot, 'node_modules/.gitignore'), content: '*\n' });
        });

        it('reports entries it cannot inspect instead of planning a broken link', () => {
            fs.symlinkSync(
                path.join(administrationRoot, 'node_modules/gone'),
                path.join(administrationRoot, 'node_modules/dangling'),
            );

            const plan = planFarm(projectRoot, administrationRoot);

            expect(plan.danglingEntries).toEqual(['dangling']);
            expect(symlinks(plan.operations)[path.join(projectRoot, 'node_modules/dangling')]).toBeUndefined();
        });
    });

    describe('buildFarm', () => {
        it('writes relative link targets, so the same tree works from a container and from the host', () => {
            buildFarm(projectRoot, administrationRoot);

            // A Shopware installation is routinely built in a container and read by
            // an editor on the host, where the mount point differs.
            expect(fs.readlinkSync(path.join(projectRoot, 'node_modules/src'))).toBe(
                '../src/Administration/Resources/app/administration/src',
            );
            expect(fs.readlinkSync(path.join(projectRoot, 'node_modules/vue'))).toBe(
                '../src/Administration/Resources/app/administration/node_modules/vue',
            );
            expect(fs.readlinkSync(path.join(projectRoot, 'node_modules/@types/node'))).toBe(
                '../../src/Administration/Resources/app/administration/node_modules/@types/node',
            );
        });

        it('creates a farm whose links resolve', () => {
            const result = buildFarm(projectRoot, administrationRoot);

            expect(result.refusal).toBeNull();
            expect(result.failures).toEqual([]);
            expect(result.created).toBeGreaterThan(5);
            expect(fs.readFileSync(path.join(projectRoot, 'node_modules/vue/package.json'), 'utf8')).toContain('"vue"');
            expect(fs.readFileSync(path.join(projectRoot, 'node_modules/src/global.types.ts'), 'utf8')).toBe('export {};\n');
            expect(fs.readFileSync(path.join(projectRoot, 'node_modules/@types/node/index.d.ts'), 'utf8')).toContain(
                'process',
            );
        });

        it('makes git ignore the whole farm, including the ignore file itself', () => {
            buildFarm(projectRoot, administrationRoot);

            expect(fs.readFileSync(path.join(projectRoot, 'node_modules/.gitignore'), 'utf8')).toBe('*\n');
        });

        it('is idempotent and replaces rather than merges, so orphans are impossible', () => {
            buildFarm(projectRoot, administrationRoot);

            const orphan = path.join(projectRoot, 'node_modules/gone-package');

            fs.symlinkSync(path.join(administrationRoot, 'src'), orphan);
            fs.rmSync(path.join(administrationRoot, 'node_modules/axios'), { recursive: true });

            const second = buildFarm(projectRoot, administrationRoot);

            expect(second.refusal).toBeNull();
            expect(fs.existsSync(orphan)).toBe(false);
            expect(fs.existsSync(path.join(projectRoot, 'node_modules/axios'))).toBe(false);
            expect(fs.existsSync(path.join(projectRoot, 'node_modules/vue'))).toBe(true);
        });

        it('refuses to replace a node_modules it did not create', () => {
            fs.mkdirSync(path.join(projectRoot, 'node_modules/somebody-elses-package'), { recursive: true });
            fs.writeFileSync(
                path.join(projectRoot, 'node_modules/somebody-elses-package/index.js'),
                'module.exports = 1;\n',
            );

            const result = buildFarm(projectRoot, administrationRoot);

            expect(result.refusal).toContain('was not created by this command');
            expect(result.created).toBe(0);
            // Nothing was written: the foreign tree is still intact.
            expect(fs.existsSync(path.join(projectRoot, 'node_modules/somebody-elses-package/index.js'))).toBe(true);
        });

        it('refuses when the Administration dependencies are missing', () => {
            fs.rmSync(path.join(administrationRoot, 'node_modules'), { recursive: true });

            const result = buildFarm(projectRoot, administrationRoot);

            expect(result.refusal).toContain('npm ci');
            expect(fs.existsSync(path.join(projectRoot, 'node_modules'))).toBe(false);
        });

        it('warns when npm may own the project root, but still links', () => {
            fs.writeFileSync(path.join(projectRoot, 'package.json'), '{ "name": "shop" }\n');

            const result = buildFarm(projectRoot, administrationRoot);

            expect(result.warnings[0]).toContain('npm may manage node_modules');
            expect(result.created).toBeGreaterThan(0);
        });
    });

    describe('isManagedFarm', () => {
        it('recognises its own farm by the self-ignoring .gitignore', () => {
            expect(isManagedFarm(path.join(projectRoot, 'node_modules'))).toBe(false);

            buildFarm(projectRoot, administrationRoot);

            expect(isManagedFarm(path.join(projectRoot, 'node_modules'))).toBe(true);
        });

        it('does not claim a foreign node_modules', () => {
            const farm = path.join(projectRoot, 'node_modules');

            fs.mkdirSync(farm);
            fs.writeFileSync(path.join(farm, '.gitignore'), 'dist\n');

            expect(isManagedFarm(farm)).toBe(false);
        });
    });
});
