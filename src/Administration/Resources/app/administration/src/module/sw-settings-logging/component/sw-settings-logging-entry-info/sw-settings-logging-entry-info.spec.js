/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

const defaultLogEntry = {
    context: {
        foo: 'bar',
    },
};

async function createWrapper({ featureActive = false, logEntry = defaultLogEntry } = {}) {
    return mount(await wrapTestComponent('sw-settings-logging-entry-info', { sync: true }), {
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
});
