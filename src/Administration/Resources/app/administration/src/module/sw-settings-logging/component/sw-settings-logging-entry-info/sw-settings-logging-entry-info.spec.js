/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

const defaultLogEntry = {
    context: {
        foo: 'bar',
    },
};

const mailLogEntry = {
    context: {
        additionalData: {
            recipients: {
                'shopware@example.com': {},
            },
            contents: {
                'text/html': '<strong>HTML mail body</strong>',
                'text/plain': 'Plain mail body',
            },
        },
    },
};

async function createWrapper({
    componentName = 'sw-settings-logging-entry-info',
    featureActive = false,
    logEntry = defaultLogEntry,
} = {}) {
    return mount(await wrapTestComponent(componentName, { sync: true }), {
        props: {
            logEntry,
        },
        global: {
            stubs: {
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                    props: [
                        'title',
                    ],
                },
                'sw-tabs': {
                    name: 'sw-tabs',
                    template: '<div class="sw-tabs"><slot></slot><slot name="content"></slot></div>',
                    props: [
                        'positionIdentifier',
                    ],
                },
                'sw-tabs-item': {
                    name: 'sw-tabs-item',
                    template: '<button class="sw-tabs-item" type="button"><slot></slot></button>',
                    props: [
                        'active',
                    ],
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    emits: [
                        'new-item-active',
                    ],
                    template: '<div class="mt-tabs"></div>',
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
                },
                'mt-button': true,
                'mt-textarea': {
                    name: 'mt-textarea',
                    template: '<textarea class="mt-textarea" :value="modelValue"></textarea>',
                    props: {
                        modelValue: {
                            type: String,
                            required: false,
                            default: '',
                        },
                    },
                },
            },
            provide: {
                feature: {
                    isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
                },
            },
        },
    });
}

describe('src/module/sw-settings-logging/component/sw-settings-logging-entry-info', () => {
    it('should render the fallback tabs branch while the major feature flag is inactive', async () => {
        const wrapper = await createWrapper();

        const tabs = wrapper.getComponent({ name: 'sw-tabs' });
        const tabItem = wrapper.getComponent({ name: 'sw-tabs-item' });

        expect(tabs.props('positionIdentifier')).toBe('sw-settings-logging-entry-info');
        expect(tabItem.props('active')).toBe(true);
        expect(tabItem.text()).toBe('sw-settings-logging.entryInfo.tabRaw');
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
        expect(wrapper.getComponent({ name: 'mt-textarea' }).props('modelValue')).toBe(
            JSON.stringify(defaultLogEntry.context, null, 2),
        );
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        const wrapper = await createWrapper({
            featureActive: true,
        });

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-settings-logging-entry-info');
        expect(tabs.props('defaultItem')).toBe('raw');
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-settings-logging.entryInfo.tabRaw',
                name: 'raw',
            },
        ]);
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
        expect(wrapper.getComponent({ name: 'mt-textarea' }).props('modelValue')).toBe(
            JSON.stringify(defaultLogEntry.context, null, 2),
        );
    });

    it('should update the active tab from meteor tab events', async () => {
        const wrapper = await createWrapper({
            featureActive: true,
        });

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });
        await tabs.vm.$emit('new-item-active', 'inactive');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeTab).toBe('inactive');
        expect(wrapper.findComponent({ name: 'mt-textarea' }).exists()).toBe(false);
    });

    it('should render mail sent meteor tabs and content when the major feature flag is active', async () => {
        const wrapper = await createWrapper({
            componentName: 'sw-settings-logging-mail-sent-info',
            featureActive: true,
            logEntry: mailLogEntry,
        });

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-settings-logging.mailInfo.tabHTML',
                name: 'html',
            },
            {
                label: 'sw-settings-logging.mailInfo.tabPlain',
                name: 'plain',
            },
            {
                label: 'sw-settings-logging.entryInfo.tabRaw',
                name: 'raw',
            },
        ]);
        expect(wrapper.text()).toContain('sw-settings-logging.mailInfo.recipientsTitle: shopware@example.com');
        expect(wrapper.html()).toContain('<strong>HTML mail body</strong>');

        await tabs.vm.$emit('new-item-active', 'plain');
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Plain mail body');

        await tabs.vm.$emit('new-item-active', 'raw');
        await wrapper.vm.$nextTick();

        expect(wrapper.getComponent({ name: 'mt-textarea' }).props('modelValue')).toBe(
            JSON.stringify(mailLogEntry.context, null, 2),
        );
    });
});
