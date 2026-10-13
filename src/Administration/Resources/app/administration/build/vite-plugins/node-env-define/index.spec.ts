/**
 * @sw-package framework
 */
import type { ResolvedConfig, Rollup } from 'vite';
import NodeEnvDefinePlugin from './index';

type TransformHook = {
    filter: { id: { exclude: RegExp }; code: string };
    handler: (code: string, id: string) => { code: string; map: Rollup.SourceMap } | null;
};

function createPlugin(config: Partial<ResolvedConfig>) {
    const plugin = NodeEnvDefinePlugin();
    (plugin.configResolved as (config: Partial<ResolvedConfig>) => void)(config);

    return { plugin, transform: plugin.transform as unknown as TransformHook };
}

describe('build/vite-plugins/node-env-define', () => {
    const originalNodeEnv = process.env.NODE_ENV;

    beforeEach(() => {
        delete process.env.NODE_ENV;
    });

    afterEach(() => {
        process.env.NODE_ENV = originalNodeEnv;
    });

    it('only runs before the other plugins in the dev server for source modules which use process.env.NODE_ENV', () => {
        const { plugin, transform } = createPlugin({ mode: 'development' });

        expect(plugin.name).toBe('shopware-vite-plugin-node-env-define');
        expect(plugin.apply).toBe('serve');
        expect(plugin.enforce).toBe('pre');
        expect(transform.filter).toEqual({ id: { exclude: /\/node_modules\// }, code: 'process.env.NODE_ENV' });
    });

    it('replaces process.env.NODE_ENV with the mode and returns a sourcemap', () => {
        const { transform } = createPlugin({ mode: 'development' });

        const result = transform.handler(
            "const isDevelopmentMode = process.env.NODE_ENV === 'development';",
            '/src/core/application.ts',
        );

        expect(result?.code).toBe('const isDevelopmentMode = "development" === \'development\';');
        expect(result?.map.sources).toEqual(['/src/core/application.ts']);
        expect(result?.map.mappings).not.toBe('');
    });

    it('replaces every occurrence', () => {
        const { transform } = createPlugin({ mode: 'development' });

        const result = transform.handler(
            'if (process.env.NODE_ENV !== "production") { log(process.env.NODE_ENV); }',
            '/src/app/main.ts',
        );

        expect(result?.code).toBe('if ("development" !== "production") { log("development"); }');
    });

    it('prefers a configured define over the environment and the mode', () => {
        process.env.NODE_ENV = 'production';
        const { transform } = createPlugin({
            mode: 'development',
            define: { 'process.env.NODE_ENV': '"test"' },
        });

        expect(transform.handler('process.env.NODE_ENV', '/src/app/main.ts')?.code).toBe('"test"');
    });

    it('prefers the NODE_ENV environment variable over the mode', () => {
        process.env.NODE_ENV = 'production';
        const { transform } = createPlugin({ mode: 'development' });

        expect(transform.handler('process.env.NODE_ENV', '/src/app/main.ts')?.code).toBe('"production"');
    });

    it('falls back to the mode', () => {
        const { transform } = createPlugin({ mode: 'staging' });

        expect(transform.handler('process.env.NODE_ENV', '/src/app/main.ts')?.code).toBe('"staging"');
    });

    it('keeps code without a full process.env.NODE_ENV untouched', () => {
        const { transform } = createPlugin({ mode: 'development' });

        expect(transform.handler('const value = process.env.NODE_ENVIRONMENT;', '/src/app/main.ts')).toBeNull();
    });
});
