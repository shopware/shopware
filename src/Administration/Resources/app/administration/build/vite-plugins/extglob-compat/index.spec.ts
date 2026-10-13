/**
 * @sw-package framework
 */
import fs from 'fs';
import path from 'path';
import { parse } from '@babel/parser';
import ExtglobCompatPlugin, { toNegatedGlobPatterns } from './index';

type TransformResult = { code: string; map: { sources: string[] } } | null;

type TransformContext = {
    parse: (code: string) => unknown;
    warn: jest.Mock;
};

type TransformHook = {
    filter: { id: { exclude: RegExp }; code: string };
    handler: (this: TransformContext, code: string, id: string) => TransformResult;
};

function transform(code: string, { id = '/src/app/mixin/index.js', warnOnRewrite = false } = {}) {
    const context: TransformContext = {
        parse: (source) => parse(source, { sourceType: 'module', plugins: ['estree'] }).program,
        warn: jest.fn(),
    };
    const hook = ExtglobCompatPlugin({ warnOnRewrite }).transform as unknown as TransformHook;

    return { result: hook.handler.call(context, code, id), warn: context.warn };
}

describe('build/vite-plugins/extglob-compat', () => {
    it('runs for source modules with globs in the normal plugin order', () => {
        const plugin = ExtglobCompatPlugin();
        const { filter } = plugin.transform as unknown as TransformHook;

        expect(plugin.name).toBe('shopware-vite-plugin-extglob-compat');
        expect(plugin.enforce).toBeUndefined();
        expect(filter.id.exclude.test('/node_modules/vue/index.js')).toBe(true);
        expect(filter.id.exclude.test('/src/app/mixin/index.ts')).toBe(false);
        expect(filter.code).toBe('import.meta.glob');
    });

    it.each([
        {
            pattern: './**/!(*.spec).{j,t}s',
            expected: [
                './**/*.{j,t}s',
                '!./**/*.spec.{j,t}s',
            ],
        },
        {
            pattern: './sw-login/index!(*.spec).{j,t}s',
            expected: [
                './sw-login/index*.{j,t}s',
                '!./sw-login/index*.spec.{j,t}s',
            ],
        },
        {
            pattern: './**/!(*.spec|*.test).ts',
            expected: [
                './**/*.ts',
                '!./**/{*.spec,*.test}.ts',
            ],
        },
        {
            pattern: '!./sw-login/index!(*.spec).{j,t}s',
            expected: ['!./sw-login/index*.{j,t}s'],
        },
        {
            pattern: './**/*.ts',
            expected: ['./**/*.ts'],
        },
    ])('converts "$pattern" into negated patterns', ({ pattern, expected }) => {
        expect(toNegatedGlobPatterns(pattern)).toEqual(expected);
    });

    it('rewrites a single pattern with options into an array and generates a source map', () => {
        const { result, warn } = transform(`const context = import.meta.glob('./**/!(*.spec).{j,t}s', {
    eager: true,
});`);

        expect(result?.code).toBe(`const context = import.meta.glob(["./**/*.{j,t}s", "!./**/*.spec.{j,t}s"], {
    eager: true,
});`);
        expect(result?.map.sources).toEqual(['/src/app/mixin/index.js']);
        expect(warn).not.toHaveBeenCalled();
    });

    it('rewrites a template literal into an array', () => {
        const { result } = transform('import.meta.glob(`./**/!(*.spec).ts`);');

        expect(result?.code).toBe('import.meta.glob(["./**/*.ts", "!./**/*.spec.ts"]);');
    });

    it('only replaces the extglob pattern of a multi-line array with comments', () => {
        const { result } = transform(`import.meta.glob(
    [
        // don't load the legacy modules ]
        './**/!(*.spec).ts',
        /* x */
        '!./legacy/**',
    ],
    { eager: true },
);`);

        expect(result?.code).toBe(`import.meta.glob(
    [
        // don't load the legacy modules ]
        "./**/*.ts", "!./**/*.spec.ts",
        /* x */
        '!./legacy/**',
    ],
    { eager: true },
);`);
    });

    it('rewrites patterns with a character class', () => {
        const { result } = transform(`import.meta.glob(['./[a-z]*/!(*.spec).ts']);`);

        expect(result?.code).toBe(`import.meta.glob(["./[a-z]*/*.ts", "!./[a-z]*/*.spec.ts"]);`);
    });

    it('leaves mentions in comments and strings untouched', () => {
        const { result } = transform(`// import.meta.glob('./**/!(*.spec).ts')
/* import.meta.glob('./items/!(*.spec).ts') */
const text = "import.meta.glob('./**/!(*.spec).ts')";
const context = import.meta.glob('./**/*.ts');`);

        expect(result).toBeNull();
    });

    it.each([
        './+(a|b).ts',
        './!(x)/!(*.spec).ts',
    ])('warns about the unsupported extglob pattern "%s" and keeps it', (pattern) => {
        const { result, warn } = transform(`import.meta.glob('${pattern}');`, { id: '/plugin/src/main.ts' });

        expect(result).toBeNull();
        expect(warn).toHaveBeenCalledTimes(1);
        expect(warn).toHaveBeenCalledWith(
            `Vite 8 does not match the extglob pattern "${pattern}" in /plugin/src/main.ts. Use negated patterns instead.`,
        );
    });

    it('warns about rewritten patterns with warnOnRewrite', () => {
        const { result, warn } = transform(`import.meta.glob('./**/!(*.spec).ts');`, {
            id: '/plugin/src/main.ts',
            warnOnRewrite: true,
        });

        expect(result?.code).toBe('import.meta.glob(["./**/*.ts", "!./**/*.spec.ts"]);');
        expect(warn).toHaveBeenCalledTimes(1);
        expect(warn).toHaveBeenCalledWith(
            'Vite 8 does not match the extglob pattern "./**/!(*.spec).ts" in /plugin/src/main.ts. Shopware rewrites it until 6.8, use ["./**/*.ts","!./**/*.spec.ts"] instead.',
        );
    });

    it('keeps the call around the glob', () => {
        const { result } = transform(`const keys = Object.keys(import.meta.glob('./**/!(*.spec).ts'));`);

        expect(result?.code).toBe(`const keys = Object.keys(import.meta.glob(["./**/*.ts", "!./**/*.spec.ts"]));`);
    });

    it('rewrites all globs of the module index of the Administration', () => {
        const id = path.resolve(__dirname, '../../../src/module/index.js');
        const { result, warn } = transform(fs.readFileSync(id, 'utf8'), { id });

        expect(result?.code).toContain(`import.meta.glob(["./sw-login/index*.{j,t}s", "!./sw-login/index*.spec.{j,t}s"], {`);
        expect(result?.code).toContain(`import.meta.glob([
        "./*/index*.{j,t}s", "!./*/index*.spec.{j,t}s",
        "!./sw-login/index*.{j,t}s",
        "!./sw-inactivity-login/index*.{j,t}s",
        "!./sw-sso-error/index*.{j,t}s",
    ]);`);
        expect(result?.code).not.toContain('!(');
        expect(warn).not.toHaveBeenCalled();
    });

    it('leaves modules without extglobs untouched', () => {
        expect(transform(`const context = import.meta.glob('./**/*.ts', { eager: true });`).result).toBeNull();
    });
});
