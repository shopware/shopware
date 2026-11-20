/**
 * @sw-package framework
 */
import type { ResolvedConfig, Rollup } from 'vite';
import { parse } from '@babel/parser';
import NodeEnvDefinePlugin from './index';

type TransformResult = { code: string; map: Rollup.SourceMap } | null;

type TransformContext = {
    parse: (code: string, options: { lang: 'js' | 'ts' }) => unknown;
};

type TransformHook = {
    filter: { id: { exclude: RegExp }; code: string };
    handler: (this: TransformContext, code: string, id: string) => TransformResult;
};

// Like the parser of Rolldown, it only reads TypeScript syntax with `lang: 'ts'`
const context: TransformContext = {
    parse: (code, { lang }) =>
        parse(code, { sourceType: 'module', plugins: lang === 'ts' ? ['estree', 'typescript'] : ['estree'] }).program,
};

function createPlugin(config: Partial<ResolvedConfig>) {
    const plugin = NodeEnvDefinePlugin();
    (plugin.configResolved as (config: Partial<ResolvedConfig>) => void)(config);
    const hook = plugin.transform as unknown as TransformHook;

    return {
        plugin,
        transform: {
            filter: hook.filter,
            handler: (code: string, id: string) => hook.handler.call(context, code, id),
        },
    };
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

    it('reads the TypeScript syntax of TypeScript sources', () => {
        const { transform } = createPlugin({ mode: 'development' });

        const result = transform.handler(
            "const mode = process.env.NODE_ENV as string;\nconst isDev: boolean = mode === 'development';",
            '/src/core/application.ts?v=1',
        );

        expect(result?.code).toBe('const mode = "development" as string;\nconst isDev: boolean = mode === \'development\';');
    });

    it('replaces process.env.NODE_ENV in optional chains', () => {
        const { transform } = createPlugin({ mode: 'development' });

        expect(transform.handler("process?.env?.NODE_ENV !== 'test';", '/src/core/factory/http.factory.js')?.code).toBe(
            '"development" !== \'test\';',
        );
    });

    it.each([
        "process.env.NODE_ENV = 'production';",
        "process.env.NODE_ENV += '-local';",
        'delete process.env.NODE_ENV;',
        "[process.env.NODE_ENV] = ['production'];",
        "globalThis.process.env.NODE_ENV === 'production';",
        'const message = "set process.env.NODE_ENV first";',
        '// process.env.NODE_ENV is replaced in the bundled dev mode',
    ])('keeps code valid that does not read process.env.NODE_ENV: %s', (code) => {
        const { transform } = createPlugin({ mode: 'development' });

        expect(transform.handler(code, '/src/app/main.js')).toBeNull();
    });

    it('replaces the reads next to a write in the same module', () => {
        const { transform } = createPlugin({ mode: 'development' });

        expect(
            transform.handler("if (!process.env.NODE_ENV) { process.env.NODE_ENV = 'production'; }", '/src/app/main.js')
                ?.code,
        ).toBe('if (!"development") { process.env.NODE_ENV = \'production\'; }');
    });

    it('keeps code without a full process.env.NODE_ENV untouched', () => {
        const { transform } = createPlugin({ mode: 'development' });

        expect(transform.handler('const value = process.env.NODE_ENVIRONMENT;', '/src/app/main.ts')).toBeNull();
    });
});
