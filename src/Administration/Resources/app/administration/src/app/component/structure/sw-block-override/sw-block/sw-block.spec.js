/**
 * @sw-package framework
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';
import blockOverrideStore from '../../../../store/block-override.store';
import getBlockDataScope from './get-block-data-scope';

async function createWrapper({
    extensions = '',
    defaultContent = '<div class="default-content"></div>',
    renderExtensions = true,
    moreBlockExtensions = '',
    extraData = {},
    extraOptions = {},
} = {}) {
    const wrapper = mount(
        {
            template: `
            <div class="component-root">
                <sw-block name="test-extension-point" :data="$dataScope()">
                    ${defaultContent}
                </sw-block>
            </div>
            ${moreBlockExtensions}
            <template v-if="renderExtensions">
                ${extensions}
            </template>
        `,
            components: {
                'sw-block': await wrapTestComponent('sw-block', { sync: true }),
                'sw-block-parent': await wrapTestComponent('sw-block-parent', { sync: true }),
            },
            data() {
                return {
                    renderExtensions,
                    ...extraData,
                };
            },
            ...extraOptions,
        },
        {
            global: {
                mocks: {
                    $dataScope: getBlockDataScope,
                },
            },
        },
    );

    async function toggleExtensions() {
        await wrapper.setData({
            renderExtensions: !wrapper.vm.renderExtensions,
        });
    }

    return {
        wrapper,
        toggleExtensions,
    };
}

describe('sw-block', () => {
    beforeAll(() => {
        Shopware.Store.register('blockOverride', blockOverrideStore);
    });

    it('renders the default content inside the `block`', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.component-root > .default-content').exists()).toBeTruthy();
    });

    it('renders nothing if the `block` has no default content and there is no override', async () => {
        const { wrapper } = await createWrapper({
            defaultContent: '',
        });

        expect(wrapper.findAll('.component-root > *')).toHaveLength(0);
    });

    it('renders the `block` overridden content without default content', async () => {
        const { wrapper } = await createWrapper({
            extensions: `
                <sw-block extends="test-extension-point">
                    <div class="extension-content"></div>
                </sw-block>
            `,
        });

        expect(wrapper.find('.component-root > .default-content').exists()).toBeFalsy();
        expect(wrapper.find('.component-root > .extension-content').exists()).toBeTruthy();
    });

    it('renders content from last `block` override when there are multiple overrides and not `block-parent` is used', async () => {
        const { wrapper } = await createWrapper({
            extensions: `
                <sw-block extends="test-extension-point">
                    <div class="extension-content-1"></div>
                </sw-block>
                <sw-block extends="test-extension-point">
                    <div class="extension-content-2"></div>
                </sw-block>
            `,
        });

        expect(wrapper.find('.component-root > .default-content').exists()).toBeFalsy();
        expect(wrapper.find('.component-root > .extension-content-1').exists()).toBeFalsy();
        expect(wrapper.find('.component-root > .extension-content-2').exists()).toBeTruthy();
    });

    it('renders content from the parent before the `block` override', async () => {
        const { wrapper } = await createWrapper({
            extensions: `
                <sw-block extends="test-extension-point">
                    <sw-block-parent/>
                    <div class="extension-content"></div>
                </sw-block>
            `,
        });

        expect(wrapper.find('.component-root > .default-content + .extension-content').exists()).toBeTruthy();
    });

    it('renders content from the parent after the `block` override', async () => {
        const { wrapper } = await createWrapper({
            extensions: `
                <sw-block extends="test-extension-point">
                    <div class="extension-content"></div>
                    <sw-block-parent/>
                </sw-block>
            `,
        });

        expect(wrapper.find('.component-root > .extension-content + .default-content').exists()).toBeTruthy();
    });

    it('renders parent content from multiple `block`s', async () => {
        const { wrapper } = await createWrapper({
            extensions: `
                <sw-block extends="test-extension-point">
                    <sw-block-parent/>
                    <div class="extension-content-1"></div>
                </sw-block>

                <sw-block extends="test-extension-point">
                    <sw-block-parent/>
                    <div class="extension-content-2"></div>
                </sw-block>
            `,
        });

        expect(wrapper.find('.component-root > .default-content').exists()).toBeTruthy();
        expect(wrapper.find('.component-root > .extension-content-1').exists()).toBeTruthy();
        expect(wrapper.find('.extension-content-2').exists()).toBeTruthy();
        expect(wrapper.find('.default-content + .extension-content-1 + .extension-content-2').exists()).toBeTruthy();
    });

    it('does not render the `block` if this is not rendered', async () => {
        const { wrapper } = await createWrapper({
            renderExtensions: false,
            extensions: `
                <sw-block extends="test-extension-point">
                    <div class="extension-content"></div>
                </sw-block>
            `,
        });

        expect(wrapper.find('.component-root > .extension-content').exists()).toBeFalsy();
        expect(wrapper.find('.component-root > .default-content').exists()).toBeTruthy();
    });

    it('renders the `block` content only once if the extension component mounted and unmounted', async () => {
        const { wrapper, toggleExtensions } = await createWrapper({
            renderExtensions: false,
            extensions: `
                <sw-block extends="test-extension-point">
                    <sw-block-parent/>
                    <div class="extension-content"></div>
                </sw-block>
            `,
        });

        expect(wrapper.find('.component-root > .default-content').exists()).toBeTruthy();
        expect(wrapper.find('.component-root > .extension-content').exists()).toBeFalsy();

        await toggleExtensions();

        expect(wrapper.find('.component-root > .default-content').exists()).toBeTruthy();
        expect(wrapper.find('.component-root > .extension-content').exists()).toBeTruthy();

        await toggleExtensions();

        expect(wrapper.find('.component-root > .default-content').exists()).toBeTruthy();
        expect(wrapper.find('.component-root > .extension-content').exists()).toBeFalsy();

        await toggleExtensions();

        expect(wrapper.find('.component-root > .default-content').exists()).toBeTruthy();
        expect(wrapper.find('.component-root > .extension-content').exists()).toBeTruthy();
        expect(wrapper.findAll('.extension-content')).toHaveLength(1);
    });

    it('renders multiple `block` overrides', async () => {
        const { wrapper } = await createWrapper({
            moreBlockExtensions: `
                <div class="component-root-2">
                    <sw-block  name="test-extension-point-2">
                        <div class="default-content-2"></div>
                    </sw-block >
                </div>
            `,
            extensions: `
                <sw-block extends="test-extension-point">
                    <sw-block-parent/>
                    <div class="extension-content-1"></div>
                </sw-block>

                <sw-block extends="test-extension-point">
                    <sw-block-parent/>
                    <div class="extension-content-2"></div>
                </sw-block>

                <sw-block extends="test-extension-point-2">
                    <sw-block-parent/>
                    <div class="extension-content-3"></div>
                </sw-block>

                <sw-block extends="test-extension-point-2">
                    <sw-block-parent/>
                    <div class="extension-content-4"></div>
                </sw-block>
            `,
        });

        expect(wrapper.find('.component-root > .default-content').exists()).toBeTruthy();
        expect(wrapper.find('.component-root > .extension-content-1').exists()).toBeTruthy();
        expect(wrapper.find('.component-root > .extension-content-2').exists()).toBeTruthy();
        expect(wrapper.find('.default-content + .extension-content-1 + .extension-content-2').exists()).toBeTruthy();

        expect(wrapper.find('.component-root-2 > .default-content-2').exists()).toBeTruthy();
        expect(wrapper.find('.component-root-2 > .extension-content-3').exists()).toBeTruthy();
        expect(wrapper.find('.component-root-2 > .extension-content-4').exists()).toBeTruthy();
        expect(wrapper.find('.default-content-2 + .extension-content-3 + .extension-content-4').exists()).toBeTruthy();
    });

    it('does not render anything if the `block` name to extend does not exist', async () => {
        const { wrapper } = await createWrapper({
            extensions: `
                <sw-block extends="NOT-EXISTING-extension-point">
                     <sw-block-parent/>
                     <div class="extension-content"></div>
                </sw-block>
              `,
        });

        expect(wrapper.find('.component-root > .default-content').exists()).toBeTruthy();
        expect(wrapper.find('.extension-content').exists()).toBeFalsy();
    });

    it('renders multiple nested blocks', async () => {
        const { wrapper } = await createWrapper({
            defaultContent: `
                    <div class="default-content"></div>
                    <sw-block name="test-extension-point-2">
                        <div class="default-content-2"></div>

                        <sw-block name="test-extension-point-3">
                            <div class="default-content-3"></div>
                        </sw-block>
                    </sw-block>
            `,
            extensions: `
                <sw-block extends="test-extension-point">
                    <sw-block-parent/>
                    <div class="extension-content-1"></div>
                </sw-block>

                <sw-block extends="test-extension-point-2">
                    <sw-block-parent/>
                    <div class="extension-content-2"></div>
                </sw-block>

                <sw-block extends="test-extension-point-3">
                    <sw-block-parent/>
                    <div class="extension-content-3"></div>
                </sw-block>
            `,
        });

        expect(wrapper.find('.component-root > .default-content').exists()).toBeTruthy();
        expect(wrapper.find('.component-root > .default-content-2').exists()).toBeTruthy();
        expect(wrapper.find('.component-root > .default-content-3').exists()).toBeTruthy();
        expect(wrapper.find('.component-root > .extension-content-3').exists()).toBeTruthy();
        expect(wrapper.find('.component-root > .extension-content-2').exists()).toBeTruthy();
        expect(wrapper.find('.component-root > .extension-content-1').exists()).toBeTruthy();
        expect(
            wrapper
                .find(
                    '.default-content + .default-content-2 + .default-content-3 + .extension-content-3 + .extension-content-2 + .extension-content-1',
                )
                .exists(),
        ).toBeTruthy();
    });

    it('renders parent content exactly once after multiple reactive re-renders and remounting the extension', async () => {
        // Regression guard for the providedParents accumulation bug in sw-block's
        // computed. The old push() implementation appended an entry to providedParents
        // on every computed re-run without a matching pop (sw-block-parent only pops
        // at setup time, not on every re-render). After remounting the extension a
        // fresh sw-block-parent pops from that array; this verifies the DOM stays correct.
        const { wrapper, toggleExtensions } = await createWrapper({
            extraData: { label: 'initial' },
            defaultContent: '<div class="default-content">{{ label }}</div>',
            extensions: `
                <sw-block extends="test-extension-point">
                    <sw-block-parent/>
                    <div class="extension-content"></div>
                </sw-block>
            `,
        });

        // Each setData triggers a reactive re-render that causes sw-block's computed
        // to run again. With the old push(), each run added a stale entry.
        await wrapper.setData({ label: 'a' });
        await wrapper.setData({ label: 'ab' });
        await wrapper.setData({ label: 'abc' });

        // Unmount then remount the extension to create a fresh sw-block-parent
        // instance that runs setup() and pops from providedParents.
        await toggleExtensions();
        await toggleExtensions();

        expect(wrapper.findAll('.default-content')).toHaveLength(1);
        expect(wrapper.findAll('.extension-content')).toHaveLength(1);
        expect(wrapper.find('.default-content + .extension-content').exists()).toBeTruthy();
    });

    it('preserves the DOM element identity of rendered block content across reactive re-renders', async () => {
        // When sw-block's computed re-runs it returns fresh VNodes, but Vue must
        // recognise the element as the same type and update it in-place rather than
        // recreating the DOM node, which would strip focus from active inputs.
        const { wrapper } = await createWrapper({
            extraData: { label: 'initial' },
            defaultContent: '<div class="default-content">{{ label }}</div>',
        });

        const domNodeBefore = wrapper.find('.default-content').element;

        await wrapper.setData({ label: 'a' });
        await wrapper.setData({ label: 'ab' });
        await wrapper.setData({ label: 'abc' });

        expect(wrapper.find('.default-content').element).toBe(domNodeBefore);
        expect(wrapper.find('.default-content').text()).toBe('abc');
    });

    it('has access to the component data scope', async () => {
        const { wrapper } = await createWrapper({
            extraData: {
                testData: 'Hello World',
            },
            extraOptions: {
                methods: {
                    testMethod(param) {
                        return `This is a method with parameter: ${param}`;
                    },
                },
                computed: {
                    testComputed() {
                        return 'This is a computed';
                    },
                },
            },
            extensions: `
                <sw-block extends="test-extension-point" #default="{testData, testMethod, testComputed}">
                    <sw-block-parent/>
                    <div class="extension-content-1">{{testData}}</div>
                    <div class="extension-content-2">{{testMethod('param')}}</div>
                    <div class="extension-content-3">{{testComputed}}</div>
                </sw-block>
            `,
        });

        expect(wrapper.find('.component-root > .extension-content-1').text()).toBe('Hello World');
        expect(wrapper.find('.component-root > .extension-content-2').text()).toBe('This is a method with parameter: param');
        expect(wrapper.find('.component-root > .extension-content-3').text()).toBe('This is a computed');
    });

    // ─── DEV-mode guard: props.name change after mount ────────────────────────
    //
    // sw-block registers a watch (guarded by process.env.NODE_ENV !== 'production')
    // that warns if the `name` prop changes after mount, because the shim slots and
    // block context bindings are computed once in setup() and cannot be rebound.
    // Jest runs with NODE_ENV='test', so the watch is active in these tests.

    describe('DEV-mode guard for dynamic "name" prop (T-3)', () => {
        let consoleSpy;

        beforeEach(() => {
            consoleSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
        });

        afterEach(() => {
            consoleSpy.mockRestore();
        });

        it('emits a console.warn when the "name" prop changes after the initial mount', async () => {
            const wrapper = await mount(
                {
                    template: `
                        <sw-block :name="blockName" :data="$dataScope()">
                            <div class="content"></div>
                        </sw-block>
                    `,
                    components: {
                        'sw-block': await wrapTestComponent('sw-block', { sync: true }),
                    },
                    data() {
                        return { blockName: 'original-block-name' };
                    },
                },
                {
                    global: { mocks: { $dataScope: getBlockDataScope } },
                },
            );

            await wrapper.setData({ blockName: 'changed-block-name' });

            expect(consoleSpy).toHaveBeenCalledWith(expect.stringContaining('[sw-block]'));
            expect(consoleSpy).toHaveBeenCalledWith(expect.stringContaining('"name" prop changed'));
            expect(consoleSpy).toHaveBeenCalledWith(expect.stringContaining('original-block-name'));
            expect(consoleSpy).toHaveBeenCalledWith(expect.stringContaining('changed-block-name'));
        });

        it('does not emit a console.warn on initial mount — the watch fires only on subsequent changes', async () => {
            await mount(
                {
                    template: `
                        <sw-block :name="blockName" :data="$dataScope()">
                            <div class="content"></div>
                        </sw-block>
                    `,
                    components: {
                        'sw-block': await wrapTestComponent('sw-block', { sync: true }),
                    },
                    data() {
                        return { blockName: 'initial-block-name' };
                    },
                },
                {
                    global: { mocks: { $dataScope: getBlockDataScope } },
                },
            );

            expect(consoleSpy).not.toHaveBeenCalled();
        });
    });
});
