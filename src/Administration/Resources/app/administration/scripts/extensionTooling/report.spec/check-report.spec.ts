/**
 * @sw-package framework
 */

import { checkReport, extension, owned, project, report, run } from './helpers';

describe('scripts/extensionTooling/report renderCheckReport', () => {
    it('summarizes many technical names to a count instead of dumping every bundle', () => {
        const commercial = project('SwagCommercial', {
            vendor: true,
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

    it('paints success-with-writable-skips yellow and points at --fail-on-skipped', () => {
        const skipped = extension(
            project('Custom', {
                tsconfig: owned(
                    'custom/plugins/Custom/src/Resources/app/administration/tsconfig.json',
                    'the extends chain does not reach the preset.',
                ),
                eslintConfig: owned(
                    'custom/plugins/Custom/src/Resources/app/administration/eslint.config.mjs',
                    'the config does not compose the factory.',
                ),
            }),
            { typescript: run('unmanaged'), eslint: run('unmanaged') },
        );
        const base = { results: [skipped], fatalDiagnostics: [], warnings: [], baselineUpdates: [] };

        const lenient = checkReport({ ...base, exitCode: 0 });

        expect(lenient).toContain('⚠');
        expect(lenient).toContain('skipped and NOT checked');
        expect(lenient).toContain('Pass --fail-on-skipped');

        const strict = checkReport({ ...base, exitCode: 1 }, { failOnSkipped: true });

        expect(strict).toContain('failing because --fail-on-skipped is set');
        expect(strict).toContain('exit 1');
    });

    it('does not warn about skips for vendor-only skipped extensions', () => {
        const vendorSkip = extension(project('VendorPlugin', { vendor: true }), {
            typescript: run('unmanaged'),
            eslint: run('unmanaged'),
        });

        const output = checkReport({
            results: [vendorSkip],
            fatalDiagnostics: [],
            warnings: [],
            baselineUpdates: [],
            exitCode: 0,
        });

        expect(output).not.toContain('skipped and NOT checked');
        expect(output).not.toContain('Pass --fail-on-skipped');
    });

    it('shows why each tool skipped and the one-command bridge for a plugin without a bridge', () => {
        const output = report([
            extension(
                project('Custom', {
                    tsconfig: owned(
                        'custom/plugins/Custom/src/Resources/app/administration/tsconfig.json',
                        'the extends chain does not reach the preset.',
                    ),
                    eslintConfig: owned(
                        'custom/plugins/Custom/src/Resources/app/administration/eslint.config.mjs',
                        'the config does not compose the factory.',
                    ),
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
        expect(output).toContain('composer admin:setup-extension-tooling');
        expect(output).not.toContain('extension-tooling/README.md');
    });

    it('prints the missing edit instead of re-suggesting --shim once the bridge exists', () => {
        const output = report([
            extension(
                project('Unwired', {
                    bridgePresent: true,
                    tsconfig: owned(
                        'custom/plugins/Unwired/src/Resources/app/administration/tsconfig.json',
                        'your tsconfig declares its own "files" array.',
                    ),
                }),
                { typescript: run('unmanaged') },
            ),
        ]);

        expect(output).toContain('why: your tsconfig declares its own "files" array.');
        expect(output).toContain('fix: add');
        expect(output).not.toContain('--shim');
    });

    it('marks skipped platform bundles as such without plugin-facing compose hints', () => {
        const output = report([
            extension(
                project('Storefront', {
                    basePath: 'src/Storefront',
                    eslintConfig: owned(
                        'src/Storefront/Resources/app/administration/eslint.config.mjs',
                        'the config does not compose the factory.',
                    ),
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
                extension(project('Skipped'), {
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
            commands: { typescript: ['cd /srv && node vue-tsc.js'], eslint: ['cd /srv && node eslint.js'] },
        });

        expect(
            checkReport({ results: [result], fatalDiagnostics: [], warnings: [], baselineUpdates: [], exitCode: 0 }),
        ).not.toContain('$ cd /srv');
        expect(
            checkReport(
                { results: [result], fatalDiagnostics: [], warnings: [], baselineUpdates: [], exitCode: 0 },
                { showCommands: true },
            ),
        ).toContain('$ cd /srv && node vue-tsc.js');
    });

    it('shows target-to-config routing only in verbose output', () => {
        const mine = project('Mine');
        const result = extension(mine, {
            coverage: [
                {
                    target: mine.targets[0],
                    runtimeConfig: 'var/admin-extension-tooling/projects/mine.json',
                    specConfig: 'var/admin-extension-tooling/projects/mine-specs.json',
                    eslintConfig: 'eslint.config.mjs',
                },
            ],
        });
        const concise = checkReport({
            results: [result],
            fatalDiagnostics: [],
            warnings: [],
            baselineUpdates: [],
            exitCode: 0,
        });
        const verbose = checkReport(
            { results: [result], fatalDiagnostics: [], warnings: [], baselineUpdates: [], exitCode: 0 },
            { verbose: true },
        );

        expect(concise).not.toContain('target Mine');
        expect(verbose).toContain('target Mine · custom/plugins/Mine/src');
        expect(verbose).toContain('runtime: var/admin-extension-tooling/projects/mine.json');
    });

    it('qualifies a vacuous TypeScript pass and points at the JS-to-TS next step', () => {
        const output = report([extension(project('JsOnly'), { typescript: run('no-files', { durationMs: 0 }) })]);

        expect(output).toContain('✔ passed (0 TypeScript files — .js is not type-checked)');
        expect(output).toContain('passed*');
        expect(output).toContain('* no TypeScript files — .js is not type-checked');
        expect(output).toContain('rename a .js source to .ts');
    });

    it('prints the fix → baseline handoff only after --fix when findings remain', () => {
        const failing = extension(project('Plug'), { eslint: run('failed', { findings: 3, newFindings: 3 }) });
        const base = { results: [failing], fatalDiagnostics: [], warnings: [], baselineUpdates: [], exitCode: 1 };

        const afterFix = checkReport({ ...base }, { fix: true });

        expect(afterFix).toContain('deprecation codemods');
        expect(afterFix).toContain('composer admin:check-extensions -- --update-baseline');

        expect(checkReport({ ...base })).not.toContain('Accept the findings that remain as a baseline');
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
