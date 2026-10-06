/**
 * @sw-package framework
 */
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import overrideComponentRegisterPlugin from './index';

/**
 * The plugin hooks are typed as Vite's `ObjectHook` unions, which aren't directly callable in tests.
 * This narrows the returned object to the plain-function shape the plugin actually provides.
 */
type CallableOverridePlugin = {
    name: string;
    transform(code: string, id: string): { code: string } | null;
};

/**
 * What `fixtures/registration/probe.ts` reports after building the fixture with the real Vite:
 * the `Shopware` calls the bundle made, in order, and where the entry's marker maps back to.
 */
type ProbeResult = {
    calls: string[];
    entryMarker: {
        authoredLine: number;
        mapped: { source: string; line: number } | null;
    };
};

const execFileAsync = promisify(execFile);

function createPlugin(): CallableOverridePlugin {
    return overrideComponentRegisterPlugin({ pluginEntryFile: 'plugin' }) as unknown as CallableOverridePlugin;
}

/**
 * Builds a copy of `fixtures/registration` in a child process, where Vite's ESM build can load.
 * `extraFiles` go into the copy first - for files git cannot hold, such as anything below `node_modules`.
 */
async function buildFixture(extraFiles: Record<string, string>): Promise<ProbeResult> {
    const root = await fs.mkdtemp(path.join(os.tmpdir(), 'sw-override-register-'));

    await fs.cp(path.join(__dirname, 'fixtures/registration'), root, { recursive: true });
    await Promise.all(
        Object.entries(extraFiles).map(async ([file, content]) => {
            await fs.mkdir(path.dirname(path.join(root, file)), { recursive: true });
            await fs.writeFile(path.join(root, file), content);
        }),
    );

    // jiti runs the TypeScript probe on every supported node; native type stripping needs node >= 22.6.
    const jitiDir = path.dirname(require.resolve('jiti/package.json'));
    const jitiPackage = JSON.parse(await fs.readFile(path.join(jitiDir, 'package.json'), 'utf8')) as {
        bin: { jiti: string };
    };
    const { stdout } = await execFileAsync(
        process.execPath,
        [path.join(jitiDir, jitiPackage.bin.jiti), path.join(root, 'probe.ts')],
        { cwd: process.cwd(), env: { ...process.env, SHOPWARE_ADMIN_ROOT: process.cwd() } },
    );

    return JSON.parse(stdout) as ProbeResult;
}

describe('build/vite-plugins/override-component-register', () => {
    it('is named so Vite can identify it', () => {
        expect(createPlugin().name).toBe('shopware-vite-plugin-override-component');
    });

    it('leaves every module but the entry untouched', () => {
        expect(createPlugin().transform('code', 'other')).toBeNull();
    });

    it('registers every override below the Vite root, sorted and before the entry code runs', async () => {
        const { calls } = await buildFixture({
            'src/node_modules/some-dependency/sw-dependency.override.vue': '<template><div /></template>\n',
        });

        // Same-named files in different folders must both register; `node_modules` must not.
        expect(calls).toEqual([
            'register src/first/sw-same-name.override.vue',
            'register src/second/sw-same-name.override.vue',
            'entry',
        ]);
    }, 60000);

    it('keeps the entry sourcemap pointing at the authored lines', async () => {
        const { entryMarker } = await buildFixture({});

        expect(entryMarker.mapped?.source).toContain('src/main.ts');
        expect(entryMarker.mapped?.line).toBe(entryMarker.authoredLine);
    }, 60000);
});
