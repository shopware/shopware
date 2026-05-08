/**
 * @sw-package checkout
 */
import { mount } from '@vue/test-utils';

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
        name: 'Test Promotion',
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
            id: 'promotionSalesChannelId',
        },
    ],
    discounts: [],
    individualCodes: [],
    personaRules: [],
    personaCustomers: [],
    orderRules: [],
    cartRules: [],
    translations: [],
    hasOrders: false,
};

async function createWrapper(routeName = 'sw.promotion.v2.detail.base') {
    return mount(await wrapTestComponent('sw-promotion-v2-detail', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: '<div class="sw-page"><slot name="smart-bar-actions"></slot><slot name="content"></slot></div>',
                },
                'sw-search-bar': true,
                'sw-notification-center': true,
                'sw-language-switch': true,
                'sw-button-process': true,
                'sw-card-view': {
                    template: '<div><slot></slot></div>',
                },
                'sw-language-info': true,
                'sw-tabs': true,
                'sw-tabs-item': true,
                'mt-tabs': {
                    props: [
                        'items',
                        'defaultItem',
                        'positionIdentifier',
                    ],
                    template: '<mt-tabs-stub :items="items" :default-item="defaultItem" :position-identifier="positionIdentifier" />',
                },
                'router-view': true,
                'sw-skeleton': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve([promotionData]),
                        get: () => Promise.resolve([promotionData]),
                        create: () => {},
                    }),
                },
            },
            mocks: {
                $route: {
                    name: routeName,
                    params: {
                        id: 'promotionId',
                    },
                },
                $router: {
                    push: jest.fn(),
                },
                placeholder: (entity, property, fallback = '') => entity?.[property] ?? fallback,
                $device: {
                    getSystemKey: () => 'strg',
                },
            },
        },
        props: {
            promotionId: 'id1',
        },
    });
}

describe('src/module/sw-promotion-v2/page/sw-promotion-v2-detail', () => {
    beforeEach(() => {
        global.activeFeatureFlags = [''];
    });

    it('should render legacy tabs when the major feature flag is inactive', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('sw-tabs-stub').exists()).toBe(true);
        expect(wrapper.find('mt-tabs-stub').exists()).toBe(false);
    });

    it('should render meteor route tabs when the major feature flag is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        const wrapper = await createWrapper('sw.promotion.v2.detail.discounts');

        const mtTabs = wrapper.getComponent('mt-tabs-stub');

        expect(mtTabs.props('items')).toStrictEqual([
            {
                label: 'sw-promotion-v2.detail.tabs.tabGeneral',
                name: 'general',
                hasError: wrapper.vm.swPromotionV2DetailBaseError,
                disabled: false,
                onClick: expect.any(Function),
            },
            {
                label: 'sw-promotion-v2.detail.tabs.tabConditions',
                name: 'conditions',
                disabled: false,
                onClick: expect.any(Function),
            },
            {
                label: 'sw-promotion-v2.detail.tabs.tabDiscounts',
                name: 'discounts',
                disabled: false,
                onClick: expect.any(Function),
            },
        ]);
        expect(mtTabs.props('defaultItem')).toBe('discounts');
        expect(mtTabs.props('positionIdentifier')).toBe('sw-promotion-detail');
        expect(wrapper.find('sw-tabs-stub').exists()).toBe(false);

        mtTabs.props('items')[0].onClick();
        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.promotion.v2.detail.base',
            params: { id: 'promotionId' },
        });

        mtTabs.props('items')[1].onClick();
        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.promotion.v2.detail.conditions',
            params: { id: 'promotionId' },
        });

        mtTabs.props('items')[2].onClick();
        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.promotion.v2.detail.discounts',
            params: { id: 'promotionId' },
        });
    });

    it('should disable the save button when privilege does not exist', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.find('.sw-promotion-v2-detail__save-action');
        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should enable the save button when privilege does not exist', async () => {
        global.activeAclRoles = ['promotion.editor'];

        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.find('.sw-promotion-v2-detail__save-action');
        expect(saveButton.attributes().disabled).toBeFalsy();
    });
});
