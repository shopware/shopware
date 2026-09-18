const { RuleTester } = require('eslint');
const rule = require('./no-set-data');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

ruleTester.run('no-set-data', rule, {
    valid: [
        {
            name: 'leaves other wrapper calls alone',
            code: 'await wrapper.setProps({ isLoading: false });',
        },
        {
            name: 'leaves a setData that is not a member call alone',
            code: 'await setComponentData(wrapper, { isLoading: false });',
        },
    ],
    invalid: [
        {
            name: 'rewrites a flat setData and adds the nextTick import',
            code: `
import { mount } from '@vue/test-utils';

it('renders', async () => {
    const wrapper = mount(await wrapTestComponent('sw-thing'));
    await wrapper.setData({ plain: 'written' });
});
            `.trim(),
            output: `
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';

it('renders', async () => {
    const wrapper = mount(await wrapTestComponent('sw-thing'));
    wrapper.vm.plain = 'written';
    await nextTick();
});
            `.trim(),
            errors: [{ messageId: 'silentNoOp' }],
        },
        {
            name: 'reports a spec that mounts an inline Options API component too',
            code: `
import { nextTick } from 'vue';

const wrapper = mount({ template: '<div />', data: () => ({ plain: 'a' }) });
await wrapper.setData({ plain: 'written' });
            `.trim(),
            output: `
import { nextTick } from 'vue';

const wrapper = mount({ template: '<div />', data: () => ({ plain: 'a' }) });
wrapper.vm.plain = 'written';
await nextTick();
            `.trim(),
            errors: [{ messageId: 'silentNoOp' }],
        },
        {
            name: 'writes one assignment per key and keeps the statement indentation',
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
            name: 'refuses a nested object literal, which merges only where a value already exists',
            code: 'await wrapper.setData({ trueSource: { mimeType: null } });',
            errors: [
                {
                    messageId: 'silentNoOpManualRewrite',
                    data: { blocker: 'a nested object literal merges into the existing value instead of replacing it' },
                },
            ],
        },
        {
            name: 'refuses a $-prefixed key, which Vue reserves and refuses to assign',
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
