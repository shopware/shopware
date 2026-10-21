/**
 * @sw-package framework
 *
 * End-to-end proof that an extension's specs run on the Administration's test
 * harness through the generated `.shopware/jest.mjs` bridge, in a platform
 * checkout and in a Composer install: the `shopware:*` virtual modules
 * resolve, the feature-flag baseline makes a deprecation warn before its
 * major and throw once the major is active, and the extension's own
 * TypeScript SFC and Twig template compile and mount.
 */

import { execFileSync, spawnSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { setupExtensionTooling } from '../setup';
import { createJestRunner, testExtensions } from '../test';
import {
    cleanupTempProject,
    createTempProject,
    createVendorAdmin,
    realAdministrationRoot,
    writeFile,
    writePluginsConfig,
} from '../test-helpers';

const JEST_TIMEOUT = 180000;

const realBundleRoot = path.resolve(realAdministrationRoot, '../../..');

const generatedFiles = [
    'test/_helper_/componentWrapper/component-imports.js',
    'test/_mocks_/entity-schema.json',
];

/**
 * Unpacks the Administration's `test/` folder from the committed bundle into
 * `packageRoot`, exactly as `git archive` packs it for the Composer package.
 * Files the package must not ship are absent, so the e2e catches a harness
 * file that `.gitattributes` excludes by mistake.
 */
function extractPackagedTests(packageRoot: string): void {
    const repositoryRoot = execFileSync('git', ['rev-parse', '--show-toplevel'], {
        cwd: realAdministrationRoot,
        encoding: 'utf8',
    }).trim();
    const bundlePath = path.relative(repositoryRoot, realBundleRoot);

    // The archive root is the bundle, so its .gitattributes applies the way the package split applies it.
    const packagedTests = execFileSync(
        'git',
        [
            'archive',
            '--format=tar',
            `HEAD:${bundlePath}`,
            'Resources/app/administration/test',
        ],
        { cwd: repositoryRoot, maxBuffer: 64 * 1024 * 1024 },
    );
    execFileSync('tar', ['-x', '-C', packageRoot], { input: packagedTests });
}

/**
 * Stands in for a Composer install: the shopware/administration package with
 * its `test/` folder exactly as `git archive` packs it, the files every
 * installation generates itself, and the Core bundle in lowercase
 * `vendor/shopware/core`.
 */
function createComposerInstall(projectRoot: string): string {
    const administrationRoot = createVendorAdmin(projectRoot, { entitySchema: 'none' });
    extractPackagedTests(path.resolve(administrationRoot, '../../..'));

    // The meta-glob transform matches the real path of src/, which per-entry symlinks would move out of it.
    fs.rmSync(path.join(administrationRoot, 'src'), { recursive: true });
    fs.symlinkSync(path.join(realAdministrationRoot, 'src'), path.join(administrationRoot, 'src'), 'dir');

    // The harness reads these committed sources, which the package ships but createVendorAdmin does not copy.
    fs.symlinkSync(path.join(realAdministrationRoot, 'build'), path.join(administrationRoot, 'build'), 'dir');
    fs.copyFileSync(
        path.join(realAdministrationRoot, 'shopware-modules.json'),
        path.join(administrationRoot, 'shopware-modules.json'),
    );
    fs.copyFileSync(path.join(realAdministrationRoot, 'tsconfig.json'), path.join(administrationRoot, 'tsconfig.json'));

    for (const generatedFile of generatedFiles) {
        fs.copyFileSync(path.join(realAdministrationRoot, generatedFile), path.join(administrationRoot, generatedFile));
    }

    writeFile(
        path.join(projectRoot, 'vendor/shopware/core/Framework/Resources/config/packages/feature.yaml'),
        fs.readFileSync(path.join(realBundleRoot, '../Core/Framework/Resources/config/packages/feature.yaml'), 'utf8'),
    );

    return administrationRoot;
}

describe.each([
    ['a platform checkout', () => realAdministrationRoot],
    ['a Composer install', createComposerInstall],
])('extension tooling jest bridge in %s (e2e)', (_layout, installAdministration) => {
    let projectRoot: string;
    let administrationRoot: string;
    let adminFolder: string;

    beforeAll(() => {
        projectRoot = createTempProject('sw-tooling-jest-bridge-');
        adminFolder = path.join(projectRoot, 'custom/plugins/JestPlugin/src/Resources/app/administration');

        writeFile(path.join(projectRoot, 'custom/plugins/JestPlugin/composer.json'), '{}\n');
        writeFile(path.join(adminFolder, 'src/main.ts'), ['export {};']);
        writeFile(path.join(adminFolder, 'jest.config.mjs'), ["export { default } from './.shopware/jest.mjs';"]);
        writeFile(path.join(adminFolder, 'src/main.spec.js'), [
            "import { createId } from 'shopware:utils';",
            '',
            "describe('JestPlugin', () => {",
            "    it('resolves the shopware:* virtual modules', () => {",
            '        expect(createId()).toMatch(/^[0-9a-f]{32}$/);',
            '    });',
            '',
            "    it.deprecated('v6.8.0.0')('warns about a deprecation before its major', () => {",
            "        Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', 'oldApi() is deprecated.');",
            '    });',
            '',
            "    it.activeFeatureFlags(['V6_8_0_0'])('throws for a deprecation once its major is active', () => {",
            "        expect(() => Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', 'oldApi() is deprecated.'))",
            "            .toThrow('Tried to access deprecated functionality');",
            '    });',
            '});',
        ]);
        // The Set spread compiles correctly only with the Administration's tsconfig
        // (downlevelIteration), which vue-jest must use instead of the extension's.
        writeFile(path.join(adminFolder, 'src/component/greeting-list.vue'), [
            '<template>',
            "    <p>{{ uniqueNames.join(', ') }}</p>",
            '</template>',
            '',
            '<script setup lang="ts">',
            "import { computed } from 'vue';",
            '',
            'const props = defineProps<{ names: string[] }>();',
            'const uniqueNames = computed(() => [...new Set(props.names)]);',
            '',
            'swDefinePublic({});',
            '</script>',
        ]);
        writeFile(path.join(adminFolder, 'src/component/greeting-card.html.twig'), [
            '{# Twig comments are stripped by the template transform. #}',
            '<p>Hello {{ name }}</p>',
        ]);
        writeFile(path.join(adminFolder, 'src/component/component.spec.js'), [
            "import { mount } from '@vue/test-utils';",
            "import GreetingList from './greeting-list.vue';",
            "import greetingCardTemplate from './greeting-card.html.twig';",
            '',
            "describe('JestPlugin components', () => {",
            "    it('renders a TypeScript single-file component', () => {",
            "        const wrapper = mount(GreetingList, { props: { names: ['Ada', 'Ada', 'Grace'] } });",
            '',
            "        expect(wrapper.text()).toBe('Ada, Grace');",
            '    });',
            '',
            "    it('renders a Twig template', () => {",
            "        const wrapper = mount({ template: greetingCardTemplate, data: () => ({ name: 'Ada' }) });",
            '',
            "        expect(wrapper.text()).toBe('Hello Ada');",
            '    });',
            '});',
        ]);
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'JestPlugin',
                basePath: 'custom/plugins/JestPlugin/src',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);

        administrationRoot = installAdministration(projectRoot);
        setupExtensionTooling({ projectRoot, administrationRoot });
    }, JEST_TIMEOUT);

    afterAll(() => {
        cleanupTempProject(projectRoot);
    });

    it(
        'runs an extension spec on the Administration test harness',
        () => {
            const run = spawnSync(
                process.execPath,
                [
                    path.join(realAdministrationRoot, 'node_modules/jest/bin/jest.js'),
                    '--config',
                    'jest.config.mjs',
                    '--ci',
                    '--coverage=false',
                ],
                { cwd: adminFolder, encoding: 'utf8' },
            );

            // A major lane skips the `it.deprecated` test, so only the total is fixed.
            expect(run.stderr).toMatch(/Tests:.* 5 total/);
            expect(run.status).toBe(0);
        },
        JEST_TIMEOUT,
    );

    it(
        'runs the extension spec through admin:test-extensions',
        async () => {
            const result = await testExtensions({
                projectRoot,
                administrationRoot,
                only: 'JestPlugin',
                runJest: createJestRunner(administrationRoot, 'ignore'),
            });

            expect(result.runs).toEqual([
                {
                    extension: 'JestPlugin',
                    config: 'custom/plugins/JestPlugin/src/Resources/app/administration/jest.config.mjs',
                    exitCode: 0,
                },
            ]);
            expect(result.exitCode).toBe(0);
        },
        JEST_TIMEOUT,
    );
});
