/**
 * @sw-package framework
 */

import { describeNextStep, describeToolGuidance } from '../report-guidance';
import type { ExtensionToolingProject } from '../shared';
import { owned, project } from './helpers';

describe('scripts/extensionTooling/report describeNextStep', () => {
    it('points a bridgeless custom/plugins extension at the automatic setup run', () => {
        const steps = describeNextStep(
            project('SwagPayPal', {
                tsconfig: owned(
                    'custom/plugins/SwagPayPal/src/Resources/app/administration/tsconfig.json',
                    'the tsconfig does not extend the preset.',
                ),
            }),
        ).join('\n');

        expect(steps).toContain('composer admin:setup-extension-tooling');
        expect(steps).not.toContain('--shim');
        expect(steps).not.toContain('README');
    });

    it('asks to finish wiring, not to re-bridge, once the bridge exists', () => {
        const steps = describeNextStep(
            project('Unwired', {
                bridgePresent: true,
                tsconfig: owned(
                    'custom/plugins/Unwired/src/Resources/app/administration/tsconfig.json',
                    'the tsconfig does not extend the preset.',
                ),
            }),
        ).join('\n');

        expect(steps).toContain('finish wiring it');
        expect(steps).toContain('"extends": "./.shopware/tsconfig.json"');
        expect(steps).not.toContain('--shim');
    });

    it('returns nothing for extensions whose configs compose the preset', () => {
        expect(
            describeNextStep(
                project('Done', {
                    bridgePresent: true,
                    tsconfig: owned('custom/plugins/Done/src/Resources/app/administration/tsconfig.json'),
                }),
            ),
        ).toEqual([]);
    });

    it('explains vendor extensions are read-only and non-fatal', () => {
        const steps = describeNextStep(project('Acme', { vendor: true, basePath: 'vendor/acme/admin' })).join('\n');

        expect(steps).toContain('vendor');
        expect(steps).not.toContain('--shim');
    });
});

describe('scripts/extensionTooling/report describeToolGuidance', () => {
    const unwired = (overrides: Partial<ExtensionToolingProject> = {}) =>
        project('Unwired', {
            bridgePresent: true,
            tsconfig: owned(
                'custom/plugins/Unwired/src/Resources/app/administration/tsconfig.json',
                'the tsconfig does not extend the preset.',
            ),
            eslintConfig: owned(
                'custom/plugins/Unwired/src/Resources/app/administration/eslint.config.mjs',
                'the ESLint config does not compose the factory.',
            ),
            ...overrides,
        });

    it('tells a files-override plugin to drop its own files array', () => {
        const guidance = describeToolGuidance(
            unwired(),
            'TypeScript',
            owned(
                'custom/plugins/Unwired/src/Resources/app/administration/tsconfig.json',
                'own "files" replaces the bridge — see tsconfig.aliases.json.',
                'files-override',
            ),
        );

        expect(guidance?.why).toContain('"files"');
        expect(guidance?.why).toContain('tsconfig.aliases.json');
        expect(guidance?.fix.join('\n')).toContain('remove the own "files" array');
        // The reported bug: the config already has its "extends", so telling the
        // reader to add one contradicts the "why" printed right above.
        expect(guidance?.fix.join('\n')).not.toContain('add "extends"');
    });

    it('gives a paste-ready include for a tsconfig that names no sources', () => {
        const guidance = describeToolGuidance(
            unwired(),
            'TypeScript',
            owned(
                'custom/plugins/Unwired/src/Resources/app/administration/tsconfig.json',
                'the tsconfig declares no "include".',
                'include-missing',
            ),
        );

        expect(guidance?.fix.join('\n')).toContain('"include": ["src/**/*.ts", "src/**/*.vue"]');
        // Same reasoning as the files-override case: the extends is already there.
        expect(guidance?.fix.join('\n')).not.toContain('add "extends"');
    });

    it('gives the concrete eslint compose snippet for an unwired bridge', () => {
        const guidance = describeToolGuidance(
            unwired(),
            'ESLint',
            owned(
                'custom/plugins/Unwired/src/Resources/app/administration/eslint.config.mjs',
                'the ESLint config does not compose the factory.',
            ),
        );

        expect(guidance?.fix.join('\n')).toContain("import shopware from './.shopware/eslint.mjs';");
    });

    it('returns null for composing tools and for vendor extensions', () => {
        expect(
            describeToolGuidance(
                unwired(),
                'TypeScript',
                owned('custom/plugins/Unwired/src/Resources/app/administration/tsconfig.json'),
            ),
        ).toBeNull();
        expect(
            describeToolGuidance(
                project('Acme', { vendor: true, basePath: 'vendor/acme/admin' }),
                'TypeScript',
                owned('vendor/acme/admin/tsconfig.json', 'the tsconfig does not extend the preset.'),
            ),
        ).toBeNull();
    });
});
