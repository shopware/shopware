import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-promotion/page/sw-promotion-detail';
import promotionState from 'src/module/sw-promotion/page/sw-promotion-detail/state';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    const promotionData = {
        name: 'Test Promotion',
        active: true,
        validFrom: '2020-07-28T12:00:00.000+00:00',
        validUntil: '2020-08-11T12:00:00.000+00:00',
        maxRedemptionsGlobal: 45,
        maxRedemptionsPerCustomer: 12,
        exclusive: false,
        code: null,
        useCodes: true,
        useIndividualCodes: true,
        individualCodePattern: 'code-%d',
        useSetGroups: false,
        customerRestriction: true,
        orderCount: 0,
        ordersPerCustomerCount: null,
        exclusionIds: ['d671d6d3efc74d2a8b977e3be3cd69c7'],
        translated: {
            name: 'Test Promotion'
        },
        apiAlias: null,
        id: 'promotionId',
        setgroups: [],
        salesChannels: [
            {
                promotionId: 'promotionId',
                salesChannelId: 'salesChannelId',
                priority: 1,
                createdAt: '2020-08-17T13:24:52.692+00:00',
                id: 'promotionSalesChannelId'
            }
        ],
        discounts: [],
        individualCodes: [],
        personaRules: [],
        personaCustomers: [],
        orderRules: [],
        cartRules: [],
        translations: [],
        hasOrders: false
    };

    return shallowMount(Shopware.Component.build('sw-promotion-detail'), {
        localVue,
        stubs: {
            'sw-page': {
                template: '<div class="sw-page"><slot name="smart-bar-actions"></slot></div>'
            },
            'sw-search-bar': true,
            'sw-notification-center': true,
            'sw-language-switch': true,
            'sw-button': true,
            'sw-button-process': true,
            'sw-card-view': true,
            'sw-language-info': true,
            'sw-tabs': true,
            'sw-tabs-item': true,
            'router-view': true
        },
        provide: {
            acl: {
                can: (key) => {
                    if (!key) { return true; }

                    return privileges.includes(key);
                }
            },
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve([promotionData]),
                    get: () => Promise.resolve([promotionData]),
                    create: () => {}
                })
            }
        },
        mocks: {
            $tc: v => v,
            $device: {
                getSystemKey: () => 'strg'
            }
        },
        propsData: {
            promotionId: 'id1'
        }
    });
}

describe('src/module/sw-promotion/page/sw-promotion-detail', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swPromotionDetail', promotionState);
    });

    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable the save button when privilege does not exist', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoading: false
        });

        const saveButton = wrapper.find('.sw-promotion-detail__save-action');
        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should enable the save button when privilege does not exist', async () => {
        const wrapper = createWrapper([
            'promotion.editor'
        ]);

        await wrapper.setData({
            isLoading: false
        });

        const saveButton = wrapper.find('.sw-promotion-detail__save-action');
        expect(saveButton.attributes().disabled).toBeFalsy();
    });
});
