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
});
