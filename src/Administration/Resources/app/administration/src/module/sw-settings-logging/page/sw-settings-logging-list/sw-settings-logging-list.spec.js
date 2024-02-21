import { mount } from '@vue/test-utils';
import 'src/module/sw-settings/mixin/sw-settings-list.mixin';

const logEntryMock = {
    id: '018dc68776077179b6c51bdf18a4f25d',
    channel: 'business_events',
    message: 'mail.sent',
    level: 200,
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-settings-logging-list', { sync: true }), {
        global: {
            stubs: {
                'sw-settings-logging-mail-sent-info': await wrapTestComponent('sw-settings-logging-mail-sent-info'),
                'sw-page': {
                    template:
                        `<div class="sw-page">
                            <slot name="content"></slot>
                        </div>`,
                },
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
});
