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
                'text/html': '<p>Order confirmation</p>',
                'text/plain': 'Order confirmation',
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
                    template: '<div><slot /><slot name="content" /></div>',
                },
                'sw-extension-component-section': await wrapTestComponent('sw-extension-component-section', { sync: true }),
                'sw-textarea-field': true,
                'sw-button': true,
                'sw-icon': true,
            },
            provide: {
                searchRankingService: {},
            },
        },
    });
}

describe('src/module/sw-settings-logging/page/sw-settings-logging-list', () => {
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

    it('should show metadata without body tabs for sensitive mail', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            displayedLog: {
                ...logEntryMock,
                context: {
                    additionalData: {
                        subject: 'Password recovery',
                        recipients: { 'customer@example.com': 'Customer' },
                    },
                },
            },
        });
        await flushPromises();

        expect(wrapper.find('.sw-settings-logging-list__custom-content').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-logging-mail-sent-info__tab-item').exists()).toBe(false);
        expect(wrapper.html()).toContain('Password recovery');
    });
});
