/**
 * @sw-package framework
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';
import blockOverrideStore from '../../../../store/block-override.store';
import createDataScopeFixture from '../sw-block-override.spec/test-utils/create-data-scope-fixture';

/**
 * Mirrors what the bridge injects into a legacy Twig template: the original block body moves into the
 * `#parent` slot of a `sw-native-block-host`, and native `<sw-block extends>` overrides render on top.
 */
async function createWrapper({
    parentContent = '<div class="parent-content"></div>',
    extensions = '',
    hostMarkup = null,
    renderExtensions = true,
    extraData = {},
} = {}) {
    const host =
        hostMarkup ??
        `<sw-native-block-host name="test-extension-point" :data="$dataScope">
            <template #parent>${parentContent}</template>
        </sw-native-block-host>`;

    const wrapper = mount(
        {
            template: `
            <div class="component-root">
                ${host}
            </div>
            <template v-if="renderExtensions">
                ${extensions}
            </template>
        `,
            components: {
                'sw-native-block-host': await wrapTestComponent('sw-native-block-host', { sync: true }),
                'sw-block': await wrapTestComponent('sw-block', { sync: true }),
                'sw-block-parent': await wrapTestComponent('sw-block-parent', { sync: true }),
            },
            data() {
                return {
                    renderExtensions,
                    ...extraData,
                };
            },
        },
        {
            global: {
                plugins: [createDataScopeFixture()],
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

describe('sw-native-block-host', () => {
    beforeAll(() => {
        Shopware.Store.register('blockOverride', blockOverrideStore);
    });

    it('renders the merged Twig content from the parent slot', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.component-root > .parent-content').exists()).toBe(true);
    });

    it('renders nothing when the block body is empty and no extension is registered', async () => {
        const { wrapper } = await createWrapper({ parentContent: '' });

        expect(wrapper.findAll('.component-root > *')).toHaveLength(0);
    });

    it('replaces the merged Twig content with a native extension that has no parent', async () => {
        const { wrapper } = await createWrapper({
            extensions: `
                <sw-block extends="test-extension-point">
                    <div class="native-content"></div>
                </sw-block>
            `,
        });

        expect(wrapper.find('.component-root > .native-content').exists()).toBe(true);
        expect(wrapper.find('.parent-content').exists()).toBe(false);
    });

    it('renders the merged Twig content below a native extension using sw-block-parent', async () => {
        const { wrapper } = await createWrapper({
            extensions: `
                <sw-block extends="test-extension-point">
                    <sw-block-parent />
                    <div class="native-content"></div>
                </sw-block>
            `,
        });

        const children = wrapper.findAll('.component-root > *');

        expect(children).toHaveLength(2);
        expect(children[0].classes()).toContain('parent-content');
        expect(children[1].classes()).toContain('native-content');
    });

    it('chains multiple native extensions on the same block', async () => {
        const { wrapper } = await createWrapper({
            extensions: `
                <sw-block extends="test-extension-point">
                    <sw-block-parent />
                    <div class="first-native"></div>
                </sw-block>
                <sw-block extends="test-extension-point">
                    <sw-block-parent />
                    <div class="second-native"></div>
                </sw-block>
            `,
        });

        const children = wrapper.findAll('.component-root > *');

        expect(children.map((child) => child.classes()[0])).toEqual([
            'parent-content',
            'first-native',
            'second-native',
        ]);
    });

    it('renders the merged Twig content again once the extension unmounts', async () => {
        const { wrapper, toggleExtensions } = await createWrapper({
            extensions: `
                <sw-block extends="test-extension-point">
                    <div class="native-content"></div>
                </sw-block>
            `,
        });

        expect(wrapper.find('.native-content').exists()).toBe(true);

        await toggleExtensions();

        expect(wrapper.find('.native-content').exists()).toBe(false);
        expect(wrapper.find('.component-root > .parent-content').exists()).toBe(true);
    });

    it('exposes the host component data scope to the extension content', async () => {
        const { wrapper } = await createWrapper({
            extraData: { headline: 'From the host' },
            extensions: `
                <sw-block extends="test-extension-point" #default="{ headline }">
                    <div class="native-content">{{ headline }}</div>
                </sw-block>
            `,
        });

        expect(wrapper.find('.native-content').text()).toBe('From the host');
    });

    it('renders a native extension once when two hosts for the same block are nested', async () => {
        const { wrapper } = await createWrapper({
            hostMarkup: `
                <sw-native-block-host name="test-extension-point" :data="$dataScope">
                    <template #parent>
                        <sw-native-block-host name="test-extension-point" :data="$dataScope">
                            <template #parent><div class="parent-content"></div></template>
                        </sw-native-block-host>
                    </template>
                </sw-native-block-host>
            `,
            extensions: `
                <sw-block extends="test-extension-point">
                    <sw-block-parent />
                    <div class="native-content"></div>
                </sw-block>
            `,
        });

        expect(wrapper.findAll('.native-content')).toHaveLength(1);
        expect(wrapper.findAll('.parent-content')).toHaveLength(1);
    });
});
