const fs = require('fs');
const os = require('os');
const path = require('path');
const { RuleTester } = require('eslint');
const rule = require('./no-set-data');

/**
 * The rule looks for a converted component beside the spec, so the cases need real files on disk.
 *
 * They are written at module load because `ruleTester.run()` collects its cases before any Jest hook
 * runs, and in a temporary directory so no fixture `.vue` file ends up under `eslint-rules/`, where the
 * repository lint and the native setup transform would both pick it up. The path is fixed rather than
 * unique, and cleared on the way in, so each run starts from an empty tree without a teardown hook.
 */
const packageRoot = path.join(os.tmpdir(), 'sw-no-set-data-fixture');
const componentDirectory = path.join(packageRoot, 'src/app/component');

fs.rmSync(packageRoot, { recursive: true, force: true });
fs.mkdirSync(path.join(componentDirectory, 'sw-converted'), { recursive: true });
fs.mkdirSync(path.join(componentDirectory, 'sw-legacy'), { recursive: true });
fs.mkdirSync(path.join(packageRoot, 'src/app/mixin'), { recursive: true });
fs.writeFileSync(path.join(componentDirectory, 'sw-converted/index.vue'), '');
fs.writeFileSync(path.join(componentDirectory, 'sw-legacy/index.js'), '');
// A converted component whose directory no spec beside it is named after.
fs.writeFileSync(path.join(packageRoot, 'src/app/mixin/index.vue'), '');

const convertedSpec = path.join(componentDirectory, 'sw-converted/sw-converted.spec.js');
const nestedConvertedSpec = path.join(componentDirectory, 'sw-converted/sw-converted.spec/rendering.spec.js');
const legacySpec = path.join(componentDirectory, 'sw-legacy/sw-legacy.spec.js');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

