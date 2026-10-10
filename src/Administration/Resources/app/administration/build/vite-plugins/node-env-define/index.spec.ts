/**
 * @sw-package framework
 */
import { execFileSync } from 'child_process';
import path from 'path';

type TransformResult = {
    name: string;
    apply: string;
    filter: { id: { exclude: string }; code: string };
    code: string;
    hasMap: boolean;
};

/**
 * Vite 8 is published as ESM only, which Jest cannot load. The plugin therefore runs with the real Vite in a separate
 * Node process.
 */
function transform(code: string, config: object, env: Record<string, string> = {}): TransformResult {
    const script = `
        const { createJiti } = require('jiti');
        const jiti = createJiti(${JSON.stringify(__filename)});

        (async () => {
            const { default: NodeEnvDefinePlugin } = await jiti.import(${JSON.stringify(path.join(__dirname, 'index.ts'))});
            const plugin = NodeEnvDefinePlugin();
            plugin.configResolved(${JSON.stringify(config)});
            const result = await plugin.transform.handler(${JSON.stringify(code)}, '/src/core/application.ts');

            process.stdout.write(JSON.stringify({
                name: plugin.name,
                apply: plugin.apply,
                filter: { id: { exclude: String(plugin.transform.filter.id.exclude) }, code: plugin.transform.filter.code },
                code: result.code,
                hasMap: !!result.map,
            }));
        })();
    `;

    const parentEnv = { ...process.env };
    delete parentEnv.NODE_ENV;

    return JSON.parse(
        execFileSync(process.execPath, ['-e', script], {
            cwd: path.resolve(__dirname, '../../..'),
            env: { ...parentEnv, ...env },
            encoding: 'utf8',
        }),
    ) as TransformResult;
}

const source = `
const isDevelopmentMode = process.env.NODE_ENV === 'development';
function readShadowed(process) {
    return process.env.NODE_ENV;
}
`;

describe('build/vite-plugins/node-env-define', () => {
    it('only runs in the dev server for source modules which use process.env.NODE_ENV', () => {
        const result = transform(source, { mode: 'development' });

        expect(result.name).toBe('shopware-vite-plugin-node-env-define');
        expect(result.apply).toBe('serve');
        expect(result.filter).toEqual({ id: { exclude: '/\\/node_modules\\//' }, code: 'process.env.NODE_ENV' });
    });

    it('replaces the global process.env.NODE_ENV with the mode before the polyfills inject process', () => {
        const result = transform(source, { mode: 'development' });

        expect(result.code).toContain('const isDevelopmentMode = "development" === "development";');
        expect(result.hasMap).toBe(true);
    });

    it('keeps process.env.NODE_ENV of a local process binding untouched', () => {
        const result = transform(source, { mode: 'development' });

        expect(result.code).toContain('return process.env.NODE_ENV;');
    });

    it('prefers the NODE_ENV environment variable over the mode', () => {
        const result = transform(source, { mode: 'development' }, { NODE_ENV: 'production' });

        expect(result.code).toContain('const isDevelopmentMode = "production" === "development";');
    });

    it('prefers a configured define over the environment', () => {
        const result = transform(
            source,
            { mode: 'development', define: { 'process.env.NODE_ENV': '"test"' } },
            { NODE_ENV: 'production' },
        );

        expect(result.code).toContain('const isDevelopmentMode = "test" === "development";');
    });
});
