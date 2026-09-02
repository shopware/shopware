/**
 * @sw-package checkout
 */
import { nextTick } from 'vue';
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

async function createWrapper({
    featureActive = false,
    promotionId = 'id1',
    routeName = 'sw.promotion.v2.detail.base',
    routerPush = jest.fn(),
} = {}) {
    return mount(await wrapTestComponent('sw-promotion-v2-detail', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
                        <div class="sw-page">
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-search-bar': true,
                'sw-notification-center': true,
                'sw-language-switch': true,
                'sw-button-process': true,
                'sw-card-view': {
                    template: '<div class="sw-card-view"><slot></slot></div>',
                },
                'sw-language-info': true,
                'sw-tabs': {
                    name: 'sw-tabs',
                    template: '<div class="sw-tabs"><slot></slot></div>',
                    props: [
                        'positionIdentifier',
                    ],
                },
                'sw-tabs-item': {
                    name: 'sw-tabs-item',
                    template: '<div class="sw-tabs-item"></div>',
                    props: [
                        'disabled',
                        'hasError',
                        'route',
                        'title',
                    ],
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    template: '<div class="mt-tabs"></div>',
                    props: {
                        defaultItem: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                        items: {
                            type: Array,
                            required: true,
                        },
                        positionIdentifier: {
                            type: String,
                            required: true,
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
                        create: () => ({ id: 'newPromotionId' }),
                    }),
                },
                feature: {
                    isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
                },
            },
            mocks: {
                $route: {
                    name: routeName,
                    params: {
                        id: 'promotion123',
                    },
                },
                $router: {
                    push: routerPush,
                },
                $device: {
                    getSystemKey: () => 'strg',
                },
            },
        },
        props: {
            promotionId,
        },
    });
}

describe('src/module/sw-promotion-v2/page/sw-promotion-v2-detail', () => {
    afterEach(() => {
        Shopware.Store.get('shopwareApps').selectedIds = [];
    });

    it('should select the displayed promotion for app action buttons', async () => {
        await createWrapper({ promotionId: 'promotionId' });
        await flushPromises();

        expect(Shopware.Store.get('shopwareApps').selectedIds).toEqual([
            'promotionId',
        ]);
    });

    it('should select the new promotion for app action buttons when navigating to another promotion', async () => {
        const wrapper = await createWrapper({ promotionId: 'promotionId' });
        await flushPromises();

        await wrapper.setProps({ promotionId: 'otherPromotionId' });
        await flushPromises();

        expect(Shopware.Store.get('shopwareApps').selectedIds).toEqual([
            'otherPromotionId',
        ]);
    });

    it('should deselect the promotion for app action buttons when creating a new promotion', async () => {
        const wrapper = await createWrapper({ promotionId: 'promotionId' });
        await flushPromises();

        await wrapper.setProps({ promotionId: null });
        await flushPromises();

        expect(Shopware.Store.get('shopwareApps').selectedIds).toEqual([]);
    });

    it('should deselect the promotion for app action buttons when leaving the detail page', async () => {
        const wrapper = await createWrapper({ promotionId: 'promotionId' });
        await flushPromises();

        wrapper.vm.$options.beforeRouteLeave.call(wrapper.vm);

        expect(Shopware.Store.get('shopwareApps').selectedIds).toEqual([]);
    });

    it('should disable the save button when privilege does not exist', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();

        wrapper.vm.isLoading = false;
        await nextTick();

        const saveButton = wrapper.find('.sw-promotion-v2-detail__save-action');
        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should enable the save button when privilege does not exist', async () => {
        global.activeAclRoles = ['promotion.editor'];

        const wrapper = await createWrapper();

        wrapper.vm.isLoading = false;
        await nextTick();

        const saveButton = wrapper.find('.sw-promotion-v2-detail__save-action');
        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should render the fallback tabs branch while the major feature flag is inactive', async () => {
        const wrapper = await createWrapper();

        const tabs = wrapper.getComponent({ name: 'sw-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-promotion-detail');
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        const wrapper = await createWrapper({
            featureActive: true,
            routeName: 'sw.promotion.v2.detail.conditions',
        });

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-promotion-detail');
        expect(tabs.props('defaultItem')).toBe('sw.promotion.v2.detail.conditions');
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-promotion-v2.detail.tabs.tabGeneral',
                name: 'sw.promotion.v2.detail.base',
                disabled: undefined,
                hasError: false,
                onClick: expect.any(Function),
            },
            {
                label: 'sw-promotion-v2.detail.tabs.tabConditions',
                name: 'sw.promotion.v2.detail.conditions',
                disabled: undefined,
                onClick: expect.any(Function),
            },
            {
                label: 'sw-promotion-v2.detail.tabs.tabDiscounts',
                name: 'sw.promotion.v2.detail.discounts',
                disabled: undefined,
                onClick: expect.any(Function),
            },
        ]);
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
    });

    it('should navigate when a meteor route tab is clicked', async () => {
        const routerPush = jest.fn();
        const wrapper = await createWrapper({
            featureActive: true,
            routerPush,
        });

        const discountsTab = wrapper.vm.promotionDetailTabs.find((tab) => {
            return tab.name === 'sw.promotion.v2.detail.discounts';
        });

        discountsTab.onClick();

        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.promotion.v2.detail.discounts',
            params: { id: 'promotion123' },
        });
    });
});
