/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

Shopware.Mixin.register('cms-element', {
    props: {
        element: {
            type: Object,
            required: true,
        },
    },
    methods: {
        initElementConfig() {},
    },
});

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-config-form', { sync: true }), {
        global: {
            provide: {
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
                    template: '<div class="sw-tabs"><slot name="default" :active="active"></slot><slot name="content" :active="active"></slot></div>',
                    data() {
                        return {
                            active: 'options',
                        };
                    },
                },
                'sw-tabs-item': {
                    template: '<div class="sw-tabs-item"><slot></slot></div>',
                    props: ['title', 'name', 'activeTab'],
                },
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-select-field': {
                    template: '<select class="sw-select-field" :value="value" @change="$emit(`update:value`, $ev.target.value)"><slot></slot></select>',
                    props: ['value'],
                },
                'sw-text-field': {
                    template: '<input class="sw-text-field" :value="value" @input="$emit(`update:value`, $ev.target.value)" />',
                    props: ['value'],
                },
                'sw-textarea-field': {
                    template: '<textarea class="sw-textarea-field" :value="value" @input="$emit(`update:value`, $ev.target.value)" />',
                    props: ['value'],
                },
                'sw-tagged-field': {
                    template: '<div class="sw-tagged-field"></div>',
                    props: ['value'],
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
    it('should add the core.basicInformation.email if it does not exist', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.element.config.mailReceiver.value).toEqual(['doNotReply@localhost']);
    });

    it('should keep email addresses at the end that do pass the check', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.getComponent('.sw-tagged-field').vm.$emit('update:value', ['valid@mail.com', 'alsovalid@mail.com']);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.element.config.mailReceiver.value).toEqual(['valid@mail.com', 'alsovalid@mail.com']);
    });

    it('should remove email addresses from the end that do not pass the check', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.getComponent('.sw-tagged-field').vm.$emit('update:value', ['valid@mail.com', 'invalid']);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.element.config.mailReceiver.value).toEqual(['valid@mail.com']);
    });
});
