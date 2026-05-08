/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper(additionalStubs = {}) {
    return mount(await wrapTestComponent('sw-cms-el-config-text', { sync: true }), {
        global: {
            provide: {
                cmsService: {
                    getCmsBlockRegistry: () => {
                        return {};
                    },
                    getCmsElementRegistry: () => {
                        return { text: {} };
                    },
                },
            },
            stubs: {
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-tabs': {
                    name: 'sw-tabs',
                    template: '<div class="sw-tabs"><slot></slot><slot name="content" :active="active"></slot></div>',
                    data() {
                        return { active: 'content' };
                    },
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    props: ['items', 'defaultItem', 'positionIdentifier', 'routeExtensionTabs'],
                    template: '<mt-tabs-stub :items="items" :default-item="defaultItem" :position-identifier="positionIdentifier" :route-extension-tabs="routeExtensionTabs" @new-item-active="$emit(\'new-item-active\', $event)" />',
                },
                'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                'sw-tabs-item': await wrapTestComponent('sw-tabs-item', {
                    sync: true,
                }),
                'sw-cms-mapping-field': await wrapTestComponent('sw-cms-mapping-field', { sync: true }),
                'sw-text-editor': {
                    props: ['value'],
                    emits: [
                        'blur',
                        'update:value',
                        'change',
                    ],
                    template:
                        '<input type="text" :value="value" @blur="$emit(\'blur\', $event.target.value)" @input="$emit(\'update:value\', $event.target.value)" @change="$emit(\'change\', $event.target.value)"></input>',
                },
                'sw-select-field': true,
                'sw-extension-component-section': {
                        name: 'sw-extension-component-section',
                        props: ['positionIdentifier'],
                        template: '<div class="sw-extension-component-section"></div>',
                    },
                'router-link': true,
                'sw-context-menu-item': true,
                'sw-context-button': true,
                'sw-cms-inherit-wrapper': {
                    template: '<div><slot :isInherited="false"></slot></div>',
                    props: [
                        'field',
                        'element',
                        'contentEntity',
                        'label',
                    ],
                },
                ...additionalStubs,
            },
        },
        props: {
            element: {
                config: {
                    content: {
                        value: '',
                    },
                    verticalAlign: {
                        value: null,
                    },
                },
            },
        },
    });
}

describe('src/module/sw-cms/elements/text/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    afterEach(() => {
        global.activeFeatureFlags = [];
    });

    it('renders legacy tabs when V6_8_0_0 is inactive', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('renders mt-tabs items when V6_8_0_0 is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        expect(mtTabs.exists()).toBe(true);
        expect(mtTabs.props('items')).toEqual([
            { label: 'sw-cms.elements.general.config.tab.content', name: 'content' },
            { label: 'sw-cms.elements.general.config.tab.settings', name: 'settings' },
        ]);
        expect(mtTabs.props('defaultItem')).toBe('content');
        expect(mtTabs.props('routeExtensionTabs')).toBe(false);
    });

    it('updates active tab from mt-tabs events', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        await mtTabs.vm.$emit('new-item-active', { name: 'settings' });
        expect(wrapper.vm.activeTab).toBe('settings');

        await mtTabs.vm.$emit('new-item-active', 'content');
        expect(wrapper.vm.activeTab).toBe('content');
    });

    it('renders active mt-tabs panes when V6_8_0_0 is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-cms-el-config-text__tab-content').exists()).toBe(true);
        expect(wrapper.find('.sw-cms-el-config-text__tab-settings').exists()).toBe(false);

        await wrapper.findComponent({ name: 'mt-tabs' }).vm.$emit('new-item-active', 'settings');

        expect(wrapper.find('.sw-cms-el-config-text__tab-content').exists()).toBe(false);
        expect(wrapper.find('.sw-cms-el-config-text__tab-settings').exists()).toBe(true);
    });


    it('renders registered extension tab content and ignores unknown tab ids when V6_8_0_0 is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        Shopware.Store.get('tabs').tabItems['sw-cms-element-config-text'] = [
            { componentSectionId: 'extension-tab' },
        ];

        const wrapper = await createWrapper();
        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        await mtTabs.vm.$emit('new-item-active', { name: 'extension-tab' });

        expect(wrapper.vm.activeTabIsExtensionTab).toBe(true);
        expect(wrapper.findComponent({ name: 'sw-extension-component-section' }).props('positionIdentifier')).toBe(
            'extension-tab',
        );

        await mtTabs.vm.$emit('new-item-active', 'unknown-tab');

        expect(wrapper.vm.activeTab).toBe('extension-tab');
        expect(wrapper.vm.activeTabIsExtensionTab).toBe(true);
        expect(wrapper.findComponent({ name: 'sw-extension-component-section' }).props('positionIdentifier')).toBe(
            'extension-tab',
        );

        Shopware.Store.get('tabs').tabItems['sw-cms-element-config-text'] = [];
    });
    it('should emits element-update when trigger @input event', async () => {
        const wrapper = await createWrapper();

        const updatedContent = 'Updated content';

        const input = wrapper.find('input[type="text"]');
        await input.setValue(updatedContent);

        expect(input.element.value).toBe(updatedContent);

        await input.trigger('input');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.element.config.content.value).toBe(updatedContent);
        expect(wrapper.emitted('element-update')).toBeTruthy();
        expect(wrapper.emitted()['element-update'][0][0]).toEqual(wrapper.vm.element);
    });

    it('should emits element-update when trigger @blur event', async () => {
        const wrapper = await createWrapper();

        const updatedContent = 'Updated content';

        const input = wrapper.find('input[type="text"]');
        await input.setValue(updatedContent);

        expect(input.element.value).toBe(updatedContent);

        await input.trigger('blur');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.element.config.content.value).toBe(updatedContent);
        expect(wrapper.emitted('element-update')).toBeTruthy();
        expect(wrapper.emitted()['element-update'][0][0]).toEqual(wrapper.vm.element);
    });

    describe('handleUpdateContent', () => {
        afterEach(() => {
            global.activeFeatureFlags = [];
        });

        it('should return true when textEditor ref is not available', async () => {
            const wrapper = await createWrapper();

            const result = await wrapper.vm.handleUpdateContent();

            expect(result).toBe(true);
        });

        it('should delegate to textEditor.validate and return true on success', async () => {
            const mockValidate = jest.fn(() => Promise.resolve(true));
            global.activeFeatureFlags = ['METEOR_TEXT_EDITOR'];

            const wrapper = await createWrapper({
                'mt-text-editor': {
                    template: '<div></div>',
                    methods: { validate: mockValidate },
                },
            });
            await flushPromises();

            const result = await wrapper.vm.handleUpdateContent();

            expect(mockValidate).toHaveBeenCalledTimes(1);
            expect(result).toBe(true);
        });

        it('should return false when textEditor.validate reports invalid content', async () => {
            const mockValidate = jest.fn(() => Promise.resolve(false));
            global.activeFeatureFlags = ['METEOR_TEXT_EDITOR'];

            const wrapper = await createWrapper({
                'mt-text-editor': {
                    template: '<div></div>',
                    methods: { validate: mockValidate },
                },
            });
            await flushPromises();

            const result = await wrapper.vm.handleUpdateContent();

            expect(mockValidate).toHaveBeenCalledTimes(1);
            expect(result).toBe(false);
        });
    });
});
