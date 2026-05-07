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

async function createWrapper({ route, routerPush } = {}) {
    return mount(await wrapTestComponent('sw-promotion-v2-detail', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template:
                        '<div class="sw-page"><slot name="smart-bar-actions"></slot><slot name="content"></slot></div>',
                },
                'sw-search-bar': true,
                'sw-notification-center': true,
                'sw-language-switch': true,
                'sw-button-process': true,
                'sw-card-view': {
                    template: '<div class="sw-card-view"><slot></slot></div>',
                },
                'sw-language-info': true,
                'sw-tabs': true,
                'sw-tabs-item': true,
                'mt-tabs': {
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
                        routeTabs: {
                            type: Boolean,
                            required: false,
                            default: false,
                        },
                    },
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
                placeholder: (entity, property, fallback = '') => entity?.[property] ?? fallback,
                $route: route ?? {
                    name: 'sw.promotion.v2.detail.base',
                    params: { id: 'id1' },
                },
                $router: {
                    push: routerPush ?? jest.fn(),
                },
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
        global.activeFeatureFlags = [];
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

    it('should render route-backed Meteor tabs when the feature is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const routerPush = jest.fn();
        const wrapper = await createWrapper({ routerPush });

        const tabs = wrapper.getComponent('.mt-tabs-stub');
        const items = tabs.props('items');

        expect(tabs.props('positionIdentifier')).toBe('sw-promotion-detail');
        expect(tabs.props('defaultItem')).toBe('sw.promotion.v2.detail.base');
        expect(tabs.props('routeTabs')).toBe(true);
        expect(items).toEqual([
            expect.objectContaining({
                label: 'sw-promotion-v2.detail.tabs.tabGeneral',
                name: 'sw.promotion.v2.detail.base',
                disabled: false,
            }),
            expect.objectContaining({
                label: 'sw-promotion-v2.detail.tabs.tabConditions',
                name: 'sw.promotion.v2.detail.conditions',
                disabled: false,
            }),
            expect.objectContaining({
                label: 'sw-promotion-v2.detail.tabs.tabDiscounts',
                name: 'sw.promotion.v2.detail.discounts',
                disabled: false,
            }),
        ]);

        items[1].onClick();

        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.promotion.v2.detail.conditions',
            params: { id: 'id1' },
        });
    });
});
