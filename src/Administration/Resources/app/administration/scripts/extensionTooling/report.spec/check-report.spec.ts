/**
 * @sw-package framework
 */

import { renderCheckReport } from '../report';
import { extension, project, report, resolution, run } from './helpers';

describe('scripts/extensionTooling/report renderCheckReport', () => {
    it('summarizes many technical names to a count instead of dumping every bundle', () => {
        const commercial = project('SwagCommercial', {
            vendor: true,
            ts: resolution('unmanaged'),
            eslint: resolution('unmanaged'),
            technicalNames: Array.from({ length: 36 }, (_, index) => `module-${index}`),
        });
        const output = report([
            extension(commercial, { typescript: run('unmanaged'), eslint: run('unmanaged') }),
        ]);

        expect(output).toContain('SwagCommercial');
        expect(output).toContain('(36 modules)');
        expect(output).not.toContain('module-0');
        expect(output).not.toContain('module-35');
    });

    it('shows why each tool skipped and the one-command bridge for a plugin without a bridge', () => {
        const output = report([
            extension(
                project('Custom', {
                    tsconfig: 'custom/plugins/Custom/src/Resources/app/administration/tsconfig.json',
                    eslintConfig: 'custom/plugins/Custom/src/Resources/app/administration/eslint.config.mjs',
                    ts: resolution('unmanaged', {
                        reason: 'not-extending',
                        detail: 'the extends chain does not reach the preset.',
                    }),
                    eslint: resolution('unmanaged', {
                        reason: 'factory-not-composed',
                        detail: 'the config does not compose the factory.',
                    }),
                }),
                {
                    typescript: run('unmanaged'),
                    eslint: run('unmanaged'),
                },
            ),
        ]);

        expect(output).toContain('⊘ skipped');
        expect(output).toContain('why: the extends chain does not reach the preset.');
        expect(output).toContain("isn't checked with the Shopware preset yet");
        expect(output).toContain('--shim=Custom');
        expect(output).not.toContain('extension-tooling/README.md');
    });

    it('prints the missing edit instead of re-suggesting --shim once the bridge exists', () => {
        const output = report([
            extension(
                project('Unwired', {
                    bridgePresent: true,
                    tsconfig: 'custom/plugins/Unwired/src/Resources/app/administration/tsconfig.json',
                    ts: resolution('unmanaged', {
                        reason: 'files-override',
                        detail: 'your tsconfig declares its own "files" array.',
                    }),
                }),
                { typescript: run('unmanaged') },
            ),
        ]);

        expect(output).toContain('why: your tsconfig declares its own "files" array.');
        expect(output).toContain('fix: remove "files" from the plugin tsconfig');
        expect(output).not.toContain('--shim');
    });

    it('marks skipped platform bundles as such without plugin-facing compose hints', () => {
        const output = report([
            extension(
                project('Storefront', {
                    basePath: 'src/Storefront',
                    eslintConfig: 'src/Storefront/Resources/app/administration/eslint.config.mjs',
                    eslint: resolution('unmanaged', { reason: 'factory-not-composed' }),
                }),
                { eslint: run('unmanaged') },
            ),
        ]);

        expect(output).toContain('platform bundle — its own config decides composition');
        expect(output).not.toContain('<administration>');
        expect(output).not.toContain('--shim');
    });

    it('prints raw tool output for failures but not for passing tools', () => {
        const output = report(
            [
                extension(project('Mine'), {
                    typescript: run('failed', { findings: 3, output: 'src/main.ts:12 error TS2322: bad' }),
                    eslint: run('passed', { output: 'should-not-appear' }),
                }),
            ],
            { exitCode: 1 },
        );

        expect(output).toContain('✖ 3 finding(s)');
        expect(output).toContain('error TS2322');
        expect(output).not.toContain('should-not-appear');
    });

    it('reveals passing output only in verbose mode', () => {
        const results = [extension(project('Mine'), { typescript: run('passed', { output: 'all-good-detail' }) })];

        expect(report(results)).not.toContain('all-good-detail');
        expect(report(results, {}, true)).toContain('all-good-detail');
    });

    it('renders a green verdict and counts when everything is clean', () => {
        const output = report([extension(project('Mine'))], { exitCode: 0 });

        expect(output).toContain('✔');
        expect(output).toContain('1 checked · 0 with findings · 0 extensions skipped · exit 0');
    });

    it('renders a red verdict and counts findings and skips', () => {
        const output = report(
            [
                extension(project('Broken'), { typescript: run('failed', { findings: 1, output: 'x' }) }),
                extension(project('Skipped', { ts: resolution('unmanaged'), eslint: resolution('unmanaged') }), {
                    typescript: run('unmanaged'),
                    eslint: run('unmanaged'),
                }),
            ],
            { exitCode: 1 },
        );

        expect(output).toContain('✖');
        expect(output).toContain('2 checked (2 tools skipped) · 1 with findings · 1 extension skipped · exit 1');
    });

    it('surfaces warnings and fatal diagnostics, cause before the extensions', () => {
        const output = report([extension(project('Mine'))], {
            warnings: ['entity schema stub in place'],
            fatalDiagnostics: ['vue-tsc is not installed'],
            exitCode: 1,
        });

        expect(output).toContain('Warning: entity schema stub in place');
        expect(output).toContain('Error: vue-tsc is not installed');
        expect(output.indexOf('Error: vue-tsc is not installed')).toBeLessThan(output.indexOf('Mine'));
    });

    it('prints reproduction commands only with --show-commands', () => {
        const result = extension(project('Mine'), {
            commands: { typescript: 'cd /srv && node vue-tsc.js', eslint: 'cd /srv && node eslint.js' },
        });

        expect(
            renderCheckReport({ results: [result], fatalDiagnostics: [], warnings: [], baselineUpdates: [], exitCode: 0 }),
        ).not.toContain('$ cd /srv');
        expect(
            renderCheckReport(
                { results: [result], fatalDiagnostics: [], warnings: [], baselineUpdates: [], exitCode: 0 },
                { showCommands: true },
            ),
        ).toContain('$ cd /srv && node vue-tsc.js');
    });

    it('qualifies a vacuous TypeScript pass instead of a bare green', () => {
        const output = report([extension(project('JsOnly'), { typescript: run('no-files', { durationMs: 0 }) })]);

        expect(output).toContain('✔ passed (0 TypeScript files — .js is not type-checked)');
        expect(output).toContain('passed*');
        expect(output).toContain('* no TypeScript files — .js is not type-checked');
    });

    it('renders blocked TypeScript runs with their cause', () => {
        const output = report([extension(project('Mine'), { typescript: run('blocked', { durationMs: 0 }) })], {
            exitCode: 1,
        });

        expect(output).toContain('⊘ blocked');
        expect(output).toContain('(entity schema missing)');
        expect(output).toContain('blocked');
    });
});
