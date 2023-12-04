/**
 * @package buyers-experience
 */

import { shallowMount, createLocalVue } from '@vue/test-utils_v2';
import swSalesChannelDetail from 'src/module/sw-sales-channel/page/sw-sales-channel-detail';
import swSalesChannelCreate from 'src/module/sw-sales-channel/page/sw-sales-channel-create';

Shopware.Component.register('sw-sales-channel-detail', swSalesChannelDetail);
Shopware.Component.extend('sw-sales-channel-create', 'sw-sales-channel-detail', swSalesChannelCreate);

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(await Shopware.Component.build('sw-sales-channel-create'), {
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
                        productExports: {
                            first: () => ({}),
                        },
                    }),
                    search: () => Promise.resolve([]),
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

describe('src/module/sw-sales-channel/page/sw-sales-channel-create', () => {
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
            'sales_channel.creator',
        ]);

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.find('.sw-sales-channel-detail__save-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
        wrapper.destroy();
    });
});
