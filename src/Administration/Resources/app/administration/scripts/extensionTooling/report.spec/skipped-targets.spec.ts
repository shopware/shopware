/**
 * @sw-package framework
 *
 * Partial multi-root coverage: some targets run, others are skipped by their
 * own config. The skipped targets' paths and remediation must render no
 * matter whether the managed remainder passed or failed.
 */

import { extension, owned, project, report, run, target } from './helpers';

const unmanagedTarget = (feature: string, overrides: Parameters<typeof target>[1] = {}) =>
    target(`Suite${feature}`, {
        sourcePath: `custom/plugins/Suite/src/${feature}/Resources/app/administration/src`,
        tsconfig: owned(
            `custom/plugins/Suite/src/${feature}/Resources/app/administration/tsconfig.json`,
            `the ${feature} extends chain does not reach the preset.`,
        ),
        ...overrides,
    });

const partialProject = (overrides: Parameters<typeof project>[1] = {}) =>
    project('Suite', {
        targets: [
            target('SuiteCore', { sourcePath: 'custom/plugins/Suite/src/Core/Resources/app/administration/src' }),
            unmanagedTarget('FeatureA', { bridgePresent: true }),
            unmanagedTarget('FeatureB'),
        ],
        ...overrides,
    });

describe('scripts/extensionTooling/report skipped targets', () => {
    it('lists each skipped target with why and fix when the managed subset failed', () => {
        const output = report(
            [
                extension(partialProject(), {
                    typescript: run('failed', {
                        findings: 1,
                        newFindings: 1,
                        output: 'custom/plugins/Suite/src/Core/Resources/app/administration/src/main.ts(1,1): error TS2322: broken',
                    }),
                }),
            ],
            { exitCode: 1 },
        );

        expect(output).toContain('skipped: custom/plugins/Suite/src/FeatureA/Resources/app/administration/tsconfig.json');
        expect(output).toContain('skipped: custom/plugins/Suite/src/FeatureB/Resources/app/administration/tsconfig.json');
        expect(output).toContain('why: the FeatureA extends chain does not reach the preset.');
        expect(output).toContain('why: the FeatureB extends chain does not reach the preset.');
        expect(output).toContain('fix: add');
        expect(output).toContain('error TS2322');
    });

    it('renders one skipped block per TypeScript target even when runtime and spec programs both ran', () => {
        const output = report(
            [
                extension(partialProject(), {
                    typescript: run('failed', { findings: 1, newFindings: 1, output: 'x' }),
                    typescriptSpecs: run('failed', { findings: 1, newFindings: 1, output: 'y' }),
                }),
            ],
            { exitCode: 1 },
        );

        const occurrences = output.split(
            'skipped: custom/plugins/Suite/src/FeatureA/Resources/app/administration/tsconfig.json',
        );

        expect(occurrences).toHaveLength(2);
    });

    it('shows the per-extension bridge note for a partial failure', () => {
        const needsBridge = project('Suite', {
            targets: [
                target('SuiteCore', { sourcePath: 'custom/plugins/Suite/src/Core/Resources/app/administration/src' }),
                unmanagedTarget('FeatureA'),
            ],
        });
        const output = report(
            [
                extension(needsBridge, {
                    typescript: run('failed', { findings: 1, newFindings: 1, output: 'x' }),
                }),
            ],
            { exitCode: 1 },
        );

        expect(output).toContain('composer admin:setup-extension-tooling');
    });

    it('groups targets sharing one config and caps the list', () => {
        const sharedConfig = 'custom/plugins/Suite/src/Alpha/Resources/app/administration/tsconfig.json';
        const manyTargets = project('Suite', {
            targets: [
                unmanagedTarget('Alpha', { technicalNames: ['SuiteAlpha1'] }),
                unmanagedTarget('Alpha', {
                    technicalNames: ['SuiteAlpha2'],
                    sourcePath: 'custom/plugins/Suite/src/Alpha2/Resources/app/administration/src',
                    tsconfig: owned(sharedConfig, 'the Alpha extends chain does not reach the preset.'),
                }),
                ...[
                    'B',
                    'C',
                    'D',
                    'E',
                    'F',
                ].map((feature) => unmanagedTarget(`Feature${feature}`)),
            ],
        });
        const output = report([extension(manyTargets, { typescript: run('failed', { findings: 1, output: 'x' }) })], {
            exitCode: 1,
        });

        expect(output).toContain('(2 targets)');
        expect(output.split('skipped: custom/plugins/Suite/src/')).toHaveLength(6);
        expect(output).toContain('… and 1 more skipped config(s) — run with --verbose to list them');
    });

    it('lists every skipped config under --verbose instead of capping', () => {
        const manyTargets = project('Suite', {
            targets: [
                'A',
                'B',
                'C',
                'D',
                'E',
                'F',
            ].map((feature) => unmanagedTarget(`Feature${feature}`)),
        });
        const output = report(
            [extension(manyTargets, { typescript: run('failed', { findings: 1, output: 'x' }) })],
            {
                exitCode: 1,
            },
            true,
        );

        expect(output.split('skipped: custom/plugins/Suite/src/')).toHaveLength(7);
        expect(output).not.toContain('more skipped config(s)');
    });

    it('lists skipped configs without per-tool fixes for vendor extensions', () => {
        const vendorPartial = project('VendorSuite', {
            vendor: true,
            targets: [
                target('VendorCore', { sourcePath: 'vendor/acme/suite/src/Core/Resources/app/administration/src' }),
                target('VendorFeature', {
                    sourcePath: 'vendor/acme/suite/src/Feature/Resources/app/administration/src',
                    tsconfig: owned(
                        'vendor/acme/suite/src/Feature/Resources/app/administration/tsconfig.json',
                        'the extends chain does not reach the preset.',
                    ),
                }),
            ],
        });
        const output = report(
            [
                extension(vendorPartial, {
                    typescript: run('failed', { findings: 1, newFindings: 1, output: 'x' }),
                }),
            ],
            { exitCode: 1 },
        );

        expect(output).toContain('skipped: vendor/acme/suite/src/Feature/Resources/app/administration/tsconfig.json');
        expect(output).not.toContain('fix:');
        expect(output).not.toContain('Pass --fail-on-skipped');
    });
});
