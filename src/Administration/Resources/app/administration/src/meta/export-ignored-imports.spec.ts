/**
 * @sw-package framework
 */

import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { globSync } from 'glob';

const ADMIN_APP_PATTERN_PREFIX = '/Resources/app/administration/';

/**
 * Import prefixes the Vite and Jest configs alias to a folder of the same name in the app root.
 */
const ALIASED_ROOTS = [
    'src/',
    'test/',
];

/**
 * Matches static imports and re-exports, including type-only ones, and dynamic `import()` / `require()` calls.
 */
const IMPORT_SPECIFIER =
    /\b(?:import|export)\s+(?<typeOnly>type\s+)?(?:[\w*{}\s,$]+?\s+from\s+)?['"](?<staticSpecifier>[^'"]+)['"]|\b(?:import|require)\s*\(\s*['"](?<dynamicSpecifier>[^'"]+)['"]\s*\)/g;

/**
 * The Administration app paths that `src/Administration/.gitattributes` marks `export-ignore`, relative to the app
 * root. These paths are missing from the Composer dist archive, so runtime code must never import from them.
 * Glob patterns, like the one for spec files, are skipped.
 *
 * @example
 * exportIgnoredPaths('/Resources/app/administration/src/meta export-ignore'); // ['src/meta']
 */
function exportIgnoredPaths(gitattributes: string): string[] {
    return gitattributes
        .split('\n')
        .map((line) => line.trim().split(/\s+/))
        .filter(
            ([pattern, ...attributes]) =>
                pattern.startsWith(ADMIN_APP_PATTERN_PREFIX) &&
                attributes.includes('export-ignore') &&
                !/[*?[]/.test(pattern),
        )
        .map(([pattern]) => pattern.slice(ADMIN_APP_PATTERN_PREFIX.length).replace(/\/$/, ''));
}

/**
 * The app-root-relative path an import specifier points to, or `null` for a package import.
 *
 * @example
 * resolveImport('../meta/data.json', 'src/core/feature.ts'); // 'src/meta/data.json'
 * resolveImport('src/meta/data.json', 'src/core/feature.ts'); // 'src/meta/data.json'
 * resolveImport('vue', 'src/core/feature.ts'); // null
 */
function resolveImport(specifier: string, importingFile: string): string | null {
    if (specifier.startsWith('./') || specifier.startsWith('../')) {
        return join(dirname(importingFile), specifier);
    }

    return ALIASED_ROOTS.some((root) => specifier.startsWith(root)) ? join(specifier) : null;
}

/**
 * Lists every runtime import of `sources` that points into one of `ignoredPaths`, as `<file> imports <specifier>`.
 * Type-only imports are skipped, since the build erases them.
 *
 * @example
 * findExportIgnoredImports({ 'src/core/feature.ts': "import a from 'src/meta/a.json';" }, ['src/meta']);
 * // ['src/core/feature.ts imports src/meta/a.json']
 */
function findExportIgnoredImports(sources: Record<string, string>, ignoredPaths: string[]): string[] {
    return Object.entries(sources).flatMap(([file, source]) =>
        [...source.matchAll(IMPORT_SPECIFIER)]
            .filter(({ groups }) => !groups?.typeOnly)
            .map(({ groups }) => groups?.staticSpecifier ?? groups?.dynamicSpecifier ?? '')
            .filter((specifier) => {
                const target = resolveImport(specifier, file);

                return (
                    target !== null && ignoredPaths.some((ignored) => target === ignored || target.startsWith(`${ignored}/`))
                );
            })
            .map((specifier) => `${file} imports ${specifier}`),
    );
}

describe('src/meta/export-ignored-imports', () => {
    const adminPath = process.env.ADMIN_PATH!;
    const ignoredPaths = exportIgnoredPaths(readFileSync(resolve(adminPath, '../../../.gitattributes'), 'utf-8'));

    it('should read the export-ignored Administration paths from .gitattributes', () => {
        expect(ignoredPaths).toEqual(
            expect.arrayContaining([
                'src/meta',
                'test',
            ]),
        );
        expect(ignoredPaths.some((path) => path.includes('*'))).toBe(false);
    });

    it('should report aliased, relative and dynamic imports into an ignored path', () => {
        const sources = {
            'src/core/a.ts': "import baseline from 'src/meta/baseline.json';",
            'src/core/b.ts': "export { data } from '../meta/data';",
            'src/app/c.js': "const helper = await import('../../test/_helper_/helper');",
            'src/core/d.ts': "import type { Baseline } from 'src/meta/baseline';\nimport { x } from 'src/metadata/x';",
            'src/core/e.ts': "import { reactive } from 'vue';\nimport feature from './feature';",
        };

        expect(
            findExportIgnoredImports(sources, [
                'src/meta',
                'test',
            ]),
        ).toEqual([
            'src/core/a.ts imports src/meta/baseline.json',
            'src/core/b.ts imports ../meta/data',
            'src/app/c.js imports ../../test/_helper_/helper',
        ]);
    });

    it('should not import from export-ignored paths in runtime code, since Composer dist archives lack them', () => {
        const files = globSync('src/**/*.{js,ts,vue}', {
            cwd: adminPath,
            ignore: [
                '**/*.spec.{js,ts}',
                '**/*.spec/**',
                '**/*.d.ts',
                ...ignoredPaths.map((path) => `${path}/**`),
            ],
        });
        const sources = Object.fromEntries(files.map((file) => [file, readFileSync(join(adminPath, file), 'utf-8')]));

        expect(files).toContain('src/core/feature.ts');
        expect(findExportIgnoredImports(sources, ignoredPaths)).toEqual([]);
    });
});
