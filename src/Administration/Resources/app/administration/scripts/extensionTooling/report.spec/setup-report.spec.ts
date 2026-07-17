/**
 * @sw-package framework
 */

import { renderSetupReport } from '../report';
import { project, resolution, setupResult } from './helpers';

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

    it('lists changed files by path and defers large batches to --explain', () => {
        const few = setupResult([managed], {
            changed: true,
            writes: [
                { file: 'var/admin-extension-tooling/projects/mine.json', state: 'created' },
                { file: 'tsconfig.json', state: 'updated' },
            ],
            staleFiles: ['var/admin-extension-tooling/projects/gone.json'],
        });
        const fewOutput = renderSetupReport(few);

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
        const manyOutput = renderSetupReport(many);

        expect(manyOutput).toContain('12 generated, 0 updated (list: --explain)');
        expect(manyOutput).not.toContain('generated: var/admin-extension-tooling/projects/p3.json');
        expect(renderSetupReport(many, { explain: true })).toContain(
            'created: var/admin-extension-tooling/projects/p3.json',
        );
    });

    it('drops the self-referential --explain hint inside --explain output', () => {
        const result = setupResult([managed]);

        expect(renderSetupReport(result)).toContain('IDE setup: run with --explain');
        expect(renderSetupReport(result, { explain: true })).not.toContain('run with --explain');
    });
});
