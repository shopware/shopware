/**
 * @sw-package framework
 */

import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { stripIndent, transformOrFail, transformShopwareSetupSfc } from './helpers';
import { ShopwareSetupTransformError, validateShopwareSetupSfc } from '../index.ts';

const fixtureModule = path.join(__dirname, 'fixtures/sw-example-mini-module');
const fixtureIndex = path.join(fixtureModule, 'index.ts');
const fixtureSfc = path.join(fixtureModule, 'src/sw-example.vue');

const moduleIndex = '/admin/sw-thing/index.ts';
const moduleSfc = '/admin/sw-thing/src/sw-thing.vue';

const sfc = stripIndent`
    <template>
        <sw-block name="sw_thing">
            <slot />
        </sw-block>
    </template>
    <script setup lang="ts">
    const props = defineProps(['label']);
    const emit = defineEmits<{ (event: 'save'): void }>();
    const value = defineModel<string>('title');
    function save() {
        emit('save');
    }

    swDefinePublic({ save });
    </script>
`;

/**
 * An index.ts that declares sw-thing.vue with the given `public` object literal.
 */
function indexWith(publicApi: string): string {
    return stripIndent`
        import { definePublicComponent } from 'src/core/public-component';

        export default definePublicComponent({
            name: 'sw-thing',
            load: () => import('./src/sw-thing.vue'),
            public: ${publicApi},
        });
    `;
}

const matchingPublic = `{
    props: ['label', 'title'],
    emits: ['save', 'update:title'],
    slots: ['default'],
    blocks: ['sw_thing'],
    setup: ['save'],
}`;

/**
 * Transforms the SFC as if it sat in a mini-module next to the given index.ts.
 */
function transformInModule(source: string, indexSource: string, filename = moduleSfc) {
    const files: Record<string, string> = { [moduleIndex]: indexSource };

    return transformShopwareSetupSfc(source, filename, { readFile: (file) => files[file] ?? null });
}

function captureDrift(source: string, indexSource: string): ShopwareSetupTransformError {
    try {
        transformInModule(source, indexSource);
    } catch (error) {
        if (error instanceof ShopwareSetupTransformError) {
            return error;
        }

        throw error;
    }

    throw new Error('Expected the public API check to fail');
}

/**
 * A mini-module whose SFC imports its props type through a tsconfig path:
 * the tsconfig files by their path in the project, where the type lives, and how the SFC imports it.
 */
type AliasedModule = {
    tsconfigs: Record<string, object>;
    typesFile: string;
    typesImport: string;
};

/**
 * Writes sw-thing as such a mini-module to disk and returns the path of its SFC.
 */
function writeAliasedModule(input: AliasedModule) {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-public-api-alias-'));
    const files: Record<string, string> = {
        [input.typesFile]: 'export interface SwThingProps { label: string; disabled?: boolean }\n',
        'src/sw-thing/index.ts': indexWith(matchingPublic.replace("props: ['label',", "props: ['label', 'disabled',")),
        'src/sw-thing/src/sw-thing.vue': sfc.replace(
            "const props = defineProps(['label']);",
            `import type { SwThingProps } from '${input.typesImport}';\nconst props = defineProps<SwThingProps>();`,
        ),
    };

    Object.entries(input.tsconfigs).forEach(([file, config]) => {
        files[file] = JSON.stringify(config);
    });
    Object.entries(files).forEach(([file, content]) => {
        fs.mkdirSync(path.dirname(path.join(root, file)), { recursive: true });
        fs.writeFileSync(path.join(root, file), content);
    });

    return path.join(root, 'src/sw-thing/src/sw-thing.vue');
}

