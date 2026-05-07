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
                'mt-tabs': {
                    props: ['items', 'defaultItem'],
                    template: '<mt-tabs-stub :items="items" :default-item="defaultItem" />',
                },
                'sw-tabs-deprecated': {
                    template: '<div><slot /></div>',
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

async function createEntryInfoWrapper() {
    return mount(await wrapTestComponent('sw-settings-logging-entry-info', { sync: true }), {
        props: {
            logEntry: {
                ...logEntryMock,
                message: 'test'.repeat(10),
            },
        },
        global: {
            stubs: {
                'sw-modal': {
                    template: '<div><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'mt-tabs': {
                    props: ['items', 'defaultItem'],
                    template: '<mt-tabs-stub :items="items" :default-item="defaultItem" />',
                },
                'sw-tabs': await wrapTestComponent('sw-tabs', {
                    sync: true,
                }),
                'sw-tabs-item': true,
                'mt-textarea': true,
            },
        },
    });
}

describe('src/module/sw-settings-logging/page/sw-settings-logging-list', () => {
    beforeEach(() => {
        global.activeFeatureFlags = [''];
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

    it('should render mt-tabs with mail log tab items when major tabs migration is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            displayedLog: {
                ...logEntryMock,
                message: 'mail.sent',
                context: {
                    additionalData: {
                        recipients: [],
                        contents: {
                            'text/html': '<p>HTML</p>',
                            'text/plain': 'Plain',
                        },
                    },
                },
            },
        });
        await flushPromises();

        const mtTabs = wrapper.getComponent('mt-tabs-stub');

        expect(mtTabs.props('items')).toStrictEqual([
            {
                label: 'sw-settings-logging.mailInfo.tabHTML',
                name: 'html',
                onClick: expect.any(Function),
            },
            {
                label: 'sw-settings-logging.mailInfo.tabPlain',
                name: 'plain',
                onClick: expect.any(Function),
            },
            {
                label: 'sw-settings-logging.entryInfo.tabRaw',
                name: 'raw',
                onClick: expect.any(Function),
            },
        ]);
        expect(mtTabs.props('defaultItem')).toBe('html');
        expect(wrapper.find('.sw-settings-logging-mail-sent-info__tab-item').exists()).toBe(false);
    });

    it('should render mt-tabs with default log entry tab items when major tabs migration is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createEntryInfoWrapper();
        await flushPromises();

        const mtTabs = wrapper.getComponent('mt-tabs-stub');

        expect(mtTabs.props('items')).toStrictEqual([
            {
                label: 'sw-settings-logging.entryInfo.tabRaw',
                name: 'raw',
                onClick: expect.any(Function),
            },
        ]);
        expect(mtTabs.props('defaultItem')).toBe('raw');
        expect(wrapper.find('sw-tabs-stub').exists()).toBe(false);
    });
});
