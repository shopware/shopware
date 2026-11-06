/**
 * @sw-package framework
 */

import { getLintableFiles } from './changed-files';

function statusOutput(...entries: string[]): string {
    return `${entries.join('\0')}\0`;
}

describe('scripts/lint-deprecations/changed-files', () => {
    it('keeps changed script, component and template files', () => {
        expect(
            getLintableFiles(
                statusOutput(
                    ' M src/app/a.js',
                    'M  src/app/b.ts',
                    '?? src/app/c.vue',
                    'A  src/app/d.html.twig',
                    ' M src/app/e.scss',
                    ' M src/app/f.json',
                ),
            ),
        ).toEqual([
            'src/app/a.js',
            'src/app/b.ts',
            'src/app/c.vue',
            'src/app/d.html.twig',
        ]);
    });

    it('keeps every untracked file inside a new directory', () => {
        expect(
            getLintableFiles(
                statusOutput('?? src/app/component/new-dir/index.ts', '?? src/app/component/new-dir/new-dir.html.twig'),
            ),
        ).toEqual([
            'src/app/component/new-dir/index.ts',
            'src/app/component/new-dir/new-dir.html.twig',
        ]);
    });

    it('skips deleted files', () => {
        expect(getLintableFiles(statusOutput(' D src/app/a.ts', 'D  src/app/b.ts', ' M src/app/c.ts'))).toEqual([
            'src/app/c.ts',
        ]);
    });

    it('skips files of the deprecation registry', () => {
        expect(getLintableFiles(statusOutput(' M src/app/deprecation-registry/index.ts', ' M src/app/a.ts'))).toEqual([
            'src/app/a.ts',
        ]);
    });

    it('ignores empty entries', () => {
        expect(getLintableFiles('\0\0 M src/app/a.ts\0\0')).toEqual(['src/app/a.ts']);
    });

    it('keeps the new path of a renamed or copied file and skips its original path', () => {
        expect(
            getLintableFiles(statusOutput('R  src/app/new.ts', 'src/app/old.ts', 'C  src/app/copy.ts', 'src/app/a.ts')),
        ).toEqual([
            'src/app/new.ts',
            'src/app/copy.ts',
        ]);
    });

    it('keeps paths with spaces unquoted', () => {
        expect(getLintableFiles(statusOutput(' M src/app/with space.ts'))).toEqual(['src/app/with space.ts']);
    });

    it('strips the working directory prefix from repository root paths', () => {
        const prefix = 'src/Administration/Resources/app/administration/';

        expect(
            getLintableFiles(
                statusOutput(` M ${prefix}src/app/a.ts`, ` M ${prefix}src/app/deprecation-registry/index.ts`),
                prefix,
            ),
        ).toEqual([
            'src/app/a.ts',
        ]);
    });
});
