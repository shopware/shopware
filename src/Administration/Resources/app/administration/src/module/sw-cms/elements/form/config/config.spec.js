/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper({ featureActive = false, formType = 'contact' } = {}) {
    return mount(await wrapTestComponent('sw-cms-el-config-form', { sync: true }), {
        global: {
            provide: {
                cmsService: Shopware.Service('cmsService'),
                feature: {
                    isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
                },
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
                    template:
                        '<div class="sw-tabs"><slot name="default" :active="active"></slot><slot name="content" :active="active"></slot></div>',
                    data() {
                        return {
                            active: 'options',
                        };
                    },
                },
                'sw-tabs-item': {
                    template: '<div class="sw-tabs-item"><slot></slot></div>',
                    props: [
                        'title',
                        'name',
                        'activeTab',
                    ],
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    emits: ['new-item-active'],
                    props: {
                        defaultItem: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                        items: {
                            type: Array,
                            required: true,
                        },
                        positionIdentifier: {
                            type: String,
                            required: true,
                        },
                    },
                    template: '<div class="mt-tabs"></div>',
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
                        value: formType,
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
        Shopware.Store.get('cmsPage').resetCmsPageState();
    });

    it('should render deprecated tabs when the major feature flag is inactive', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-tabs').exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        const wrapper = await createWrapper({ featureActive: true });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-cms-element-config-form');
        expect(tabs.props('defaultItem')).toBe('content');
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-cms.elements.general.config.tab.content',
                name: 'content',
            },
            {
                label: 'sw-cms.elements.general.config.tab.settings',
                name: 'options',
            },
        ]);
        expect(wrapper.find('.sw-tabs').exists()).toBe(false);
        expect(wrapper.find('.mt-select').exists()).toBe(true);
    });

    it('should not add the meteor options tab when the form type does not require configuration', async () => {
        const wrapper = await createWrapper({
            featureActive: true,
            formType: 'newsletter',
        });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-cms.elements.general.config.tab.content',
                name: 'content',
            },
        ]);
    });

    it('should switch meteor tab content when the active tab changes', async () => {
        const wrapper = await createWrapper({ featureActive: true });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        await tabs.vm.$emit('new-item-active', 'options');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeTab).toBe('options');
        expect(wrapper.find('.mt-select').exists()).toBe(false);
        expect(wrapper.find('.sw-tagged-field').exists()).toBe(true);
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
