/**
 * @sw-package framework
 */

import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { extractMiniModules } from '../src/extract-modules';

describe('build/shopware-modules-generator/src/extract-modules', () => {
    it('refuses a component module whose directory is not named after the component', () => {
        const root = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-mini-modules-'));
        const moduleDir = path.join(root, 'src/module/sw-example/component/sw-renamed');

        fs.mkdirSync(path.join(moduleDir, 'src'), { recursive: true });
        fs.mkdirSync(path.join(moduleDir, 'tests'));
        fs.writeFileSync(
            path.join(moduleDir, 'index.ts'),
            "export default definePublicComponent({ name: 'sw-example-detail', load: () => import('./src/x') });\n",
        );

        try {
            expect(() => extractMiniModules(root)).toThrow(
                '"src/module/sw-example/component/sw-renamed" declares the component "sw-example-detail".',
            );
        } finally {
            fs.rmSync(root, { recursive: true, force: true });
        }
    });
});