ruleTester.run('no-set-data', rule, {
    valid: [
        {
            name: 'leaves setData alone when the wrapped component is not a native setup SFC',
            filename: legacySpec,
            code: `
const wrapper = mount(await wrapTestComponent('sw-legacy'));
await wrapper.setData({ isLoading: false });
            `.trim(),
        },
        {
            name: 'leaves setData alone when the spec names no component at all',
            filename: legacySpec,
            code: `
const wrapper = mount({ template: '<div />', data: () => ({ isLoading: true }) });
await wrapper.setData({ isLoading: false });
            `.trim(),
        },
        {
            name: 'leaves other wrapper calls alone in a native setup spec',
            filename: convertedSpec,
            code: `
const wrapper = mount(await wrapTestComponent('sw-converted'));
await wrapper.setProps({ isLoading: false });
            `.trim(),
        },
        {
            name: 'spares a call on an inline Options API host that only registers the converted component',
            filename: convertedSpec,
            code: `
const wrapper = mount({
    template: '<div><sw-converted /></div>',
    components: {
        'sw-converted': await wrapTestComponent('sw-converted', { sync: true }),
    },
    data() {
        return { label: 'initial' };
    },
});

await wrapper.setData({ label: 'a' });
            `.trim(),
        },
        {
            name: 'spares a call routed through a helper that mounts an inline Options API host',
            filename: convertedSpec,
            code: `
async function createWrapper() {
    return mount({
        template: '<div><sw-converted /></div>',
        components: { 'sw-converted': await wrapTestComponent('sw-converted') },
        data() {
            return { label: 'initial' };
        },
    });
}

const wrapper = await createWrapper();
await wrapper.setData({ label: 'a' });
            `.trim(),
        },
        {
            name: 'spares a call on a wrapper destructured out of such a helper',
            filename: convertedSpec,
            code: `
async function createWrapper() {
    const wrapper = mount({
        template: '<div><sw-converted /></div>',
        components: { 'sw-converted': await wrapTestComponent('sw-converted') },
        data() {
            return { label: 'initial' };
        },
    });

    return { wrapper, toggle: () => {} };
}

const { wrapper } = await createWrapper();
await wrapper.setData({ label: 'a' });
            `.trim(),
        },
        {
            name: 'is not gated by an index.vue in a directory the spec is not named after',
            filename: path.join(packageRoot, 'src/app/mixin/listing.mixin.spec.js'),
            code: `
const wrapper = mount(await wrapTestComponent('sw-converted'));
await wrapper.setData({ plain: 'x' });
            `.trim(),
        },
        {
            name: 'ignores a spec with no component beside it',
            filename: path.join(packageRoot, 'src/app/component/sw-absent/sw-absent.spec.js'),
            code: "await wrapper.setData({ plain: 'written' });",
        },
    ],
    invalid: [
        {
            name: 'rewrites a flat setData and adds the nextTick import',
            filename: convertedSpec,
            code: `
import { mount } from '@vue/test-utils';

it('renders', async () => {
    const wrapper = mount(await wrapTestComponent('sw-converted'));
    await wrapper.setData({ plain: 'written' });
});
            `.trim(),
            output: `
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';

it('renders', async () => {
    const wrapper = mount(await wrapTestComponent('sw-converted'));
    wrapper.vm.plain = 'written';
    await nextTick();
});
            `.trim(),
            errors: [{ messageId: 'silentNoOp' }],
        },
        {
            name: 'detects the component through a direct .vue import',
            filename: legacySpec,
            code: `
import Component from './index.vue';

await wrapper.setData({ plain: 'written' });
            `.trim(),
            output: `
import { nextTick } from 'vue';
import Component from './index.vue';

wrapper.vm.plain = 'written';
await nextTick();
            `.trim(),
            errors: [{ messageId: 'silentNoOp' }],
        },
        {
            name: 'detects the component through a sibling .vue file when the spec mounts it some other way',
            filename: convertedSpec,
            code: "await wrapper.setData({ plain: 'written' });",
            output: "import { nextTick } from 'vue';\nwrapper.vm.plain = 'written';\nawait nextTick();",
            errors: [{ messageId: 'silentNoOp' }],
        },
        {
            name: 'detects the component from a spec inside a `.spec` directory',
            filename: nestedConvertedSpec,
            code: "await wrapper.setData({ plain: 'written' });",
            output: "import { nextTick } from 'vue';\nwrapper.vm.plain = 'written';\nawait nextTick();",
            errors: [{ messageId: 'silentNoOp' }],
        },
        {
            name: 'writes one assignment per key and keeps the statement indentation',
            filename: convertedSpec,
            code: `
import { nextTick } from 'vue';

it('renders', async () => {
    await wrapper.setData({
        plain: 'written',
        total: 2,
    });
});
            `.trim(),
            output: `
import { nextTick } from 'vue';

it('renders', async () => {
    wrapper.vm.plain = 'written';
    wrapper.vm.total = 2;
    await nextTick();
});
            `.trim(),
            errors: [{ messageId: 'silentNoOp' }],
        },
        {
            name: 'reindents a multi-line value that loses a nesting level',
            filename: convertedSpec,
            code: `
import { nextTick } from 'vue';

it('renders', async () => {
    await wrapper.setData({
        records: [
            {
                name: 'name',
            },
        ],
    });
});
            `.trim(),
            output: `
import { nextTick } from 'vue';

it('renders', async () => {
    wrapper.vm.records = [
        {
            name: 'name',
        },
    ];
    await nextTick();
});
            `.trim(),
            errors: [{ messageId: 'silentNoOp' }],
        },
        {
            name: 'leaves the line starts of a template literal alone',
            filename: convertedSpec,
            code: [
                "import { nextTick } from 'vue';",
                '',
                'await wrapper.setData({',
                '    template: `<div>',
                '        <span />',
                '    </div>`,',
                '});',
            ].join('\n'),
            output: [
                "import { nextTick } from 'vue';",
                '',
                'wrapper.vm.template = `<div>',
                '        <span />',
                '    </div>`;',
                'await nextTick();',
            ].join('\n'),
            errors: [{ messageId: 'silentNoOp' }],
        },
        {
            name: 'adds nextTick to an existing vue import instead of a second import',
            filename: convertedSpec,
            code: `
import { ref } from 'vue';

await wrapper.setData({ plain: 'written' });
            `.trim(),
            output: `
import { ref, nextTick } from 'vue';

wrapper.vm.plain = 'written';
await nextTick();
            `.trim(),
            errors: [{ messageId: 'silentNoOp' }],
        },
        {
            name: 'adds a named group to a default-only vue import',
            filename: convertedSpec,
            code: `
import Vue from 'vue';

await wrapper.setData({ plain: 'written' });
            `.trim(),
            output: `
import Vue, { nextTick } from 'vue';

wrapper.vm.plain = 'written';
await nextTick();
            `.trim(),
            errors: [{ messageId: 'silentNoOp' }],
        },
        {
            name: 'reuses the local name of an aliased nextTick import',
            filename: convertedSpec,
            code: `
import { nextTick as tick } from 'vue';

await wrapper.setData({ plain: 'written' });
            `.trim(),
            output: `
import { nextTick as tick } from 'vue';

wrapper.vm.plain = 'written';
await tick();
            `.trim(),
            errors: [{ messageId: 'silentNoOp' }],
        },
        {
            name: 'imports nextTick once for several calls',
            filename: convertedSpec,
            code: `
import { mount } from '@vue/test-utils';

await wrapper.setData({ plain: 'first' });
await wrapper.setData({ plain: 'second' });
            `.trim(),
            output: `
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';

wrapper.vm.plain = 'first';
await nextTick();
wrapper.vm.plain = 'second';
await nextTick();
            `.trim(),
            errors: [
                { messageId: 'silentNoOp' },
                { messageId: 'silentNoOp' },
            ],
        },
        {
            name: 'brackets a key that is not a valid identifier',
            filename: convertedSpec,
            code: `
import { nextTick } from 'vue';

await wrapper.setData({ 'data-value': 'written' });
            `.trim(),
            output: `
import { nextTick } from 'vue';

wrapper.vm["data-value"] = 'written';
await nextTick();
            `.trim(),
            errors: [{ messageId: 'silentNoOp' }],
        },
        {
            name: 'still reports an inline host that keeps its state in setup(), where setData is just as dead',
            filename: convertedSpec,
            code: `
import { nextTick } from 'vue';

const wrapper = mount({
    template: '<div />',
    setup() {
        return { label: ref('initial') };
    },
});

await wrapper.setData({ label: 'a' });
            `.trim(),
            output: `
import { nextTick } from 'vue';

const wrapper = mount({
    template: '<div />',
    setup() {
        return { label: ref('initial') };
    },
});

wrapper.vm.label = 'a';
await nextTick();
            `.trim(),
            errors: [{ messageId: 'silentNoOp' }],
        },
        {
            name: 'still reports a call whose wrapper cannot be traced',
            filename: convertedSpec,
            code: `
const wrapper = someUntraceableFactory();
await wrapper.setData({ plain: 'x' });
            `.trim(),
            output: `
import { nextTick } from 'vue';
const wrapper = someUntraceableFactory();
wrapper.vm.plain = 'x';
await nextTick();
            `.trim(),
            errors: [{ messageId: 'silentNoOp' }],
        },
        {
            name: 'refuses a nested object literal, which merges only where a value already exists',
            filename: convertedSpec,
            code: 'await wrapper.setData({ trueSource: { mimeType: null } });',
            errors: [
                {
                    messageId: 'silentNoOpManualRewrite',
                    data: { blocker: 'a nested object literal merges into the existing value instead of replacing it' },
                },
            ],
        },
        {
            name: 'reports a spec whose component is untouched when assumeNativeSetup is set',
            filename: legacySpec,
            options: [
                { assumeNativeSetup: true },
            ],
            code: `
import { nextTick } from 'vue';

await wrapper.setData({ plain: 'x' });
            `.trim(),
            output: `
import { nextTick } from 'vue';

wrapper.vm.plain = 'x';
await nextTick();
            `.trim(),
            errors: [{ messageId: 'silentNoOp' }],
        },
        {
            name: 'refuses a $-prefixed key, which Vue reserves and refuses to assign',
            filename: convertedSpec,
            code: 'await wrapper.setData({ $refs: refsMock });',
            errors: [
                {
                    messageId: 'silentNoOpManualRewrite',
                    data: { blocker: 'a $-prefixed key is a reserved Vue property that cannot be assigned' },
                },
            ],
        },
        {
            name: 'refuses to rewrite an argument that is not an object literal',
            filename: convertedSpec,
            code: 'await wrapper.setData(conditionTreeMock);',
            errors: [
                {
                    messageId: 'silentNoOpManualRewrite',
                    data: { blocker: 'the argument is not an object literal' },
                },
            ],
        },
        {
            name: 'refuses to rewrite a spread, which hides the written keys',
            filename: convertedSpec,
            code: 'await wrapper.setData({ ...defaults });',
            errors: [
                {
                    messageId: 'silentNoOpManualRewrite',
                    data: { blocker: 'the object literal does not spell out plain key/value pairs' },
                },
            ],
        },
        {
            name: 'refuses to rewrite a call on a wrapper that is not a plain variable',
            filename: convertedSpec,
            code: "await wrapper.findComponent(stubs['sw-tabs']).setData({ active: 'products' });",
            errors: [
                {
                    messageId: 'silentNoOpManualRewrite',
                    data: { blocker: 'the wrapper is not a plain variable' },
                },
            ],
        },
        {
            name: 'refuses to rewrite a call that is not a standalone statement',
            filename: convertedSpec,
            code: "const done = await wrapper.setData({ plain: 'written' });",
            errors: [
                {
                    messageId: 'silentNoOpManualRewrite',
                    data: { blocker: 'the call is not a standalone awaited statement' },
                },
            ],
        },
        {
            name: 'refuses to rewrite a call that shares its line with other code',
            filename: convertedSpec,
            code: "if (shouldWrite) await wrapper.setData({ plain: 'written' });",
            errors: [
                {
                    messageId: 'silentNoOpManualRewrite',
                    data: { blocker: 'the statement does not start its own line' },
                },
            ],
        },
    ],
});
