// @vitest-environment node
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';
import { loadExtensionComponentAliases } from './extension-component-aliases';

describe('loadExtensionComponentAliases', () => {
    let projectRoot: string | undefined;

    afterEach(() => {
        if (projectRoot) {
            fs.rmSync(projectRoot, { recursive: true, force: true });
            projectRoot = undefined;
        }
    });

    it('loads aliases from active bundle Vite configs', async () => {
        projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'storefront-vite-aliases-'));
        const bundleRoot = path.join(projectRoot, 'custom/plugins/Example');
        const appDir = path.join(bundleRoot, 'Resources/app/storefront');
        fs.mkdirSync(path.join(bundleRoot, 'Resources/views/components'), { recursive: true });
        fs.mkdirSync(path.join(projectRoot, 'var'), { recursive: true });
        fs.mkdirSync(appDir, { recursive: true });
        fs.writeFileSync(path.join(projectRoot, 'var/plugins.json'), JSON.stringify({
            Storefront: { basePath: 'src/Storefront' },
            Example: { basePath: 'custom/plugins/Example' },
        }));
        fs.mkdirSync(path.join(projectRoot, 'src/Storefront/Resources/app/storefront'), { recursive: true });
        fs.mkdirSync(path.join(projectRoot, 'src/Storefront/Resources/views/components'), { recursive: true });
        fs.writeFileSync(
            path.join(projectRoot, 'src/Storefront/Resources/app/storefront/vite.components.config.mts'),
            'invalid config that must not be loaded',
        );
        fs.writeFileSync(path.join(appDir, 'vite.components.config.ts'), `
            export default {
                resolve: {
                    alias: { '@modules': '/example/src/modules' },
                },
                plugins: [],
                build: { outDir: '/ignored/build/output' },
            };
        `);

        await expect(loadExtensionComponentAliases(projectRoot)).resolves.toEqual([
            { find: '@modules', replacement: '/example/src/modules' },
        ]);
    });

    it('returns no aliases when the bundle manifest is missing', async () => {
        projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'storefront-vite-aliases-'));

        await expect(loadExtensionComponentAliases(projectRoot)).resolves.toEqual([]);
    });
});
