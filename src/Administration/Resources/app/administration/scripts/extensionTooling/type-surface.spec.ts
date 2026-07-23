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
});
