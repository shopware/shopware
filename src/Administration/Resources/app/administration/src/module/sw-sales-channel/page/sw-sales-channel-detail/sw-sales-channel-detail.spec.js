/**
 * @package sales-channel
 */

import { createLocalVue, shallowMount } from '@vue/test-utils';
import swSalesChannelDetail from 'src/module/sw-sales-channel/page/sw-sales-channel-detail';

Shopware.Component.register('sw-sales-channel-detail', swSalesChannelDetail);

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(await Shopware.Component.build('sw-sales-channel-detail'), {
        localVue,
        stubs: {
            'sw-page': {
                template: `
<div class="sw-page">
    <slot name="smart-bar-actions"></slot>
</div>
                `,
            },
            'sw-button-process': true,
            'sw-language-switch': true,
            'sw-card-view': true,
            'sw-language-info': true,
            'sw-tabs': true,
            'sw-tabs-item': true,
            'router-view': true,
            'sw-skeleton': true,
        },
        provide: {
            acl: {
                can: (privilegeKey) => {
                    if (!privilegeKey) { return true; }

                    return privileges.includes(privilegeKey);
                },
            },
            repositoryFactory: {
                create: () => ({
                    create: () => ({}),
                    get: () => Promise.resolve({
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
    });
}

describe('src/module/sw-sales-channel/page/sw-sales-channel-detail', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
        wrapper.destroy();
    });

    it('should disable the save button when privilege does not exists', async () => {
        const wrapper = await createWrapper();
        const saveButton = wrapper.find('.sw-sales-channel-detail__save-action');

        await wrapper.setData({
            isLoading: false,
        });

        expect(saveButton.attributes().disabled).toBeTruthy();
        wrapper.destroy();
    });

    it('should enable the save button when privilege does exists', async () => {
        const wrapper = await createWrapper([
            'sales_channel.editor',
        ]);

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.find('.sw-sales-channel-detail__save-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
        wrapper.destroy();
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

        wrapper.destroy();
    });

    it('should not remove analytics association on save when analyticsId is not empty', async () => {
        const wrapper = await createWrapper([
            'sales_channel.editor',
        ]);

        await wrapper.setData({
            isLoading: false,
        });

        const analyticsId = wrapper.vm.updateAnalytics();

        expect(typeof analyticsId).toBe('string');
        expect(wrapper.vm.salesChannel.analyticsId).toBe('1a2b3c');
        expect(wrapper.vm.salesChannel.analytics.id).toEqual(wrapper.vm.salesChannel.analyticsId);

        wrapper.destroy();
    });

    it('should have currency criteria with sort', async () => {
        const wrapper = await createWrapper();

        const criteria = wrapper.vm.getLoadSalesChannelCriteria();

        expect(criteria.parse()).toEqual(expect.objectContaining({
            associations: expect.objectContaining({
                currencies: expect.objectContaining({
                    sort: expect.arrayContaining([
                        { field: 'name', order: 'ASC', naturalSorting: false },
                    ]),
                }),
            }),
        }));
    });
});
