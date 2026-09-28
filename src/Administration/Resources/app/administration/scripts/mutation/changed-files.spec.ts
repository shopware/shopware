/**
 * @sw-package framework
 */

import { resolveMutationTargets, sourceForSpec } from './changed-files';
import type { ResolverFs } from './changed-files';
import { CHANGED_INCREMENTAL_FILE, buildStrykerArgs, parseArgs } from './changed';

function createFakeFs(files: Record<string, string>): ResolverFs {
    return {
        exists: (file) => file in files,
        readFile: (file) => files[file] ?? '',
    };
}

describe('scripts/mutation/changed-files', () => {
    describe('sourceForSpec', () => {
        it('maps a spec to the sibling source, preferring whichever extension exists', () => {
            const fs = createFakeFs({ 'src/core/helper/retry.helper.ts': '' });

            expect(sourceForSpec('src/core/helper/retry.helper.spec.js', fs)).toBe('src/core/helper/retry.helper.ts');
        });

        it('maps a spec inside a .spec directory to the parent source', () => {
            const fs = createFakeFs({ 'src/core/factory/async-component.factory.ts': '' });

            expect(sourceForSpec('src/core/factory/async-component.factory.spec/lazy.spec.ts', fs)).toBe(
                'src/core/factory/async-component.factory.ts',
            );
        });

        it('returns null for non-spec paths and for specs without a source', () => {
            const fs = createFakeFs({});

            expect(sourceForSpec('src/core/helper/retry.helper.ts', fs)).toBeNull();
            expect(sourceForSpec('src/core/helper/orphan.spec.js', fs)).toBeNull();
        });
    });

    describe('resolveMutationTargets', () => {
        it('keeps changed sources, traces specs back and deduplicates', () => {
            const fs = createFakeFs({
                'src/core/helper/retry.helper.js': 'export const retry = () => {};',
                'src/core/service/utils/sort.utils.ts': 'export const sort = () => {};',
            });

            const result = resolveMutationTargets(
                [
                    'src/core/helper/retry.helper.js',
                    'src/core/helper/retry.helper.spec.js',
                    'src/core/service/utils/sort.utils.spec.ts',
                ],
                fs,
            );

            expect(result.targets).toEqual([
                'src/core/helper/retry.helper.js',
                'src/core/service/utils/sort.utils.ts',
            ]);
            expect(result.skipped).toEqual([]);
        });

        it('ignores files outside src/ and non JS/TS files silently', () => {
            const fs = createFakeFs({
                'scripts/mutation/changed.ts': '',
                'src/module/sw-order/snippet/de-DE.json': '{}',
                'src/app/component/base/sw-card/sw-card.scss': '',
            });

            const result = resolveMutationTargets(
                [
                    'scripts/mutation/changed.ts',
                    'src/module/sw-order/snippet/de-DE.json',
                    'src/app/component/base/sw-card/sw-card.scss',
                ],
                fs,
            );

            expect(result).toEqual({ targets: [], skipped: [] });
        });

        it('skips declarations, deleted files and orphaned specs with a reason', () => {
            const fs = createFakeFs({ 'src/global.types.d.ts': '' });

            const result = resolveMutationTargets(
                [
                    'src/global.types.d.ts',
                    'src/core/helper/removed.helper.js',
                    'src/core/helper/orphan.spec.js',
                ],
                fs,
            );

            expect(result.targets).toEqual([]);
            expect(result.skipped.map((entry) => entry.file)).toEqual([
                'src/global.types.d.ts',
                'src/core/helper/removed.helper.js',
                'src/core/helper/orphan.spec.js',
            ]);
            result.skipped.forEach((entry) => expect(entry.reason).not.toBe(''));
        });

        it('keeps component entries even though their Twig template itself is not mutated', () => {
            const fs = createFakeFs({
                'src/module/sw-order/page/sw-order-list/index.js': "import template from './sw-order-list.html.twig';",
                'src/module/sw-order/page/sw-order-list/sw-order-list.html.twig': '{% block %}{% endblock %}',
            });

            const result = resolveMutationTargets(
                [
                    'src/module/sw-order/page/sw-order-list/index.spec.js',
                    'src/module/sw-order/page/sw-order-list/sw-order-list.html.twig',
                ],
                fs,
            );

            expect(result).toEqual({ targets: ['src/module/sw-order/page/sw-order-list/index.js'], skipped: [] });
        });

        it('skips import.meta.glob loaders', () => {
            const fs = createFakeFs({
                'src/core/service/api/index.ts': "const context = import.meta.glob('./**/*.ts');",
            });

            const result = resolveMutationTargets(['src/core/service/api/index.ts'], fs);

            expect(result.targets).toEqual([]);
            expect(result.skipped[0].reason).toContain('import.meta.glob');
        });
    });
});

describe('scripts/mutation/changed parseArgs', () => {
    it('defaults to trunk and forwards unknown arguments to Stryker', () => {
        expect(parseArgs([])).toEqual({ baseRef: 'trunk', listOnly: false, strykerArgs: [] });
        expect(
            parseArgs([
                '--reporters',
                'clear-text',
            ]),
        ).toEqual({
            baseRef: 'trunk',
            listOnly: false,
            strykerArgs: [
                '--reporters',
                'clear-text',
            ],
        });
    });

    it('builds the Stryker call with a dedicated incremental file unless one is forwarded', () => {
        const targets = [
            'src/core/helper/a.js',
            'src/core/helper/b.ts',
        ];

        expect(
            buildStrykerArgs(
                parseArgs([
                    '--reporters',
                    'clear-text',
                ]),
                targets,
            ),
        ).toEqual([
            'run',
            '--reporters',
            'clear-text',
            '--incrementalFile',
            CHANGED_INCREMENTAL_FILE,
            '--mutate',
            'src/core/helper/a.js,src/core/helper/b.ts',
        ]);
        expect(
            buildStrykerArgs(
                parseArgs([
                    '--incrementalFile',
                    'other.json',
                ]),
                targets,
            ),
        ).toEqual([
            'run',
            '--incrementalFile',
            'other.json',
            '--mutate',
            'src/core/helper/a.js,src/core/helper/b.ts',
        ]);
    });

    it('reads the base ref in both spellings and the list flag', () => {
        expect(
            parseArgs([
                '--base',
                'origin/trunk',
                '--list',
            ]).baseRef,
        ).toBe('origin/trunk');
        expect(parseArgs(['--base=6.7.x']).baseRef).toBe('6.7.x');
        expect(parseArgs(['--list']).listOnly).toBe(true);
    });
});
