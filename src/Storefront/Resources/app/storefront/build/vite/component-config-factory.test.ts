// @vitest-environment node
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { afterAll, beforeAll, describe, expect, it, vi } from 'vitest';
import { createComponentBuildConfig } from './component-config-factory';

describe('createComponentBuildConfig', () => {
    let fixtureRoot: string;

    beforeAll(() => {
        fixtureRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'component-config-factory-'));
    });

    afterAll(() => {
        fs.rmSync(fixtureRoot, { recursive: true, force: true });
    });

    it('normalizes script and style entries while applying the extension namespace', async () => {
        const componentRoot = path.join(fixtureRoot, 'extension-components');
        fs.mkdirSync(path.join(componentRoot, 'Sw/Nested/Action'), { recursive: true });
        fs.mkdirSync(path.join(componentRoot, 'Sw/Flat'), { recursive: true });
        fs.writeFileSync(path.join(componentRoot, 'Sw/Nested/Action/index.ts'), 'export default 1;');
        fs.writeFileSync(path.join(componentRoot, 'Sw/Nested/Action/index.scss'), '.action {}');
        fs.writeFileSync(path.join(componentRoot, 'Sw/Flat.js'), 'export default 2;');
        fs.writeFileSync(path.join(componentRoot, 'Sw/Flat.css'), '.flat {}');

        const config = await createComponentBuildConfig({
            componentRoot,
            outDir: path.join(fixtureRoot, 'extension-output'),
            namespace: 'Demo',
            storefrontAppDir: path.join(fixtureRoot, 'app'),
            coreStorefrontAppDir: path.join(fixtureRoot, 'core'),
            sourcemap: false,
        });
        const input = config.build?.rolldownOptions?.input;

        expect(input).toEqual(expect.objectContaining({
            'Demo/Sw/Nested/Action': path.join(componentRoot, 'Sw/Nested/Action/index.ts'),
            'Demo/Sw/Nested/Action.scss': path.join(componentRoot, 'Sw/Nested/Action/index.scss'),
            'Demo/Sw/Flat': path.join(componentRoot, 'Sw/Flat.js'),
            'Demo/Sw/Flat.css': '\0sw-plain-css:Demo/Sw/Flat.css',
        }));
    });

    it('does not add a namespace prefix to core component entries', async () => {
        const componentRoot = path.join(fixtureRoot, 'core-components');
        fs.mkdirSync(path.join(componentRoot, 'Sw/Foo'), { recursive: true });
        fs.writeFileSync(path.join(componentRoot, 'Sw/Foo/index.js'), 'export default 1;');

        const config = await createComponentBuildConfig({
            componentRoot,
            outDir: path.join(fixtureRoot, 'core-output'),
            namespace: 'Storefront',
            storefrontAppDir: path.join(fixtureRoot, 'app'),
            coreStorefrontAppDir: path.join(fixtureRoot, 'core'),
            sourcemap: false,
        });
        const input = config.build?.rolldownOptions?.input;

        expect(input).toEqual(expect.objectContaining({
            'Sw/Foo': path.join(componentRoot, 'Sw/Foo/index.js'),
        }));
        expect(input).not.toHaveProperty('Storefront/Sw/Foo');
    });

    it('warns about duplicate normalized entry names without failing config creation', async () => {
        const componentRoot = path.join(fixtureRoot, 'components');
        const componentDirectory = path.join(componentRoot, 'Wusel');
        fs.mkdirSync(path.join(componentDirectory, 'Foo'), { recursive: true });
        fs.writeFileSync(path.join(componentDirectory, 'Foo.js'), 'export default 1;');
        fs.writeFileSync(path.join(componentDirectory, 'Foo/index.js'), 'export default 2;');
        fs.writeFileSync(path.join(componentDirectory, 'Foo.scss'), '.foo {}');
        fs.writeFileSync(path.join(componentDirectory, 'Foo/index.scss'), '.foo {}');

        const warning = vi.spyOn(console, 'warn').mockImplementation(() => undefined);

        try {
            const config = await createComponentBuildConfig({
                componentRoot,
                outDir: path.join(fixtureRoot, 'output'),
                namespace: 'Demo',
                storefrontAppDir: path.join(fixtureRoot, 'app'),
                coreStorefrontAppDir: path.join(fixtureRoot, 'core'),
                sourcemap: false,
            });

            expect(config.build?.rolldownOptions?.input).toHaveProperty('Demo/Wusel/Foo');
            expect(config.build?.rolldownOptions?.input).toHaveProperty('Demo/Wusel/Foo.scss');
            expect(warning).toHaveBeenCalledTimes(2);
            expect(warning).toHaveBeenCalledWith(expect.stringContaining('JavaScript entry "Demo/Wusel/Foo"'));
            expect(warning).toHaveBeenCalledWith(expect.stringContaining('Style entry "Demo/Wusel/Foo.scss"'));
        } finally {
            warning.mockRestore();
        }
    });
});
