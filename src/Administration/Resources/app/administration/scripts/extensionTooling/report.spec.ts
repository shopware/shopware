/**
 * @sw-package framework
 *
 * picocolors disables color on non-TTY stdout (jest), so these assertions run
 * against plain text.
 */

import { describeNextStep, describeToolGuidance, renderCheckReport, renderSetupReport } from './report';
import type { CheckExtensionsResult, ExtensionCheckResult, ToolRunResult } from './check';
import type { SetupExtensionToolingResult } from './setup';
import type { ExtensionToolingProject, ModeResolution } from './shared';

function resolution(mode: ModeResolution['mode'], overrides: Partial<ModeResolution> = {}): ModeResolution {
    return { mode, verified: true, ...overrides };
}

function project(name: string, overrides: Partial<ExtensionToolingProject> = {}): ExtensionToolingProject {
    return {
        name,
        technicalNames: [name],
        basePath: `custom/plugins/${name}`,
        sourcePaths: [],
        vendor: false,
        bridgePresent: false,
        tsconfig: null,
        eslintConfig: null,
        ts: resolution('managed'),
        eslint: resolution('managed'),
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
        tsResolution: overrides.tsResolution ?? project_.ts,
        eslintResolution: overrides.eslintResolution ?? project_.eslint,
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
        expect(output).toContain('1 checked · 0 with findings · 0 skipped · exit 0');
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

describe('scripts/extensionTooling/report describeNextStep', () => {
    it('gives the one-command bridge for a custom/plugins extension without a bridge', () => {
        const steps = describeNextStep(
            project('SwagPayPal', {
                tsconfig: 'custom/plugins/SwagPayPal/src/Resources/app/administration/tsconfig.json',
                ts: resolution('unmanaged', { reason: 'not-extending' }),
            }),
        ).join('\n');

        expect(steps).toContain('composer admin:setup-extension-tooling -- --shim=SwagPayPal');
        expect(steps).not.toContain('README');
    });

    it('never re-suggests --shim once the bridge exists', () => {
        const steps = describeNextStep(
            project('Unwired', {
                bridgePresent: true,
                tsconfig: 'custom/plugins/Unwired/src/Resources/app/administration/tsconfig.json',
                ts: resolution('unmanaged', { reason: 'not-extending' }),
            }),
        ).join('\n');

        expect(steps).toContain('finish wiring it');
        expect(steps).toContain('"extends": "./.shopware-admin/tsconfig.json"');
        expect(steps).not.toContain('--shim');
    });

    it('returns nothing for extensions whose configs compose the preset', () => {
        expect(
            describeNextStep(
                project('Done', {
                    bridgePresent: true,
                    tsconfig: 'custom/plugins/Done/src/Resources/app/administration/tsconfig.json',
                    ts: resolution('custom'),
                }),
            ),
        ).toEqual([]);
    });

    it('explains vendor extensions are read-only, with no shim command', () => {
        const steps = describeNextStep(project('Acme', { vendor: true, basePath: 'vendor/acme/admin' })).join('\n');

        expect(steps).toContain('vendor');
        expect(steps).not.toContain('--shim');
    });
});

describe('scripts/extensionTooling/report describeToolGuidance', () => {
    const unwired = (overrides: Partial<ExtensionToolingProject> = {}) =>
        project('Unwired', {
            bridgePresent: true,
            tsconfig: 'custom/plugins/Unwired/src/Resources/app/administration/tsconfig.json',
            eslintConfig: 'custom/plugins/Unwired/src/Resources/app/administration/eslint.config.mjs',
            ...overrides,
        });

    it('tells a files-override plugin to drop its own files array', () => {
        const guidance = describeToolGuidance(
            unwired(),
            'TypeScript',
            resolution('unmanaged', {
                reason: 'files-override',
                detail: 'own "files" replaces the bridge — see tsconfig.aliases.json.',
            }),
        );

        expect(guidance?.why).toContain('"files"');
        expect(guidance?.why).toContain('tsconfig.aliases.json');
        expect(guidance?.fix.join('\n')).toContain('remove "files"');
    });

    it('gives the concrete eslint compose snippet for an unwired bridge', () => {
        const guidance = describeToolGuidance(
            unwired(),
            'ESLint',
            resolution('unmanaged', { reason: 'factory-not-composed' }),
        );

        expect(guidance?.fix.join('\n')).toContain("import shopware from './.shopware-admin/eslint.mjs';");
    });

    it('returns null for composing tools and for vendor extensions', () => {
        expect(describeToolGuidance(unwired(), 'TypeScript', resolution('custom'))).toBeNull();
        expect(
            describeToolGuidance(
                project('Acme', { vendor: true, basePath: 'vendor/acme/admin' }),
                'TypeScript',
                resolution('unmanaged', { reason: 'not-extending' }),
            ),
        ).toBeNull();
    });
});

function setupResult(
    projects: ExtensionToolingProject[],
    overrides: Partial<SetupExtensionToolingResult> = {},
): SetupExtensionToolingResult {
    return {
        manifest: {
            version: 2,
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
        ts: resolution('unmanaged', { reason: 'not-extending', verified: false }),
        eslint: resolution('unmanaged', { reason: 'factory-not-composed', verified: false }),
        tsconfig: 'custom/plugins/SwagPayPal/src/Resources/app/administration/tsconfig.json',
        eslintConfig: 'custom/plugins/SwagPayPal/src/Resources/app/administration/eslint.config.mjs',
    });
    const bridgedPlugin = project('Bridged', {
        ts: resolution('custom'),
        eslint: resolution('custom'),
        bridgePresent: true,
        tsconfig: 'custom/plugins/Bridged/src/Resources/app/administration/tsconfig.json',
        eslintConfig: 'custom/plugins/Bridged/src/Resources/app/administration/eslint.config.mjs',
    });
    const unwiredPlugin = project('Unwired', {
        ts: resolution('unmanaged', { reason: 'not-extending', verified: false }),
        eslint: resolution('unmanaged', { reason: 'factory-not-composed', verified: false }),
        bridgePresent: true,
        tsconfig: 'custom/plugins/Unwired/src/Resources/app/administration/tsconfig.json',
        eslintConfig: 'custom/plugins/Unwired/src/Resources/app/administration/eslint.config.mjs',
    });

    it('classifies ready / bridged / unwired / custom and shows inline next-steps by default', () => {
        const output = renderSetupReport(
            setupResult([
                managed,
                customPlugin,
                bridgedPlugin,
                unwiredPlugin,
            ]),
        );

        expect(output).toContain('✔ ready');
        expect(output).toContain('● bridged');
        expect(output).toContain('bridge unwired');
        expect(output).toContain('● custom');
        expect(output).toContain('Next steps');
        expect(output).toContain('--shim=SwagPayPal');
        expect(output).not.toContain('--shim=Unwired');
        expect(output).toContain('finish wiring it');
    });

    it('marks statically classified bridged plugins as unverified until a check ran', () => {
        const staticallyBridged = project('Static', {
            ts: resolution('custom', { verified: false }),
            eslint: resolution('custom', { verified: false }),
            bridgePresent: true,
            tsconfig: 'custom/plugins/Static/src/Resources/app/administration/tsconfig.json',
            eslintConfig: 'custom/plugins/Static/src/Resources/app/administration/eslint.config.mjs',
        });

        expect(renderSetupReport(setupResult([staticallyBridged]))).toContain('unverified');
        expect(renderSetupReport(setupResult([bridgedPlugin]))).not.toContain('unverified');
    });

    it('collapses the full IDE instruction block unless --explain is passed', () => {
        const withInstructions = setupResult([managed], {
            instructions: ['PhpStorm (configure once, Settings → Languages & Frameworks): …'],
        });

        expect(renderSetupReport(withInstructions)).not.toContain('Settings → Languages');
        expect(renderSetupReport(withInstructions, { explain: true })).toContain('Settings → Languages');
    });

    it('confirms a fresh bridge after --shim only when the configs actually compose it', () => {
        const output = renderSetupReport(setupResult([bridgedPlugin]), { shim: 'Bridged' });

        expect(output).toContain('✔ Bridged Bridged');
        expect(output).toContain('.shopware-admin/ bridge');
    });

    it('announces one remaining step after --shim when existing configs were left alone', () => {
        const output = renderSetupReport(setupResult([unwiredPlugin]), { shim: 'Unwired' });

        expect(output).toContain('✔ Bridge created for Unwired');
        expect(output).toContain('one step left');
        expect(output).not.toContain('✔ Bridged Unwired');
    });

    it('replaces the empty state with discovery guidance instead of a green "up to date"', () => {
        const output = renderSetupReport(setupResult([]));

        expect(output).toContain('no extensions found');
        expect(output).toContain('bin/console bundle:dump');
        expect(output).not.toContain('Configs up to date');
        expect(output).not.toContain('0 extension(s)');
    });

    it('lists platform bundles in their own dim section, excluded from the count and next steps', () => {
        const storefront = project('Storefront', {
            basePath: 'src/Storefront',
            eslintConfig: 'src/Storefront/Resources/app/administration/eslint.config.mjs',
            eslint: resolution('unmanaged', { reason: 'factory-not-composed' }),
        });
        const output = renderSetupReport(
            setupResult([
                managed,
                storefront,
            ]),
        );

        expect(output).toContain('— 1 extension(s)');
        expect(output).toContain('platform   Storefront');
        expect(output).not.toContain('--shim=Storefront');
        expect(output).not.toContain('<administration>');

        const emptyWithPlatform = renderSetupReport(setupResult([storefront]));

        expect(emptyWithPlatform).toContain('no extensions found');
        expect(emptyWithPlatform).toContain('Platform bundles like Storefront');
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
