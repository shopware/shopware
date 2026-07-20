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
