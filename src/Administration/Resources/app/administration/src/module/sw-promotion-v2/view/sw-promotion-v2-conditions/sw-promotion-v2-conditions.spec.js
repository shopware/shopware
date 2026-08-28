/**
 * @sw-package checkout
 */
import { mount } from '@vue/test-utils';

const { Criteria, EntityCollection } = Shopware.Data;

function createPromotion(overrides = {}) {
    return {
        name: 'Test Promotion',
        active: true,
        validFrom: '2020-07-28T12:00:00.000+00:00',
        validUntil: '2020-08-11T12:00:00.000+00:00',
        maxRedemptionsGlobal: 45,
        maxRedemptionsPerCustomer: 12,
        exclusive: false,
        code: null,
        useCodes: true,
        useIndividualCodes: false,
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
        isNew() {
            return true;
        },
        ...overrides,
    };
}

function createPromotionCollection(promotions = []) {
    return new EntityCollection(
        '/promotion',
        'promotion',
        Shopware.Context.api,
        new Criteria(1, 25),
        promotions,
        promotions.length,
    );
}

function defaultRepositorySearch() {
    return Promise.resolve(createPromotionCollection([{ id: 'promotionId1' }]));
}

async function createWrapper({ promotion = {}, repositorySearch } = {}) {
    return mount(await wrapTestComponent('sw-promotion-v2-conditions', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template: '<div class="mt-card"><slot></slot></div>',
                },
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-text-field': {
                    template: '<input type="text" class="sw-field sw-text-field"></input>',
                    props: [
                        'value',
                        'disabled',
                    ],
                },
                'mt-number-field': {
                    template: '<input type="number" class="sw-field mt-number-field"></input>',
                    props: [
                        'value',
                        'disabled',
                    ],
                },
                'sw-entity-multi-select': {
                    template: '<input type="select" multiple="true" class="sw-field sw-entity-multi-select"></input>',
                    props: [
                        'value',
                        'disabled',
                    ],
                },
                'sw-promotion-v2-sales-channel-select': {
                    template: '<input type="select" class="sw-field sw-promotion-v2-sales-channel-select"></input>',
                    props: [
                        'value',
                        'disabled',
                    ],
                },
                'sw-select-rule-create': {
                    template: '<input type="select" class="sw-field sw-select-rule-create"></input>',
                    props: [
                        'value',
                        'disabled',
                    ],
                },
                'sw-promotion-v2-cart-condition-form': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: repositorySearch ?? defaultRepositorySearch,
                    }),
                },
                ruleConditionDataProviderService: {
                    getAwarenessConfigurationByAssignmentName: () => {
                        return {
                            snippet: '',
                        };
                    },
                },
            },
        },
        props: {
            promotion: createPromotion(promotion),
        },
    });
}

describe('src/module/sw-promotion-v2/component/sw-promotion-v2-conditions', () => {
    it('should disable adding discounts when privileges not set', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();

        wrapper.findAllComponents('.sw-field').forEach((field) => {
            expect(field.props('disabled')).toBe(true);
        });
    });

    it('should enable adding discounts when privilege is set', async () => {
        global.activeAclRoles = ['promotion.editor'];

        const wrapper = await createWrapper();

        wrapper.findAllComponents('.sw-field').forEach((field) => {
            expect(field.props('disabled')).toBeFalsy();
        });
    });

    it('should load all selected promotion exclusions', async () => {
        const selectedPromotions = Array.from({ length: 26 }, (_, index) => {
            return {
                id: `promotion-id-${index}`,
                name: `Promotion ${index}`,
            };
        });
        const repositorySearch = jest.fn((criteria) => {
            return Promise.resolve(createPromotionCollection(selectedPromotions.slice(0, criteria.limit)));
        });
        const wrapper = await createWrapper({
            promotion: {
                exclusionIds: [],
            },
            repositorySearch,
        });

        wrapper.vm.onChangeExclusions(createPromotionCollection(selectedPromotions));

        await flushPromises();

        expect(wrapper.vm.promotion.exclusionIds).toHaveLength(26);
        expect(repositorySearch).toHaveBeenCalledTimes(1);
        expect(repositorySearch.mock.calls[0][0].limit).toBe(26);
        expect(wrapper.vm.excludedPromotions).toHaveLength(26);
    });
});
