/**
 * @sw-package framework
 *
 * End-to-end proof that an extension's specs run on the Administration's test
 * harness through the generated `.shopware/jest.mjs` bridge: the `shopware:*`
 * virtual modules resolve, the feature-flag baseline makes a deprecation
 * warn before its major and throw once the major is active, and the extension's
 * own TypeScript SFC and Twig template compile and mount.
 */

import { spawnSync } from 'child_process';
import path from 'path';
import { setupExtensionTooling } from '../setup';
import {
    cleanupTempProject,
    createTempProject,
    realAdministrationRoot,
    writeFile,
    writePluginsConfig,
} from '../test-helpers';

const JEST_TIMEOUT = 180000;

describe('extension tooling jest bridge (e2e)', () => {
    let projectRoot: string;
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

        // The Composer package ships no test harness, so this bridges into the real platform Administration.
        setupExtensionTooling({ projectRoot, administrationRoot: realAdministrationRoot });
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
});
