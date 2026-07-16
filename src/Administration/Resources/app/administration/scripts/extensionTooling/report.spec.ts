/**
 * @sw-package framework
 *
 * picocolors disables color on non-TTY stdout (jest), so these assertions run
 * against plain text.
 */

import { describeInclusionSteps, renderCheckReport, renderSetupReport } from './report';
import type { CheckExtensionsResult, ExtensionCheckResult, ToolRunResult } from './check';
import type { SetupExtensionToolingResult } from './setup';
import type { ExtensionToolingProject } from './shared';

function project(name: string, overrides: Partial<ExtensionToolingProject> = {}): ExtensionToolingProject {
    return {
        name,
        technicalNames: [name],
        basePath: `custom/plugins/${name}`,
        sourcePaths: [],
        vendor: false,
        bridged: false,
        tsconfig: null,
        eslintConfig: null,
        tsMode: 'managed',
        eslintMode: 'managed',
        checkTsconfig: '',
        ...overrides,
    };
}

function run(status: ToolRunResult['status'], overrides: Partial<ToolRunResult> = {}): ToolRunResult {
    return { status, output: '', durationMs: 1000, findings: 0, ...overrides };
}

function extension(project_: ExtensionToolingProject, overrides: Partial<ExtensionCheckResult> = {}): ExtensionCheckResult {
    return {
        project: project_,
        tsMode: overrides.tsMode ?? project_.tsMode,
        eslintMode: overrides.eslintMode ?? project_.eslintMode,
        typescript: overrides.typescript ?? run('passed'),
        eslint: overrides.eslint ?? run('passed'),
    };
}

function report(results: ExtensionCheckResult[], overrides: Partial<CheckExtensionsResult> = {}, verbose = false): string {
    return renderCheckReport({ results, fatalDiagnostics: [], warnings: [], exitCode: 0, ...overrides }, { verbose });
}

describe('scripts/extensionTooling/report renderCheckReport', () => {
    it('summarizes many technical names to a count instead of dumping every bundle', () => {
        const commercial = project('SwagCommercial', {
            vendor: true,
            tsMode: 'unmanaged',
            eslintMode: 'unmanaged',
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

    it('shows the one-command bridge for unmanaged custom/plugins extensions', () => {
        const output = report([
            extension(project('Custom', { tsMode: 'unmanaged', eslintMode: 'unmanaged' }), {
                typescript: run('unmanaged'),
                eslint: run('unmanaged'),
            }),
        ]);

        expect(output).toContain('⊘ SKIPPED');
        expect(output).toContain("isn't checked with the Shopware preset yet");
        expect(output).toContain('--shim=Custom');
        expect(output).not.toContain('extension-tooling/README.md');
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
        expect(output).toContain('1 checked · 0 with findings · 0 skipped · exit 0');
    });

    it('renders a red verdict and counts findings and skips', () => {
        const output = report(
            [
                extension(project('Broken'), { typescript: run('failed', { findings: 1, output: 'x' }) }),
                extension(project('Skipped', { tsMode: 'unmanaged', eslintMode: 'unmanaged' }), {
                    typescript: run('unmanaged'),
                    eslint: run('unmanaged'),
                }),
            ],
            { exitCode: 1 },
        );

        expect(output).toContain('✖');
        expect(output).toContain('2 checked · 1 with findings · 1 skipped · exit 1');
    });

    it('surfaces warnings and fatal diagnostics', () => {
        const output = report([extension(project('Mine'))], {
            warnings: ['entity schema stub in place'],
            fatalDiagnostics: ['vue-tsc is not installed'],
            exitCode: 1,
        });

        expect(output).toContain('Warning: entity schema stub in place');
        expect(output).toContain('Error: vue-tsc is not installed');
    });
});

describe('scripts/extensionTooling/report describeInclusionSteps', () => {
    it('gives the one-command bridge for a custom/plugins extension', () => {
        const steps = describeInclusionSteps(project('SwagPayPal', { tsMode: 'custom', eslintMode: 'custom' })).join('\n');

        expect(steps).toContain('composer admin:setup-extension-tooling -- --shim=SwagPayPal');
        expect(steps).not.toContain('README');
    });

    it('explains vendor extensions are read-only, with no shim command', () => {
        const steps = describeInclusionSteps(project('Acme', { vendor: true, basePath: 'vendor/acme/admin' })).join('\n');

        expect(steps).toContain('vendor');
        expect(steps).not.toContain('--shim');
    });
});

function setupResult(
    projects: ExtensionToolingProject[],
    overrides: Partial<SetupExtensionToolingResult> = {},
): SetupExtensionToolingResult {
    return {
        manifest: {
            version: 1,
            adminRoot: 'src/Administration/Resources/app/administration',
            entitySchemaAvailable: true,
            hostModules: {},
            rootConfigs: { tsconfig: 'managed', eslintConfig: 'managed' },
            ideBootstraps: {},
            projects,
        },
        manifestPath: 'var/admin-extension-tooling/manifest.json',
        writes: [],
        staleFiles: [],
        warnings: [],
        instructions: [],
        changed: false,
        ...overrides,
    };
}

describe('scripts/extensionTooling/report renderSetupReport', () => {
    const managed = project('FroshTools');
    const customPlugin = project('SwagPayPal', {
        tsMode: 'custom',
        eslintMode: 'custom',
        tsconfig: 'custom/plugins/SwagPayPal/src/Resources/app/administration/tsconfig.json',
        eslintConfig: 'custom/plugins/SwagPayPal/src/Resources/app/administration/eslint.config.mjs',
    });
    const bridgedPlugin = project('Bridged', {
        tsMode: 'custom',
        eslintMode: 'custom',
        bridged: true,
        tsconfig: 'custom/plugins/Bridged/src/Resources/app/administration/tsconfig.json',
        eslintConfig: 'custom/plugins/Bridged/src/Resources/app/administration/eslint.config.mjs',
    });

    it('classifies ready / bridged / custom and shows inline next-steps by default', () => {
        const output = renderSetupReport(
            setupResult([
                managed,
                customPlugin,
                bridgedPlugin,
            ]),
        );

        expect(output).toContain('✔ ready');
        expect(output).toContain('● bridged');
        expect(output).toContain('● custom');
        expect(output).toContain('Next steps');
        expect(output).toContain('--shim=SwagPayPal');
    });

    it('collapses the full IDE instruction block unless --explain is passed', () => {
        const withInstructions = setupResult([managed], {
            instructions: ['PhpStorm (configure once, Settings → Languages & Frameworks): …'],
        });

        expect(renderSetupReport(withInstructions)).not.toContain('Settings → Languages');
        expect(renderSetupReport(withInstructions, { explain: true })).toContain('Settings → Languages');
    });

    it('confirms a fresh bridge after --shim', () => {
        const output = renderSetupReport(setupResult([bridgedPlugin]), { shim: 'Bridged' });

        expect(output).toContain('✔ Bridged Bridged');
        expect(output).toContain('.shopware-admin/ bridge');
    });

    it('reads "Configs up to date" when nothing changed, lists stale writes under --check', () => {
        expect(renderSetupReport(setupResult([managed]))).toContain('Configs up to date');

        const stale = setupResult([managed], {
            changed: true,
            writes: [{ file: 'tsconfig.json', state: 'created' }],
        });

        expect(renderSetupReport(stale, { checkOnly: true })).toContain('would create: tsconfig.json');
    });
});
