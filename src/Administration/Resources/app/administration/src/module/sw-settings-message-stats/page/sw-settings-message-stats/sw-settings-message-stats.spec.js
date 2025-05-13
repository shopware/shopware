/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-settings-message-stats/page/sw-settings-message-stats';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-settings-message-stats', {
        sync: true,
    }), {
        global: {
            provide: {
                messageStatsService: {
                    getStats: jest.fn(),
                },
            },
            stubs: {
                'sw-page': {
                    template: `<div class="sw-page">
                            <slot name="content"></slot>
                        </div>`,
                },
                'mt-card': true,
                'mt-data-table': true,
                'sw-skeleton': true,
                'sw-empty-state': true,
                'sw-help-text': true,
                'sw-data-grid': true,
                'sw-card-view': true,
                'sw-card': true,
                'sw-container': true,
                'sw-button': true,
                'sw-icon': true,
            },
            mocks: {
                $tc: (key) => key,
            },
        },
    });
}

describe('module/sw-settings-message-stats/page/sw-settings-message-stats', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.unmount();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should load stats on component creation', async () => {
        const mockStats = {
            totalMessagesProcessed: 100,
            processedSince: '2024-03-19T12:00:00.000Z',
            messageTypes: [
                { type: 'ProductIndexing', count: 50 },
                { type: 'ThemeCompilation', count: 1 },
            ],
        };

        wrapper.vm.messageStatsService.getStats.mockResolvedValueOnce(mockStats);

        await wrapper.vm.loadStats();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.stats).toEqual(mockStats);
        expect(wrapper.vm.hasStats).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
    });
});
