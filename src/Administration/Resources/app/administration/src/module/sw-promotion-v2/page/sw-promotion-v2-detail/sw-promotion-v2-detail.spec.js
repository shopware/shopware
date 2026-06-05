/**
 * @sw-package checkout
 */
import { mount } from '@vue/test-utils';
import swPromotionV2Detail from './index';

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

function createTabs({ promotionId = 'promotionId', hasBaseError = false } = {}) {
    const routerPush = jest.fn(() => Promise.resolve());
    const tabs = swPromotionV2Detail.computed.tabs.call({
        promotionId,
        swPromotionV2DetailBaseError: hasBaseError,
        $route: {
            params: {
                id: promotionId,
            },
        },
        $router: {
            push: routerPush,
        },
        $t: (snippet) => snippet,
    });

    return {
        routerPush,
        tabs,
    };
}

async function createWrapper() {
    return mount(await wrapTestComponent('sw-promotion-v2-detail', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: '<div class="sw-page"><slot name="smart-bar-actions"></slot></div>',
                },
                'sw-search-bar': true,
                'sw-notification-center': true,
                'sw-language-switch': true,
                'sw-button-process': true,
                'sw-card-view': true,
                'sw-language-info': true,
                'mt-tabs': true,
                'sw-tabs': true,
                'sw-tabs-item': true,
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
    it('builds mt-tabs route items', () => {
        const { tabs } = createTabs({ hasBaseError: true });

        expect(tabs).toEqual([
            expect.objectContaining({
                label: 'sw-promotion-v2.detail.tabs.tabGeneral',
                name: 'sw.promotion.v2.detail.base',
                hasError: true,
                disabled: false,
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-promotion-v2.detail.tabs.tabConditions',
                name: 'sw.promotion.v2.detail.conditions',
                disabled: false,
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-promotion-v2.detail.tabs.tabDiscounts',
                name: 'sw.promotion.v2.detail.discounts',
                disabled: false,
                onClick: expect.any(Function),
            }),
        ]);
    });

    it('pushes the matching promotion route when a tab is clicked', () => {
        const { routerPush, tabs } = createTabs();

        tabs[0].onClick();
        tabs[1].onClick();
        tabs[2].onClick();

        expect(routerPush).toHaveBeenNthCalledWith(1, {
            name: 'sw.promotion.v2.detail.base',
            params: { id: 'promotionId' },
        });
        expect(routerPush).toHaveBeenNthCalledWith(2, {
            name: 'sw.promotion.v2.detail.conditions',
            params: { id: 'promotionId' },
        });
        expect(routerPush).toHaveBeenNthCalledWith(3, {
            name: 'sw.promotion.v2.detail.discounts',
            params: { id: 'promotionId' },
        });
    });

    it('does not push a route for disabled promotion tabs', () => {
        const { routerPush, tabs } = createTabs({ promotionId: null });

        tabs[0].onClick();
        tabs[1].onClick();
        tabs[2].onClick();

        expect(tabs).toEqual([
            expect.objectContaining({ disabled: true }),
            expect.objectContaining({ disabled: true }),
            expect.objectContaining({ disabled: true }),
        ]);
        expect(routerPush).not.toHaveBeenCalled();
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
