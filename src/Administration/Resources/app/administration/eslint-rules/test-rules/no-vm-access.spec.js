/**
 * @sw-package framework
 */

const fs = require('fs');
const path = require('path');
const { RuleTester } = require('eslint');
const rule = require('./no-vm-access');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const message = 'Test what the user sees. `.vm` reaches into component internals.';

// A made-up mini-module, so the rule finds the index.ts whose public lists it reads.
const moduleRoot = 'src/core/example-module';
const publicApiSpec = `${moduleRoot}/tests/public-api.spec.ts`;
const indexFile = path.join(process.cwd(), moduleRoot, 'index.ts');
const indexSource = `
import { definePublicComponent } from 'src/core/public-component';

/** @public */
export default definePublicComponent({
    name: 'sw-example',
    load: () => import('./src/sw-example'),
    public: {
        props: ['disabled'],
        blocks: ['sw_example'],
        computed: ['usedRules'],
        methods: ['onSave'],
        setup: ['count'],
    },
});`;
const allowed = 'disabled, usedRules, onSave, count';

// A nested private module in the src/ of the made-up one, whose private.ts lists what its parent may use.
const nestedRoot = `${moduleRoot}/src/example-card`;
const privateApiSpec = `${nestedRoot}/tests/private-api.spec.ts`;
const privateFile = path.join(process.cwd(), nestedRoot, 'private.ts');
const privateSource = `
/** @private */
export default definePrivateComponent({
    load: () => import('./src/example-card'),
    public: { props: ['service'], computed: ['readableVersion'] },
});`;
const moduleFolders = [
    path.join(process.cwd(), moduleRoot, 'src'),
    path.join(process.cwd(), moduleRoot, 'tests'),
    path.join(process.cwd(), nestedRoot, 'src'),
    path.join(process.cwd(), nestedRoot, 'tests'),
];
const sources = { [indexFile]: indexSource, [privateFile]: privateSource };

const realExistsSync = fs.existsSync;
const realReadFileSync = fs.readFileSync;

describe('no-vm-access', () => {
    beforeEach(() => {
        jest.spyOn(fs, 'existsSync').mockImplementation(
            (file) => Object.hasOwn(sources, file) || moduleFolders.includes(file) || realExistsSync(file),
        );
        jest.spyOn(fs, 'readFileSync').mockImplementation((file, ...rest) =>
            Object.hasOwn(sources, file) ? sources[file] : realReadFileSync(file, ...rest),
        );
    });

    ruleTester.run('no-vm-access', rule, {
        valid: [
            {
                name: 'allows assertions on the rendered output',
                code: "expect(wrapper.find('.sw-modal').exists()).toBe(true);",
            },
            {
                name: 'allows emitted events',
                code: "expect(wrapper.emitted('save')).toHaveLength(1);",
            },
            {
                name: 'allows a variable named vm',
                code: 'const vm = createViewModel(); expect(vm.items).toHaveLength(2);',
            },
            {
                name: 'allows simulating an event from a child component',
                code: "await wrapper.findComponent('.sw-modal').vm.$emit('modal-close');",
            },
            {
                name: 'allows simulating an event from a stored child component',
                code: "const modal = wrapper.findComponent('.sw-modal'); await modal.vm.$emit('modal-close');",
            },
            {
                name: 'allows a public-api spec to read a public computed',
                filename: publicApiSpec,
                code: 'expect(wrapper.vm.usedRules).toEqual([]);',
            },
            {
                name: 'allows a public-api spec to call a public method',
                filename: publicApiSpec,
                code: 'wrapper.vm.onSave();',
            },
            {
                name: 'allows a public-api spec to read a public prop',
                filename: publicApiSpec,
                code: 'expect(wrapper.vm.disabled).toBe(false);',
            },
            {
                name: 'allows a private-api spec to read a member its private.ts lists',
                filename: privateApiSpec,
                code: "expect(wrapper.vm.readableVersion).toBe('1.0.0');",
            },
            {
                name: 'allows a public-api spec to read a native-setup binding',
                filename: publicApiSpec,
                code: 'expect(wrapper.vm.count).toBe(0);',
            },
        ],
        invalid: [
            {
                name: 'reports calls on the component instance',
                code: "await wrapper.vm.runBulkAction('install');",
                errors: [{ message }],
            },
            {
                name: 'reports reads from child component instances',
                code: "expect(wrapper.findComponent('.sw-modal').vm.isLoading).toBe(true);",
                errors: [{ message }],
            },
            {
                name: 'reports waiting through the component instance',
                code: 'await wrapper.vm.$nextTick();',
                errors: [{ message }],
            },
            {
                name: 'reports emitting an event on the mounted component',
                code: "wrapper.vm.$emit('update:value', 'new');",
                errors: [{ message }],
            },
            {
                name: 'reports other members that start like $emit',
                code: 'expect(wrapper.vm.$emitted).toBeDefined();',
                errors: [{ message }],
            },
            {
                name: 'reports public members outside a public-api spec',
                filename: `${moduleRoot}/tests/pinned.spec.ts`,
                code: 'expect(wrapper.vm.usedRules).toEqual([]);',
                errors: [{ message }],
            },
            {
                name: 'reports a member that index.ts does not list, and names the listed ones',
                filename: publicApiSpec,
                code: 'expect(wrapper.vm.isLoading).toBe(true);',
                errors: [
                    {
                        message: `\`isLoading\` is not listed. A public-api spec reads only the members index.ts lists: ${allowed}.`,
                    },
                ],
            },
            {
                name: 'reports a member that the private.ts of a nested module does not list',
                filename: privateApiSpec,
                code: 'expect(wrapper.vm.isLoading).toBe(true);',
                errors: [
                    {
                        message:
                            '`isLoading` is not listed. A private-api spec reads only the members private.ts lists: service, readableVersion.',
                    },
                ],
            },
            {
                name: 'reports a block name, which is no instance member',
                filename: publicApiSpec,
                code: 'expect(wrapper.vm.sw_example).toBeDefined();',
                errors: [{ messageId: 'notPublic' }],
            },
            {
                name: 'reports Vue internals in a public-api spec',
                filename: publicApiSpec,
                code: 'await wrapper.vm.$nextTick();',
                errors: [{ messageId: 'notPublic' }],
            },
            {
                name: 'reports writing a public member',
                filename: publicApiSpec,
                code: 'wrapper.vm.disabled = true;',
                errors: [{ messageId: 'notARead' }],
            },
            {
                name: 'reports destructuring the instance',
                filename: publicApiSpec,
                code: 'const { usedRules } = wrapper.vm;',
                errors: [{ messageId: 'notARead' }],
            },
            {
                name: 'reports a computed key',
                filename: publicApiSpec,
                code: "expect(wrapper.vm['usedRules']).toEqual([]);",
                errors: [{ messageId: 'notARead' }],
            },
            {
                name: 'reports public names on a child component',
                filename: publicApiSpec,
                code: "expect(wrapper.findComponent('.sw-child').vm.usedRules).toEqual([]);",
                errors: [{ messageId: 'childAccess' }],
            },
            {
                name: 'allows nothing in a public-api spec outside a mini-module',
                filename: 'src/app/tests/public-api.spec.ts',
                code: 'expect(wrapper.vm.usedRules).toEqual([]);',
                errors: [
                    { message: '`usedRules` is not listed. A public-api spec reads only the members index.ts lists: none.' },
                ],
            },
        ],
    });
});
