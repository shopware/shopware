const { RuleTester } = require('eslint');
const rule = require('./no-vm-access');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const message = 'Test what the user sees. `.vm` reaches into component internals.';

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
    ],
});
