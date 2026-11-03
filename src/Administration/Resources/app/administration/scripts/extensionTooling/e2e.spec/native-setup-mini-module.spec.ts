/**
 * @sw-package framework
 *
 * End-to-end proof that an extension can author a native-setup base component as a mini-module:
 * the extension check types its `index.ts`, the extension build compares `index.ts` with the SFC,
 * and a second extension types the public members it overrides.
 * An override of a component that is not public stays untyped for an extension, and the lint warns about it.
 */

import { spawnSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { checkExtensions } from '../check';
import { setupExtensionTooling } from '../setup';
import {
    cleanupTempProject,
    createTempProject,
    createVendorAdmin,
    realAdministrationRoot,
    writeFile,
    writePluginsConfig,
} from '../test-helpers';

const CHECK_TIMEOUT = 300000;
const BASE_ADMIN = 'custom/plugins/MmBase/src/Resources/app/administration';
const MODULE = `${BASE_ADMIN}/src/mini-modules/mm-greeting`;
const THEME_ADMIN = 'custom/plugins/MmTheme/src/Resources/app/administration';

/**
 * Writes MmBase with the mini-module `mm-greeting`, whose props type comes through the plugin's own alias,
 * and MmTheme, which overrides it and types its public members through an alias to MmBase.
 */
function writeMiniModulePlugins(projectRoot: string): void {
    const write = (file: string, lines: string[]) => writeFile(path.join(projectRoot, file), lines);

    write('custom/plugins/MmBase/composer.json', ['{}']);
    write(`${BASE_ADMIN}/tsconfig.aliases.json`, ['{ "MmBase/*": ["src/*"] }']);
    write(`${BASE_ADMIN}/src/main.ts`, ["import './mini-modules/mm-greeting/register';"]);
    write(`${MODULE}/index.ts`, [
        "import { definePublicComponent } from 'shopware:framework/public-component';",
        '',
        '/** @public */',
        'export default definePublicComponent({',
        "    name: 'mm-greeting',",
        "    load: () => import('./src/mm-greeting.vue'),",
        "    public: { props: ['name', 'shout'], emits: ['greet'], slots: ['default'], blocks: ['mm_greeting'], setup: ['greeting', 'greet'] },",
        '});',
    ]);
    write(`${MODULE}/register.ts`, [
        "import MmGreeting from './index';",
        '',
        'Shopware.Component.register(MmGreeting);',
    ]);
    write(`${MODULE}/dependencies.ts`, ["export { computed } from 'vue';"]);
    write(`${MODULE}/src/mm-greeting.types.ts`, ['export interface MmGreetingProps { name: string; shout?: boolean }']);
    write(`${MODULE}/src/mm-greeting.vue`, [
        '<template>',
        '    <sw-block name="mm_greeting">',
        '        <p @click="greet">',
        '            {{ greeting }}',
        '        </p>',
        '        <slot />',
        '    </sw-block>',
        '</template>',
        '',
        '<script setup lang="ts">',
        "import type { MmGreetingProps } from 'MmBase/mini-modules/mm-greeting/src/mm-greeting.types';",
        "import { computed } from '../dependencies';",
        '',
        'const props = defineProps<MmGreetingProps>();',
        'const emit = defineEmits<{ greet: [greeting: string] }>();',
        'const greeting = computed(() => (props.shout ? `HELLO ${props.name}!` : `Hello ${props.name}`));',
        '',
        'function greet(): void {',
        "    emit('greet', greeting.value);",
        '}',
        '',
        'swDefinePublic({ greeting, greet });',
        '</script>',
    ]);

    write('custom/plugins/MmTheme/composer.json', ['{}']);
    write(`${THEME_ADMIN}/tsconfig.aliases.json`, [
        '{ "MmBase/*": ["../../../../../MmBase/src/Resources/app/administration/src/*"] }',
    ]);
    write(`${THEME_ADMIN}/src/main.ts`, ['export {};']);
    write(`${THEME_ADMIN}/src/mm-greeting.override.vue`, [
        '<script setup lang="ts">',
        "import { computed } from 'vue';",
        '',
        'const previousState = useSwPreviousState();',
        'const greeting = computed(() => `${String(previousState.greeting.value)} :)`);',
        '',
        'swDefineOverride({ greeting });',
        '</script>',
    ]);
    write(`${THEME_ADMIN}/src/private-override.ts`, [
        '// Not public: any configuration type-checks, and the lint warns that an update may break it.',
        "Shopware.Component.override('sw-cms-detail', { methods: { anyMethod(): void {} } });",
    ]);
    write(`${THEME_ADMIN}/src/mm-greeting.types.ts`, [
        "import type { PublicInstance } from 'shopware:framework/public-component';",
        "import type MmGreeting from 'MmBase/mini-modules/mm-greeting';",
        '',
        'type Greeting = PublicInstance<typeof MmGreeting>;',
        '',
        "export const name: Greeting['name'] = 'Ada';",
        "export const greeting: Greeting['greeting'] = 'Hello Ada';",
        '// @ts-expect-error greeting is a string',
        "export const wrongGreeting: Greeting['greeting'] = 1;",
        "export const greet: Greeting['greet'] = () => undefined;",
        '// @ts-expect-error the setup bindings swDefinePublic() leaves out are private',
        "export const emit: Greeting['emit'] = undefined;",
    ]);

    writePluginsConfig(projectRoot, [
        {
            technicalName: 'mm-base',
            basePath: 'custom/plugins/MmBase/src',
            administrationPath: 'Resources/app/administration/src',
            entryFilePath: 'Resources/app/administration/src/main.ts',
        },
        {
            technicalName: 'mm-theme',
            basePath: 'custom/plugins/MmTheme/src',
            administrationPath: 'Resources/app/administration/src',
            entryFilePath: 'Resources/app/administration/src/main.ts',
        },
    ]);
}

/**
 * Runs the production build of the extensions, as `composer build:js:admin` does after the Administration.
 */
function buildExtensions(projectRoot: string): { status: number | null; output: string } {
    const result = spawnSync('npx', ['ts-node', '-T', 'build/plugins.vite.ts'], {
        cwd: realAdministrationRoot,
        env: {
            ...process.env,
            PROJECT_ROOT: projectRoot,
            VITE_MODE: 'production',
            VITE_CONFIG_NATIVE_IGNORE_WARNING: 'true',
        },
        encoding: 'utf8',
    });

    return { status: result.status, output: `${result.stdout}${result.stderr}` };
}

describe('extension tooling native-setup mini-module (e2e)', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeAll(() => {
        projectRoot = createTempProject('sw-tooling-mini-module-');
        administrationRoot = createVendorAdmin(projectRoot, { entitySchema: 'real' });

        writeMiniModulePlugins(projectRoot);
        setupExtensionTooling({ projectRoot, administrationRoot });
    }, CHECK_TIMEOUT);

    afterAll(() => {
        cleanupTempProject(projectRoot);
    });

    it(
        'type-checks and lints the mini-module and an override that types its public members',
        async () => {
            // ACT
            const check = await checkExtensions({ projectRoot, administrationRoot });

            // ASSERT
            const statuses = check.results.map((result) => [
                result.project.name,
                result.typescript.status,
                result.eslint.status,
                `${result.typescript.output}${result.eslint.output}`,
            ]);
            expect(statuses).toEqual([
                [
                    'MmBase',
                    'passed',
                    'passed',
                    expect.any(String),
                ],
                [
                    'MmTheme',
                    'passed',
                    'passed',
                    expect.any(String),
                ],
            ]);
            expect(check.exitCode).toBe(0);
            expect(check.results[1].eslint.output).toContain(
                "'sw-cms-detail' is not public API of the Administration: an update can change or remove it without deprecation",
            );
        },
        CHECK_TIMEOUT,
    );

    it(
        'builds the extension and fails the build when index.ts drifts from the component',
        () => {
            // ARRANGE
            const indexFile = path.join(projectRoot, MODULE, 'index.ts');
            const built = buildExtensions(projectRoot);
            fs.writeFileSync(
                indexFile,
                fs.readFileSync(indexFile, 'utf8').replace("setup: ['greeting', 'greet']", "setup: ['greeting']"),
            );

            // ACT
            const drifted = buildExtensions(projectRoot);

            // ASSERT
            expect(built.status).toBe(0);
            expect(drifted.status).toBe(1);
            expect(drifted.output).toContain('The public API in index.ts does not match "mm-greeting":');
            expect(drifted.output).toContain(
                '- setup "greet": declared by swDefinePublic(), missing in index.ts public.setup',
            );
        },
        CHECK_TIMEOUT,
    );
});
