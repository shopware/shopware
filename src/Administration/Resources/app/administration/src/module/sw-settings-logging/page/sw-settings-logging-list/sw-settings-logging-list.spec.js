/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import 'src/module/sw-settings/mixin/sw-settings-list.mixin';

const logEntryMock = {
    id: '018dc68776077179b6c51bdf18a4f25d',
    channel: 'business_events',
    message: 'mail.sent',
    level: 200,
    context: {
        additionalData: {
            recipients: [],
            contents: {
                'text/html': '<p>HTML</p>',
                'text/plain': 'Plain',
            },
        },
    },
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-settings-logging-list', { sync: true }), {
        global: {
            stubs: {
                'sw-settings-logging-mail-sent-info': await wrapTestComponent('sw-settings-logging-mail-sent-info'),
                'sw-page': {
                    template: `<div class="sw-page">
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-search-bar': true,
                'sw-pagination': true,
                'sw-context-menu-item': true,
                'sw-entity-listing': true,
                'sw-sidebar-item': true,
                'sw-sidebar': true,
                'sw-tabs-item': true,
                'sw-tabs': await wrapTestComponent('sw-tabs', {
                    sync: true,
                }),
                'sw-tabs-deprecated': {
                    template: '<div><slot /></div>',
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    template: '<div class="mt-tabs-stub"></div>',
                    props: {
                        items: {
                            type: Array,
                            required: true,
                        },
                        positionIdentifier: {
                            type: String,
                            required: false,
                            default: null,
                        },
                        defaultItem: {
                            type: String,
                            required: false,
                            default: null,
                        },
                    },
                    emits: [
                        'new-item-active',
                        'extension-item-active',
                    ],
                },
                'sw-extension-component-section': await wrapTestComponent('sw-extension-component-section', { sync: true }),
                'sw-textarea-field': true,
                'sw-time-ago': true,
            },
            provide: {
                searchRankingService: {
                    isValidTerm: (term) => {
                        return term && term.trim().length >= 1;
                    },
                },
            },
        },
    });
}

describe('src/module/sw-settings-logging/page/sw-settings-logging-list', () => {
    beforeEach(() => {
        global.activeFeatureFlags = [];
    });

    it('should load default modal component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            displayedLog: {
                ...logEntryMock,
                message: 'test'.repeat(10),
            },
        });

        expect(wrapper.find('.sw-settings-logging-list__custom-content').exists()).toBe(true);
        expect(wrapper.find('sw-settings-logging-entry-info').exists()).toBe(true);
    });

    it('should load dynamic modal component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            displayedLog: {
                ...logEntryMock,
                message: 'mail.sent',
            },
        });
        await flushPromises();

        expect(wrapper.find('.sw-settings-logging-list__custom-content').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-logging-mail-sent-info__tab-item').exists()).toBe(true);
    });

    it('should render mail sent info with Meteor tabs', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            displayedLog: {
                ...logEntryMock,
                message: 'mail.sent',
            },
        });
        await flushPromises();

        const tabs = wrapper.getComponent('.mt-tabs-stub');

        expect(tabs.props('positionIdentifier')).toBe('sw-settings-logging-entry-info');
        expect(tabs.props('defaultItem')).toBe('html');
        expect(tabs.props('items').map((item) => item.name)).toEqual([
            'html',
            'plain',
            'raw',
        ]);

        await tabs.vm.$emit('new-item-active', 'plain');

        expect(wrapper.find('.sw-settings-logging-mail-sent-info__mail-content').text()).toBe('Plain');

        await tabs.vm.$emit('new-item-active', 'raw');

        expect(wrapper.findComponent({ name: 'mt-textarea' }).exists()).toBe(true);
    });
});
