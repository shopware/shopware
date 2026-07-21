/**
 * @sw-package framework
 */

import { describeNextStep, describeToolGuidance } from '../report';
import type { ExtensionToolingProject } from '../shared';
import { project, resolution } from './helpers';

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
                    ts: resolution('bridged'),
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
        expect(describeToolGuidance(unwired(), 'TypeScript', resolution('bridged'))).toBeNull();
        expect(
            describeToolGuidance(
                project('Acme', { vendor: true, basePath: 'vendor/acme/admin' }),
                'TypeScript',
                resolution('unmanaged', { reason: 'not-extending' }),
            ),
        ).toBeNull();
    });
});
