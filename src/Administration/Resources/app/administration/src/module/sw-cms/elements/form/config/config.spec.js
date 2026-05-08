/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-config-form', { sync: true }), {
        global: {
            provide: {
                cmsService: Shopware.Service('cmsService'),
                systemConfigApiService: {
                    getValues: (query) => {
                        expect(query).toBe('core.basicInformation');
                        return {
                            'core.basicInformation.email': 'doNotReply@localhost',
                        };
                    },
                },
            },
            stubs: {
                'sw-tabs': {
                    name: 'sw-tabs',
                    template:
                        '<div class="sw-tabs"><slot name="default" :active="active"></slot><slot name="content" :active="active"></slot></div>',
                    data() {
                        return {
                            active: 'options',
                        };
                    },
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    props: ['items', 'defaultItem', 'positionIdentifier', 'routeExtensionTabs'],
                    template: '<mt-tabs-stub :items="items" :default-item="defaultItem" :position-identifier="positionIdentifier" :route-extension-tabs="routeExtensionTabs" @new-item-active="$emit(\'new-item-active\', $event)" />',
                },
                    'sw-extension-component-section': {
                        name: 'sw-extension-component-section',
                        props: ['positionIdentifier'],
                        template: '<div class="sw-extension-component-section"></div>',
                    },
                'sw-tabs-item': {
                    template: '<div class="sw-tabs-item"><slot></slot></div>',
                    props: [
                        'title',
                        'name',
                        'activeTab',
                    ],
                },
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'mt-select': {
                    template:
                        '<select class="mt-select" :value="modelValue" @change="$emit(`update:modelValue`, $event.target.value)"><slot></slot></select>',
                    props: [
                        'modelValue',
                        'options',
                        'disabled',
                    ],
                },
                'mt-text-field': {
                    template:
                        '<input class="mt-text-field" :value="modelValue" @input="$emit(`update:modelValue`, $event.target.value)" />',
                    props: [
                        'modelValue',
                        'disabled',
                    ],
                },
                'mt-textarea': {
                    template:
                        '<textarea class="mt-textarea" :value="modelValue" @input="$emit(`update:modelValue`, $event.target.value)" />',
                    props: [
                        'modelValue',
                        'disabled',
                    ],
                },
                'sw-tagged-field': {
                    template: '<div class="sw-tagged-field"></div>',
                    props: [
                        'value',
                        'name',
                        'placeholder',
                        'disabled',
                    ],
                },
                'sw-cms-inherit-wrapper': {
                    template: '<div><slot :isInherited="false"></slot></div>',
                    props: [
                        'field',
                        'element',
                        'contentEntity',
                        'label',
                    ],
                },
            },
        },
        props: {
            element: {
                config: {
                    mailReceiver: {
                        value: [],
                    },
                    defaultMailReceiver: {
                        value: true,
                    },
                    type: {
                        value: 'contact',
                    },
                    title: {
                        value: '',
                    },
                    confirmationText: {
                        value: '',
                    },
                },
            },
        },
    });
}

describe('module/sw-cms/elements/form/config/sw-cms-el-config-form', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/form');
    });

    afterEach(() => {
        global.activeFeatureFlags = [];
        Shopware.Store.get('cmsPage').resetCmsPageState();
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
            { label: 'sw-cms.elements.general.config.tab.settings', name: 'options' },
        ]);
        expect(mtTabs.props('defaultItem')).toBe('content');
        expect(mtTabs.props('routeExtensionTabs')).toBe(false);
    });

    it('updates active tab from mt-tabs events', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        await mtTabs.vm.$emit('new-item-active', { name: 'options' });
        expect(wrapper.vm.activeTab).toBe('options');

        await mtTabs.vm.$emit('new-item-active', 'content');
        expect(wrapper.vm.activeTab).toBe('content');
    });

    it('renders active mt-tabs panes when V6_8_0_0 is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-cms-el-config-form__tab-content').exists()).toBe(true);
        expect(wrapper.find('.sw-cms-el-config-form__tab-options').exists()).toBe(false);

        await wrapper.findComponent({ name: 'mt-tabs' }).vm.$emit('new-item-active', 'options');

        expect(wrapper.find('.sw-cms-el-config-form__tab-content').exists()).toBe(false);
        expect(wrapper.find('.sw-cms-el-config-form__tab-options').exists()).toBe(true);
    });


    it('renders registered extension tab content and ignores unknown tab ids when V6_8_0_0 is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        Shopware.Store.get('tabs').tabItems['sw-cms-element-config-form'] = [
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

        Shopware.Store.get('tabs').tabItems['sw-cms-element-config-form'] = [];
    });
    it('should add the core.basicInformation.email if it does not exist', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.element.config.mailReceiver.value).toEqual([
            'doNotReply@localhost',
        ]);
    });

    it('should keep email addresses at the end that do pass the check', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.getComponent('.sw-tagged-field').vm.$emit('update:value', [
            'valid@mail.com',
            'alsovalid@mail.com',
        ]);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.element.config.mailReceiver.value).toEqual([
            'valid@mail.com',
            'alsovalid@mail.com',
        ]);
    });

    it('should remove email addresses from the end that do not pass the check', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.getComponent('.sw-tagged-field').vm.$emit('update:value', [
            'valid@mail.com',
            'invalid',
        ]);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.element.config.mailReceiver.value).toEqual([
            'valid@mail.com',
        ]);
    });
});