describe('build/vue-setup-transform public API in index.ts', () => {
    it('passes the fixture mini-module, whose index.ts lists exactly what its SFC declares', () => {
        // ARRANGE
        const source = fs.readFileSync(fixtureSfc, 'utf8');

        // ACT
        const result = transformOrFail(source, fixtureSfc);

        // ASSERT
        expect(result.publicApiFile).toBe(fixtureIndex);
    });

    it('passes when every list matches', () => {
        // ACT
        const result = transformInModule(sfc, indexWith(matchingPublic));

        // ASSERT
        expect(result?.publicApiFile).toBe(moduleIndex);
    });

    it.each([
        {
            kind: 'setup',
            publicApi: matchingPublic.replace("setup: ['save']", 'setup: []'),
            line: '- setup "save": declared by swDefinePublic(), missing in index.ts public.setup',
        },
        {
            kind: 'setup',
            publicApi: matchingPublic.replace("setup: ['save']", "setup: ['save', 'reset']"),
            line: '- setup "reset": listed in index.ts public.setup, missing in swDefinePublic()',
        },
        {
            kind: 'props',
            publicApi: matchingPublic.replace("props: ['label', 'title']", "props: ['title']"),
            line: '- props "label": declared by defineProps() or defineModel(), missing in index.ts public.props',
        },
        {
            kind: 'props',
            publicApi: matchingPublic.replace("props: ['label', 'title']", "props: ['label']"),
            line: '- props "title": declared by defineProps() or defineModel(), missing in index.ts public.props',
        },
        {
            kind: 'emits',
            publicApi: matchingPublic.replace("emits: ['save', 'update:title']", "emits: ['save', 'update:title', 'close']"),
            line: '- emits "close": listed in index.ts public.emits, missing in defineEmits() or defineModel()',
        },
        {
            kind: 'slots',
            publicApi: matchingPublic.replace("slots: ['default']", 'slots: []'),
            line: '- slots "default": declared by a <slot> or defineSlots(), missing in index.ts public.slots',
        },
        {
            kind: 'blocks',
            publicApi: matchingPublic.replace("blocks: ['sw_thing']", "blocks: ['sw_thing_old']"),
            line: '- blocks "sw_thing": declared by an <sw-block name>, missing in index.ts public.blocks',
        },
    ])('fails on a drifted $kind list with the name, the side that misses it and both files', ({ publicApi, line }) => {
        // ACT
        const error = captureDrift(sfc, indexWith(publicApi));

        // ASSERT
        expect(error.message).toContain('The public API in index.ts does not match "sw-thing":');
        expect(error.message).toContain(line);
        expect(error.message).toContain(`index.ts:  ${moduleIndex}`);
        expect(error.message).toContain(`component: ${moduleSfc}`);
        expect(error.index).toBe(sfc.indexOf('swDefinePublic({ save });'));
    });

    it('treats a missing public object as empty lists', () => {
        // ACT
        const error = captureDrift(sfc, indexWith('undefined').replace('public: undefined,', ''));

        // ASSERT
        expect(error.message).toContain('- setup "save": declared by swDefinePublic(), missing in index.ts public.setup');
        expect(error.message).toContain(
            '- blocks "sw_thing": declared by an <sw-block name>, missing in index.ts public.blocks',
        );
    });

    it('rejects the Options API lists for a native-setup component', () => {
        // ACT
        const error = captureDrift(sfc, indexWith("{ methods: ['save'] }"));

        // ASSERT
        expect(error.message).toContain('A native-setup component has no public "methods".');
        expect(error.message).toContain(`${moduleIndex}:6:15`);
    });

    it('rejects a list it cannot read without running index.ts', () => {
        // ACT
        const error = captureDrift(sfc, indexWith('{ setup: names }'));

        // ASSERT
        expect(error.message).toContain('public.setup must be an array of string literals.');
    });

    it('fails on props it cannot read', () => {
        // ARRANGE
        const source = sfc.replace("defineProps(['label'])", 'defineProps(propsDefinition)');

        // ACT
        const error = captureDrift(source, indexWith(matchingPublic));

        // ASSERT
        expect(error.message).toContain('Cannot read the props of this component for the index.ts check.');
    });

    it.each<AliasedModule & { alias: string }>([
        {
            alias: "the Administration's src/ alias",
            tsconfigs: { 'tsconfig.json': { compilerOptions: { baseUrl: './' } } },
            typesFile: 'src/shared/sw-thing.types.ts',
            typesImport: 'src/shared/sw-thing.types',
        },
        {
            alias: 'an extension alias in an extended tsconfig',
            tsconfigs: {
                'tsconfig.json': { extends: './.shopware/tsconfig.json' },
                '.shopware/tsconfig.json': { compilerOptions: { paths: { 'MyPlugin/*': ['../lib/*'] } } },
            },
            typesFile: 'lib/sw-thing.types.ts',
            typesImport: 'MyPlugin/sw-thing.types',
        },
    ])('reads props whose type is imported through $alias', ({ tsconfigs, typesFile, typesImport }) => {
        // ARRANGE
        const sfcFile = writeAliasedModule({ tsconfigs, typesFile, typesImport });

        // ACT
        const result = transformOrFail(fs.readFileSync(sfcFile, 'utf8'), sfcFile);

        // ASSERT
        expect(result.publicApiFile).toBe(path.join(path.dirname(sfcFile), '../index.ts'));
    });

    it('leaves an SFC in src/ alone when index.ts does not load it', () => {
        // ACT
        const result = transformInModule(sfc, indexWith(matchingPublic), '/admin/sw-thing/src/sw-thing-row.vue');

        // ASSERT
        expect(result?.publicApiFile).toBeNull();
    });

    it('leaves an SFC outside a mini-module alone', () => {
        // ACT
        const result = transformOrFail(sfc, 'sw-thing.vue');

        // ASSERT
        expect(result.publicApiFile).toBeNull();
    });

    it('skips the check for ESLint, whose cache cannot see index.ts', () => {
        // ARRANGE
        const source = fs
            .readFileSync(fixtureSfc, 'utf8')
            .replace('swDefinePublic({ count, save, value });', 'swDefinePublic({});');

        // ACT
        const validate = () => validateShopwareSetupSfc(source, fixtureSfc);

        // ASSERT
        expect(validate).not.toThrow();
        expect(() => transformShopwareSetupSfc(source, fixtureSfc)).toThrow(
            '- setup "count": listed in index.ts public.setup, missing in swDefinePublic()',
        );
    });
});
