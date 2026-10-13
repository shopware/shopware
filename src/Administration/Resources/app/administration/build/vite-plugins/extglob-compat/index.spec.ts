/**
 * @sw-package framework
 */
import fs from 'fs';
import path from 'path';
import ExtglobCompatPlugin, { toNegatedGlobPatterns } from './index';

type TransformHook = {
    filter: { id: { exclude: RegExp }; code: string };
    handler: (code: string, id: string) => { code: string; map: { sources: string[] } } | null;
};

function transform(code: string, id = '/src/app/mixin/index.ts') {
    return (ExtglobCompatPlugin().transform as TransformHook).handler(code, id);
}

describe('build/vite-plugins/extglob-compat', () => {
    it('runs before Vite resolves the globs and only for source modules with globs', () => {
        const plugin = ExtglobCompatPlugin();
        const { filter } = plugin.transform as TransformHook;

        expect(plugin.name).toBe('shopware-vite-plugin-extglob-compat');
        expect(plugin.enforce).toBe('pre');
        expect(filter.id.exclude.test('/node_modules/vue/index.js')).toBe(true);
        expect(filter.code).toBe('import.meta.glob');
    });

    it('converts a negated extglob into negated patterns', () => {
        expect(toNegatedGlobPatterns(['./**/!(*.spec).{j,t}s'])).toEqual([
            './**/*.{j,t}s',
            '!./**/*.spec.{j,t}s',
        ]);
        expect(toNegatedGlobPatterns(['./sw-login/index!(*.spec).{j,t}s'])).toEqual([
            './sw-login/index*.{j,t}s',
            '!./sw-login/index*.spec.{j,t}s',
        ]);
    });

    it('excludes all files of a negated pattern with an extglob', () => {
        expect(toNegatedGlobPatterns(['!./sw-login/index!(*.spec).{j,t}s'])).toEqual(['!./sw-login/index*.{j,t}s']);
    });

    it('keeps patterns without an extglob', () => {
        expect(toNegatedGlobPatterns(['./**/*.ts', '!./**/*.spec.ts'])).toEqual(['./**/*.ts', '!./**/*.spec.ts']);
    });

    it('rewrites the glob of a typed import.meta.glob call with options', () => {
        const result = transform(`const context = import.meta.glob<$TSFixMe>('./**/!(*.spec).{j,t}s', {
    eager: true,
});`);

        expect(result?.code).toBe(`const context = import.meta.glob<$TSFixMe>(['./**/*.{j,t}s', '!./**/*.spec.{j,t}s'], {
    eager: true,
});`);
        expect(result?.map.sources).toEqual(['/src/app/mixin/index.ts']);
    });

    it('rewrites all globs of the module index of the Administration', () => {
        const id = path.resolve(__dirname, '../../../src/module/index.js');
        const result = transform(fs.readFileSync(id, 'utf8'), id);

        expect(result?.code).toContain(`import.meta.glob(['./sw-login/index*.{j,t}s', '!./sw-login/index*.spec.{j,t}s'], {`);
        expect(result?.code).toContain(
            `import.meta.glob(['./*/index*.{j,t}s', '!./*/index*.spec.{j,t}s', '!./sw-login/index*.{j,t}s', '!./sw-inactivity-login/index*.{j,t}s', '!./sw-sso-error/index*.{j,t}s'])`,
        );
        expect(result?.code).not.toContain('!(');
    });

    it('leaves modules without extglobs untouched', () => {
        expect(transform(`const context = import.meta.glob('./**/*.ts', { eager: true });`)).toBeNull();
    });
});
