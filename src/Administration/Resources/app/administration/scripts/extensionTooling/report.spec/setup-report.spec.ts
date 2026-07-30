/**
 * @sw-package framework
 */

import { project, resolution, setupReport, setupResult } from './helpers';

describe('scripts/extensionTooling/report renderSetupReport', () => {
    const managed = project('FroshTools');
    const customPlugin = project('SwagPayPal', {
        ts: resolution('unmanaged', { reason: 'not-extending', verified: false }),
        eslint: resolution('unmanaged', { reason: 'factory-not-composed', verified: false }),
        tsconfig: 'custom/plugins/SwagPayPal/src/Resources/app/administration/tsconfig.json',
        eslintConfig: 'custom/plugins/SwagPayPal/src/Resources/app/administration/eslint.config.mjs',
    });
    const bridgedPlugin = project('Bridged', {
        ts: resolution('bridged'),
        eslint: resolution('bridged'),
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

    it('classifies ready / bridged / unwired / needs-bridge and shows inline next-steps by default', () => {
        const output = setupReport(
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
        expect(output).toContain('● needs bridge');
        expect(output).toContain('Next steps');
        expect(output).toContain('--shim=SwagPayPal');
        expect(output).not.toContain('--shim=Unwired');
        expect(output).toContain('finish wiring it');
    });

    it('reports a statically classified bridged plugin the same as a verified one', () => {
        // Setup only ever has the static verdict; nothing in its output may
        // depend on `verified`, which no setup run can ever set.
        const staticallyBridged = project('Static', {
            ts: resolution('bridged', { verified: false }),
            eslint: resolution('bridged', { verified: false }),
            bridgePresent: true,
            tsconfig: 'custom/plugins/Static/src/Resources/app/administration/tsconfig.json',
            eslintConfig: 'custom/plugins/Static/src/Resources/app/administration/eslint.config.mjs',
        });

        expect(setupReport(setupResult([staticallyBridged]))).toContain('● bridged  Static');
        expect(setupReport(setupResult([staticallyBridged]))).not.toContain('unverified');
    });

    it('renders the IDE / integration instruction block the run produced', () => {
        const withInstructions = setupResult([managed], {
            instructions: ['PhpStorm (configure once, Settings → Languages & Frameworks): …'],
        });

        expect(setupReport(withInstructions)).toContain('Settings → Languages');
        expect(setupReport(setupResult([managed]))).not.toContain('Settings → Languages');
    });

    it('confirms a fresh bridge after --shim only when the configs actually compose it', () => {
        const output = setupReport(setupResult([bridgedPlugin]), { shim: 'Bridged' });

        expect(output).toContain('✔ Bridged Bridged');
        expect(output).toContain('.shopware/ bridge');
    });

    it('announces one remaining step after --shim when existing configs were left alone', () => {
        const output = setupReport(setupResult([unwiredPlugin]), { shim: 'Unwired' });

        expect(output).toContain('✔ Bridge created for Unwired');
        expect(output).toContain('one step left');
        expect(output).not.toContain('✔ Bridged Unwired');
    });

    it('distinguishes git-ignored bridge files from committable plugin files in dry-run output', () => {
        const output = setupReport(
            setupResult([project('Mono')], {
                changed: true,
                writes: [
                    { file: 'custom/plugins/Mono/.shopware/tsconfig.json', state: 'created' },
                    { file: 'custom/plugins/Mono/.shopware/eslint.mjs', state: 'created' },
                    { file: 'custom/plugins/Mono/tsconfig.json', state: 'created' },
                    { file: 'var/admin-extension-tooling/manifest.json', state: 'created' },
                ],
            }),
            { checkOnly: true },
        );

        expect(output).toContain('would create: custom/plugins/Mono/.shopware/tsconfig.json [git-ignored bridge]');
        expect(output).toContain('would create: custom/plugins/Mono/tsconfig.json [commit this]');
        expect(output).toContain('2 git-ignored bridge file(s), 1 committable plugin file(s), 1 host projection(s)');
    });

    it('states the experimental status on every run, including the empty state', () => {
        // The report is the only surface a developer is guaranteed to see, so the
        // BC caveat may never depend on which branch the run takes.
        const populated = setupReport(setupResult([managed]));

        expect(populated).toContain('EXPERIMENTAL — not covered by the backwards-compatibility promise.');
        expect(populated).toContain('manifest');
        expect(setupReport(setupResult([]))).toContain('EXPERIMENTAL');
        expect(setupReport(setupResult([managed]), { checkOnly: true })).toContain('EXPERIMENTAL');
    });

    it('replaces the empty state with discovery guidance instead of a green "up to date"', () => {
        const output = setupReport(setupResult([]));

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
        const output = setupReport(
            setupResult([
                managed,
                storefront,
            ]),
        );

        expect(output).toContain('— 1 extension(s)');
        expect(output).toContain('platform   Storefront');
        expect(output).not.toContain('--shim=Storefront');
        expect(output).not.toContain('<administration>');

        const emptyWithPlatform = setupReport(setupResult([storefront]));

        expect(emptyWithPlatform).toContain('no extensions found');
        expect(emptyWithPlatform).toContain('Platform bundles like Storefront');
    });

    it('reads "Configs up to date" when nothing changed, lists stale writes under --check', () => {
        expect(setupReport(setupResult([managed]))).toContain('Configs up to date');

        const stale = setupResult([managed], {
            changed: true,
            writes: [{ file: 'tsconfig.json', state: 'created' }],
        });

        expect(setupReport(stale, { checkOnly: true })).toContain('would create: tsconfig.json');
    });

    it('lists changed files by path and keeps only the count for large batches', () => {
        const few = setupResult([managed], {
            changed: true,
            writes: [
                { file: 'var/admin-extension-tooling/projects/mine.json', state: 'created' },
                { file: 'tsconfig.json', state: 'updated' },
            ],
            staleFiles: ['var/admin-extension-tooling/projects/gone.json'],
        });
        const fewOutput = setupReport(few);

        expect(fewOutput).toContain('generated: var/admin-extension-tooling/projects/mine.json');
        expect(fewOutput).toContain('updated: tsconfig.json');
        expect(fewOutput).toContain('removed: var/admin-extension-tooling/projects/gone.json');

        const many = setupResult([managed], {
            changed: true,
            writes: Array.from({ length: 12 }, (unused, index) => ({
                file: `var/admin-extension-tooling/projects/p${index}.json`,
                state: 'created' as const,
            })),
        });
        const manyOutput = setupReport(many);

        expect(manyOutput).toContain('12 generated, 0 updated');
        expect(manyOutput).not.toContain('generated: var/admin-extension-tooling/projects/p3.json');
    });
});
