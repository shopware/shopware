/**
 * @sw-package framework
 */

import fs from 'fs';
import path from 'path';
import { discoverAdminRoots, selectRoots } from './discovery';
import { isPlatformPackage } from './shared';
import { composerManifest, createTempProject, removeTempProject, writeBundleConfig, writeTree } from './test-helpers';
import type { AdminRoot } from './shared';

describe('scripts/extensionTooling/discovery', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        projectRoot = createTempProject();
        administrationRoot = path.join(projectRoot, 'src/Administration/Resources/app/administration');

        fs.mkdirSync(path.join(administrationRoot, 'src'), { recursive: true });
    });

    afterEach(() => {
        removeTempProject(projectRoot);
    });

    const discover = (): AdminRoot[] =>
        discoverAdminRoots({
            projectRoot,
            administrationRoot,
            pluginsConfigPath: path.join(projectRoot, 'var', 'plugins.json'),
        });

    it('throws with the bundle:dump hint when the bundle configuration is missing', () => {
        expect(() => discover()).toThrow('Run "bin/console bundle:dump" first.');
    });

    it('discovers a plugin that ships Administration sources', () => {
        writeTree(projectRoot, {
            'custom/plugins/SwagExample/composer.json': composerManifest('swag/example', 'shopware-platform-plugin'),
            'custom/plugins/SwagExample/src/Resources/app/administration/src/main.ts': 'export default {};\n',
        });
        writeBundleConfig(projectRoot, {
            SwagExample: { basePath: 'custom/plugins/SwagExample/src', technicalName: 'swag-example' },
        });

        const roots = discover();

        expect(roots).toHaveLength(1);
        expect(roots[0]).toMatchObject({
            bundleName: 'SwagExample',
            technicalName: 'swag-example',
            extensionName: 'SwagExample',
            slug: 'swag-example',
            platform: false,
        });
        expect(roots[0].sourcePath).toBe(
            path.join(projectRoot, 'custom/plugins/SwagExample/src/Resources/app/administration/src'),
        );
        expect(roots[0].adminFolder).toBe(
            path.join(projectRoot, 'custom/plugins/SwagExample/src/Resources/app/administration'),
        );
    });

    it('skips bundles whose Administration path does not exist', () => {
        writeTree(projectRoot, {
            'custom/plugins/SwagBackendOnly/composer.json': composerManifest('swag/backend', 'shopware-platform-plugin'),
        });
        writeBundleConfig(projectRoot, {
            SwagBackendOnly: { basePath: 'custom/plugins/SwagBackendOnly/src' },
        });

        expect(discover()).toEqual([]);
    });

    it('skips bundles without an Administration path at all', () => {
        writeBundleConfig(projectRoot, {
            SwagNoAdmin: { basePath: 'custom/plugins/SwagNoAdmin/src', administrationPath: null },
        });

        expect(discover()).toEqual([]);
    });

    it('skips the Administration itself — it is the host, not an extension', () => {
        writeBundleConfig(projectRoot, {
            Administration: { basePath: 'src/Administration' },
        });

        expect(discover()).toEqual([]);
    });

    it('skips a basePath that escapes the project root', () => {
        const outside = path.join(path.dirname(projectRoot), `${path.basename(projectRoot)}-outside`);

        fs.mkdirSync(path.join(outside, 'Resources/app/administration/src'), { recursive: true });
        writeBundleConfig(projectRoot, {
            Escaping: { basePath: `../${path.basename(outside)}` },
        });

        try {
            expect(discover()).toEqual([]);
        } finally {
            fs.rmSync(outside, { recursive: true, force: true });
        }
    });

    it('deduplicates bundles that point at the same source root and keeps slugs unique', () => {
        writeTree(projectRoot, {
            'custom/plugins/SwagExample/composer.json': composerManifest('swag/example', 'shopware-platform-plugin'),
            'custom/plugins/SwagExample/src/Resources/app/administration/src/main.ts': 'export default {};\n',
            'custom/plugins/SwagOther/composer.json': composerManifest('swag/other', 'shopware-platform-plugin'),
            'custom/plugins/SwagOther/src/Resources/app/administration/src/main.ts': 'export default {};\n',
        });
        writeBundleConfig(projectRoot, {
            SwagExample: { basePath: 'custom/plugins/SwagExample/src', technicalName: 'swag-example' },
            SwagExampleAgain: { basePath: 'custom/plugins/SwagExample/src', technicalName: 'swag-example' },
            SwagOther: { basePath: 'custom/plugins/SwagOther/src', technicalName: 'swag-example' },
        });

        const roots = discover();

        expect(roots.map((root) => root.bundleName)).toEqual([
            'SwagExample',
            'SwagOther',
        ]);
        expect(roots.map((root) => root.slug)).toEqual([
            'swag-example',
            'swag-example-2',
        ]);
    });

    describe('platform filter', () => {
        it('classifies a platform package by its shopware/ vendor prefix', () => {
            writeTree(projectRoot, {
                'src/Storefront/composer.json': composerManifest('shopware/storefront', 'library'),
                'src/Storefront/Resources/app/administration/src/main.ts': 'export default {};\n',
            });
            writeBundleConfig(projectRoot, {
                Storefront: { basePath: 'src/Storefront', technicalName: 'storefront' },
            });

            expect(discover()[0]).toMatchObject({ extensionName: 'Storefront', platform: true });
        });

        it('finds the platform manifest above the bundle directory', () => {
            writeTree(projectRoot, {
                'src/Core/composer.json': composerManifest('shopware/core', 'library'),
                'src/Core/Profiling/Resources/app/administration/src/main.ts': 'export default {};\n',
            });
            writeBundleConfig(projectRoot, {
                Profiling: { basePath: 'src/Core/Profiling', technicalName: 'profiling' },
            });

            expect(discover()[0]).toMatchObject({ extensionName: 'Core', platform: true });
        });

        it('classifies a first-party extension under the shopware/ prefix as an extension', () => {
            // shopware/commercial lives in custom/plugins and carries the platform
            // vendor prefix: the package type is what separates it from platform code.
            writeTree(projectRoot, {
                'custom/plugins/SwagCommercial/composer.json': composerManifest(
                    'shopware/commercial',
                    'shopware-platform-plugin',
                ),
                'custom/plugins/SwagCommercial/src/Subscription/Resources/app/administration/src/main.ts':
                    'export default {};\n',
            });
            writeBundleConfig(projectRoot, {
                SwagCommercialSubscription: {
                    basePath: 'custom/plugins/SwagCommercial/src/Subscription',
                    technicalName: 'swag-commercial-subscription',
                },
            });

            expect(discover()[0]).toMatchObject({ extensionName: 'SwagCommercial', platform: false });
        });

        it('classifies custom/static-plugins as extensions, not as platform', () => {
            writeTree(projectRoot, {
                'custom/static-plugins/SwagStatic/composer.json': composerManifest(
                    'shopware/static-thing',
                    'shopware-platform-plugin',
                ),
                'custom/static-plugins/SwagStatic/src/Resources/app/administration/src/main.ts': 'export default {};\n',
                'custom/static-plugins/SwagThirdParty/composer.json': composerManifest(
                    'acme/static',
                    'shopware-platform-plugin',
                ),
                'custom/static-plugins/SwagThirdParty/src/Resources/app/administration/src/main.ts': 'export default {};\n',
            });
            writeBundleConfig(projectRoot, {
                SwagStatic: { basePath: 'custom/static-plugins/SwagStatic/src', technicalName: 'swag-static' },
                SwagThirdParty: { basePath: 'custom/static-plugins/SwagThirdParty/src', technicalName: 'swag-third-party' },
            });

            expect(
                discover().map((root) => [
                    root.extensionName,
                    root.platform,
                ]),
            ).toEqual([
                [
                    'SwagStatic',
                    false,
                ],
                [
                    'SwagThirdParty',
                    false,
                ],
            ]);
        });

        it('treats an extension without a composer manifest as an extension', () => {
            writeTree(projectRoot, {
                'composer.json': composerManifest('shopware/platform', 'library'),
                'custom/plugins/SwagNoManifest/src/Resources/app/administration/src/main.ts': 'export default {};\n',
            });
            writeBundleConfig(projectRoot, {
                SwagNoManifest: { basePath: 'custom/plugins/SwagNoManifest/src', technicalName: 'swag-no-manifest' },
            });

            // The root manifest describes the installation, never an extension, so the
            // upward walk must not reach it.
            expect(discover()[0]).toMatchObject({ platform: false });
        });

        it('treats an unreadable composer manifest as an extension', () => {
            writeTree(projectRoot, {
                'custom/plugins/SwagBroken/composer.json': '{ this is not json',
                'custom/plugins/SwagBroken/src/Resources/app/administration/src/main.ts': 'export default {};\n',
            });
            writeBundleConfig(projectRoot, {
                SwagBroken: { basePath: 'custom/plugins/SwagBroken/src' },
            });

            expect(discover()[0]).toMatchObject({ platform: false });
        });

        it('reads a commented composer manifest', () => {
            writeTree(projectRoot, {
                'src/Elasticsearch/composer.json':
                    '{\n    // the platform package\n    "name": "shopware/elasticsearch",\n    "type": "library",\n}\n',
            });

            expect(isPlatformPackage(path.join(projectRoot, 'src/Elasticsearch'))).toBe(true);
        });

        it('is not platform when there is no package root at all', () => {
            expect(isPlatformPackage(null)).toBe(false);
        });
    });

    describe('selectRoots', () => {
        const roots: AdminRoot[] = [
            {
                extensionName: 'SwagExample',
                bundleName: 'SwagExample',
                technicalName: 'swag-example',
                slug: 'swag-example',
                platform: false,
            },
            {
                extensionName: 'SwagOther',
                bundleName: 'SwagOtherBundle',
                technicalName: 'swag-other',
                slug: 'swag-other',
                platform: false,
            },
            {
                extensionName: 'Storefront',
                bundleName: 'Storefront',
                technicalName: 'storefront',
                slug: 'storefront',
                platform: true,
            },
        ].map((root) => ({ ...root, extensionRoot: '', sourcePath: '', adminFolder: '' }));

        it('excludes platform roots by default and reports them as skipped', () => {
            const selection = selectRoots(roots, { names: [], includePlatform: false });

            expect(selection.selected.map((root) => root.bundleName)).toEqual([
                'SwagExample',
                'SwagOtherBundle',
            ]);
            expect(selection.skippedPlatform.map((root) => root.bundleName)).toEqual(['Storefront']);
        });

        it('includes platform roots with --include-platform', () => {
            const selection = selectRoots(roots, { names: [], includePlatform: true });

            expect(selection.selected).toHaveLength(3);
            expect(selection.skippedPlatform).toEqual([]);
        });

        it('matches extension, bundle, technical and slug names case-insensitively', () => {
            expect(
                selectRoots(roots, {
                    names: [
                        'swagexample',
                        'SwagOtherBundle',
                    ],
                    includePlatform: false,
                }).selected.map((root) => root.bundleName),
            ).toEqual([
                'SwagExample',
                'SwagOtherBundle',
            ]);
            expect(selectRoots(roots, { names: ['swag-other'], includePlatform: false }).selected).toHaveLength(1);
        });

        it('checks an explicitly named platform root without --include-platform', () => {
            const selection = selectRoots(roots, { names: ['Storefront'], includePlatform: false });

            expect(selection.selected.map((root) => root.bundleName)).toEqual(['Storefront']);
        });

        it('reports names that match nothing', () => {
            const selection = selectRoots(roots, {
                names: [
                    'SwagExample',
                    'Nope',
                ],
                includePlatform: false,
            });

            expect(selection.unknownNames).toEqual(['Nope']);
        });
    });
});
