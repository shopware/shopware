/**
 * @package buyers-experience
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-sales-channel-detail', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
    <div class="sw-page">
        <slot name="smart-bar-actions"></slot>
    </div>
                    `,
                },
                'sw-button-process': {
                    template: '<button class="sw-button-process"></button>',
                    props: ['disabled'],
                },
                'sw-language-switch': true,
                'sw-card-view': true,
                'sw-language-info': true,
                'sw-tabs': true,
                'sw-tabs-item': true,
                'router-view': true,
                'sw-skeleton': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => ({}),
                        get: () =>
                            Promise.resolve({
                                id: '1a2b3c4d',
                                analyticsId: '1a2b3c',
                                analytics: {
                                    id: '1a2b3c',
                                    trackingId: 'tracking-id',
                                },
                                productExports: {
                                    first: () => ({}),
                                },
                            }),
                        search: () => Promise.resolve([]),
                        delete: () => Promise.resolve(),
                        save: () => Promise.resolve(),
                    }),
                },
                exportTemplateService: {
                    getProductExportTemplateRegistry: () => ({}),
                },
            },
            mocks: {
                $route: {
                    params: {
                        id: '1a2b3c4d',
                    },
                    name: '',
                },
            },
        },
    });
}

describe('src/module/sw-sales-channel/page/sw-sales-channel-detail', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should disable the save button when privilege does not exists', async () => {
        const wrapper = await createWrapper();
        const saveButton = wrapper.getComponent('.sw-sales-channel-detail__save-action');

        await wrapper.setData({
            isLoading: false,
        });

        expect(saveButton.props('disabled')).toBe(true);
    });

    it('should enable the save button when privilege does exists', async () => {
        global.activeAclRoles = ['sales_channel.editor'];
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.getComponent('.sw-sales-channel-detail__save-action');

        expect(saveButton.props('disabled')).toBe(false);
    });

    it('should remove analytics association on save when analyticsId is empty', async () => {
        const wrapper = await createWrapper([
            'sales_channel.editor',
        ]);

        await wrapper.setData({
            isLoading: false,
        });

        wrapper.vm.salesChannel.analytics.trackingId = null;

        const analyticsId = wrapper.vm.updateAnalytics();

        expect(typeof analyticsId).toBe('string');
        expect(wrapper.vm.salesChannel.analyticsId).toBeNull();
        expect(wrapper.vm.salesChannel.analytics).toBeUndefined();
    });

    it('should not remove analytics association on save when analyticsId is not empty', async () => {
        global.activeAclRoles = ['sales_channel.editor'];
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoading: false,
        });

        const analyticsId = wrapper.vm.updateAnalytics();

        expect(typeof analyticsId).toBe('string');
        expect(wrapper.vm.salesChannel.analyticsId).toBe('1a2b3c');
        expect(wrapper.vm.salesChannel.analytics.id).toEqual(wrapper.vm.salesChannel.analyticsId);
    });

    it('should have currency criteria with sort', async () => {
        const wrapper = await createWrapper();

        const criteria = wrapper.vm.getLoadSalesChannelCriteria();

        expect(criteria.parse()).toEqual(
            expect.objectContaining({
                associations: expect.objectContaining({
                    currencies: expect.objectContaining({
                        sort: expect.arrayContaining([
                            {
                                field: 'name',
                                order: 'ASC',
                                naturalSorting: false,
                            },
                        ]),
                    }),
                }),
            }),
        );
    });
});
