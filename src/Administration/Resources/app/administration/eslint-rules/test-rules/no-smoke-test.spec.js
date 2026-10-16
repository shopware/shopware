const { RuleTester } = require('eslint');
const rule = require('./no-smoke-test');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const smokeTest = { messageId: 'smokeTest' };

ruleTester.run('no-smoke-test', rule, {
    valid: [
        {
            name: 'allows a test that checks the rendered output',
            code: `it('renders the title', async () => {
                const wrapper = await createWrapper();
                expect(wrapper.vm).toBeTruthy();
                expect(wrapper.text()).toContain('Title');
            });`,
        },
        {
            name: 'allows a test that checks a value on the instance',
            code: `it('is loading', () => {
                expect(wrapper.vm.isLoading).toBeTruthy();
            });`,
        },
        {
            name: 'counts expect helpers as assertions',
            code: `it('renders the grid', () => {
                expect(wrapper.vm).toBeTruthy();
                expectGridToRender(wrapper);
            });`,
        },
        {
            name: 'allows a test without assertions, which jest/expect-expect reports',
            code: `it('mounts', async () => {
                await createWrapper();
            });`,
        },
        {
            name: 'ignores existence checks outside of tests',
            code: `beforeEach(() => {
                expect(wrapper.vm).toBeTruthy();
            });`,
        },
        {
            name: 'ignores a test without a callback',
            code: `it.todo('renders the title');`,
        },
        {
            name: 'allows checking that a found element exists',
            code: `it('renders the button', () => {
                const button = wrapper.find('button');
                expect(button.exists()).toBe(true);
            });`,
        },
        {
            name: 'allows checking that the wrapper is gone',
            code: `it('unmounts', () => {
                wrapper.unmount();
                expect(wrapper.exists()).toBe(false);
            });`,
        },
        {
            name: 'allows wrapper.exists() next to a real assertion',
            code: `it('hides the pagination', () => {
                expect(wrapper.exists()).toBe(true);
                expect(wrapper.find('.sw-pagination').isVisible()).toBe(false);
            });`,
        },
    ],
    invalid: [
        {
            name: 'reports the generic Vue.js component smoke test',
            code: `it('should be a Vue.js component', () => {
                expect(wrapper.vm).toBeTruthy();
            });`,
            errors: [smokeTest],
        },
        {
            name: 'reports the smoke test under another title',
            code: `test('should render the component correctly', async () => {
                const wrapper = await createWrapper();
                expect(wrapper.vm).toBeDefined();
            });`,
            errors: [smokeTest],
        },
        {
            name: 'reports parameterized and feature flag tests',
            code: `it.each(['6.4.0', '7.0.0'])('shows version %s', async (version) => {
                await createWrapper(version);
                expect(wrapper.vm).toBeTruthy();
            });
            it.activeFeatureFlags(['V6_8_0_0'])('mounts with the flag', async () => {
                expect(component.vm).toBeTruthy();
            });`,
            errors: [
                smokeTest,
                smokeTest,
            ],
        },
        {
            name: 'reports only the smoke test of two neighbors',
            code: `describe('sw-version', () => {
                it('renders the version', () => {
                    expect(wrapper.text()).toBe('6.4.0');
                });
                it('mounts', () => {
                    expect(wrapper.vm).toBeTruthy();
                    expect(wrapper.vm).toBeTruthy();
                });
            });`,
            errors: [{ ...smokeTest, line: 5 }],
        },
        {
            name: 'reports a test that only checks that the wrapper exists',
            code: `it('should mount successfully', async () => {
                const wrapper = await createWrapper();
                expect(wrapper.exists()).toBe(true);
            });
            it('should not crash', async () => {
                await wrapper.setData({ canvas: null });
                expect(wrapper.exists()).toBeTruthy();
            });`,
            errors: [
                smokeTest,
                smokeTest,
            ],
        },
    ],
});
