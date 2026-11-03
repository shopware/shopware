/**
 * @sw-package framework
 *
 * `admin-types.d.ts` is the single type surface handed to extension programs.
 * The Administration's own build compiles all of `src/**`, so a `ServiceContainer`
 * augmentation declared anywhere is implicitly present there — but an extension
 * program only sees it when `admin-types.d.ts` imports the declaring module.
 * Those imports are hand-maintained, so this guard fails when a new augmentation
 * drifts out of the surface, instead of leaving extensions with false type errors
 * on real host services.
 */

import fs from 'fs';
import path from 'path';
import { toPosix } from './shared';

const adminRoot = path.resolve(__dirname, '../..');
const srcDir = path.join(adminRoot, 'src');
const adminTypesPath = path.join(adminRoot, 'extension-tooling', 'admin-types.d.ts');

describe('extension-tooling type surface', () => {
    it('imports every ServiceContainer augmentation declared under src/', () => {
        const adminTypes = fs.readFileSync(adminTypesPath, 'utf8');

        const augmentations = (fs.readdirSync(srcDir, { recursive: true }) as string[])
            .filter((file) => file.endsWith('.ts') && !file.includes('.spec.'))
            .map((file) => path.join(srcDir, file))
            .filter((file) => fs.readFileSync(file, 'utf8').includes('interface ServiceContainer'));

        // A broken walk (wrong root, empty result) must fail loudly rather than
        // vacuously pass — the base declaration plus the three module services.
        expect(augmentations.length).toBeGreaterThanOrEqual(4);

        const notImported = augmentations.filter((file) => {
            const specifier = `../${toPosix(path.relative(adminRoot, file))
                .replace(/\.ts$/, '')
                .replace(/\/index$/, '')}`;

            return !adminTypes.includes(`'${specifier}'`);
        });

        expect(notImported).toEqual([]);
    });

    it('imports the global.d.ts of every mini-module so extensions get typed overrides', () => {
        const miniModuleTypes = fs.readFileSync(path.join(adminRoot, 'extension-tooling', 'mini-modules.d.ts'), 'utf8');

        const notImported = (fs.readdirSync(srcDir, { recursive: true }) as string[])
            .filter(
                (file) =>
                    path.basename(file) === 'global.d.ts' &&
                    fs.existsSync(path.join(srcDir, path.dirname(file), 'index.ts')),
            )
            .map((file) => `../src/${toPosix(file).replace(/\.d\.ts$/, '')}`)
            .filter((specifier) => !miniModuleTypes.includes(`'${specifier}'`));

        expect(notImported).toEqual([]);
    });

    it('leaves the private components out, so an extension program types their names loosely', () => {
        const surface = ['admin-types.d.ts', 'mini-modules.d.ts']
            .map((file) => fs.readFileSync(path.join(adminRoot, 'extension-tooling', file), 'utf8'))
            .join('\n');

        // A private module's global.d.ts adds its name to PrivateComponents; with none of them in the surface,
        // `Component.override()` falls back to the untyped configuration for every private name.
        const privateTypes = (fs.readdirSync(srcDir, { recursive: true }) as string[])
            .filter((file) => path.basename(file) === 'global.d.ts')
            .filter((file) => fs.readFileSync(path.join(srcDir, file), 'utf8').includes('interface PrivateComponents'))
            .map((file) => `src/${toPosix(file).replace(/\.d\.ts$/, '')}`)
            .filter((specifier) => surface.includes(`'../${specifier}'`));

        expect(privateTypes).toEqual([]);
    });

    it('declares a shopware:<kind> module for every mini-module so extensions can import its index.ts', () => {
        const declarations = fs.readFileSync(path.join(srcDir, 'shopware-virtual-modules.d.ts'), 'utf8');

        const miniModules = (fs.readdirSync(srcDir, { recursive: true }) as string[])
            .filter((file) => path.basename(file) === 'index.ts')
            .map((file) => path.dirname(path.join(srcDir, file)))
            .filter((dir) => fs.existsSync(path.join(dir, 'src')) && fs.existsSync(path.join(dir, 'tests')));

        expect(miniModules.length).toBeGreaterThan(0);

        // A component module is published under the component's name, any other module under its directory.
        const publishedName = (dir: string) =>
            /definePublicComponent\(\{\s*name: '([^']+)'/.exec(fs.readFileSync(path.join(dir, 'index.ts'), 'utf8'))?.[1] ??
            path.basename(dir);
        const notDeclared = miniModules.filter(
            (dir) => !new RegExp(`declare module 'shopware:[a-z]+/${publishedName(dir)}'`).test(declarations),
        );

        expect(notDeclared).toEqual([]);
    });

    it('carries the native-setup macro declarations so extensions type-check swDefinePublic and friends', () => {
        const adminTypes = fs.readFileSync(adminTypesPath, 'utf8');

        expect(adminTypes).toContain("'../build/vue-setup-transform/shopware-setup-macros'");
    });

    it('carries the shopware:* module declarations so extensions can import them', () => {
        const adminTypes = fs.readFileSync(adminTypesPath, 'utf8');

        expect(adminTypes).toContain("'../src/shopware-virtual-modules'");
    });
});
