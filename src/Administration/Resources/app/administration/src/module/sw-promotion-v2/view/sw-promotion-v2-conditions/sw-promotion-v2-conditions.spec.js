/**
 * @sw-package checkout
 */
import { mount } from '@vue/test-utils';
import { deprecations } from 'test/_helper_/deprecations';

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
                    props: ['value', 'disabled'],
                },
                'mt-number-field': {
                    template: '<input type="number" class="sw-field mt-number-field"></input>',
                    props: ['value', 'disabled'],
                },
                'sw-entity-multi-select': {
                    template: '<input type="select" multiple="true" class="sw-field sw-entity-multi-select"></input>',
                    props: ['value', 'disabled', 'entityCollection'],
                },
                'sw-promotion-v2-sales-channel-select': {
                    template: '<input type="select" class="sw-field sw-promotion-v2-sales-channel-select"></input>',
                    props: ['value', 'disabled'],
                },
                'sw-select-rule-create': {
                    template: '<input type="select" class="sw-field sw-select-rule-create"></input>',
                    props: ['value', 'disabled', 'ruleFilter'],
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

    it('should load the exclusions of the new promotion when the promotion is replaced while the view stays mounted', async () => {
        const repositorySearch = jest.fn((criteria) => {
            const ids = criteria.filters.find((filter) => filter.field === 'id').value.split('|');

            return Promise.resolve(createPromotionCollection(ids.map((id) => ({ id }))));
        });
        const wrapper = await createWrapper({ repositorySearch });
        await flushPromises();

        const exclusionSelection = () => wrapper.findComponent('.sw-promotion-v2-conditions__rules-exclusion-selection');

        expect(repositorySearch).toHaveBeenCalledTimes(1);
        expect(Array.from(exclusionSelection().props('entityCollection').getIds())).toEqual([
            'd671d6d3efc74d2a8b977e3be3cd69c7',
        ]);

        await wrapper.setProps({
            promotion: createPromotion({
                id: 'secondPromotionId',
                exclusionIds: ['8b3f1c2d9e4a4f6b8c7d5e3a1b2c4d6e'],
            }),
        });
        await flushPromises();

        expect(repositorySearch).toHaveBeenCalledTimes(2);
        expect(repositorySearch.mock.calls[1][0].filters).toEqual([
            expect.objectContaining({ field: 'id', value: '8b3f1c2d9e4a4f6b8c7d5e3a1b2c4d6e' }),
        ]);
        expect(Array.from(exclusionSelection().props('entityCollection').getIds())).toEqual([
            '8b3f1c2d9e4a4f6b8c7d5e3a1b2c4d6e',
        ]);
    });

    describe('rule filter of the customer rule select', () => {
        afterEach(() => {
            Shopware.Component.getOverrideRegistry().delete('sw-promotion-v2-conditions');
        });

        // @deprecated tag:v6.8.0 - The test will be removed with the personaRuleFilter computed.
        it.deprecated('v6.8.0.0')(
            'passes personaRuleFilter to the rule select without reporting its deprecation',
            async () => {
                const wrapper = await createWrapper();

                expect(
                    wrapper
                        .getComponent('.sw-promotion-v2-conditions__rule-select-customer')
                        .props('ruleFilter')
                        .hasAssociation('conditions'),
                ).toBe(true);
                expect(deprecations).not.toHaveTriggered('sw-promotion-v2-conditions.personaRuleFilter is deprecated');
            },
        );

        // @deprecated tag:v6.8.0 - The test will be removed with the personaRuleFilter computed.
        it.deprecated('v6.8.0.0')('passes an override of personaRuleFilter to the rule select', async () => {
            const overriddenCriteria = new Shopware.Data.Criteria(1, 5);
            Shopware.Component.override('sw-promotion-v2-conditions', {
                computed: {
                    personaRuleFilter() {
                        return overriddenCriteria;
                    },
                },
            });

            const wrapper = await createWrapper();

            expect(wrapper.getComponent('.sw-promotion-v2-conditions__rule-select-customer').props('ruleFilter')).toBe(
                overriddenCriteria,
            );
        });

        it.activeFeatureFlags(['v6.8.0.0'])('leaves the rule filter to the default of the rule select', async () => {
            const wrapper = await createWrapper();

            expect(
                wrapper.getComponent('.sw-promotion-v2-conditions__rule-select-customer').props('ruleFilter'),
            ).toBeUndefined();
        });
    });

    describe('rule filter of the order rule select', () => {
        afterEach(() => {
            Shopware.Component.getOverrideRegistry().delete('sw-promotion-v2-conditions');
        });

        // @deprecated tag:v6.8.0 - The test will be removed with the orderConditionsFilter computed.
        it.deprecated('v6.8.0.0')(
            'passes orderConditionsFilter to the rule select without reporting its deprecation',
            async () => {
                const wrapper = await createWrapper();

                expect(
                    wrapper
                        .getComponent('.sw-promotion-v2-conditions__rule-select-order-conditions')
                        .props('ruleFilter')
                        .hasAssociation('conditions'),
                ).toBe(true);
                expect(deprecations).not.toHaveTriggered('sw-promotion-v2-conditions.orderConditionsFilter is deprecated');
            },
        );

        // @deprecated tag:v6.8.0 - The test will be removed with the orderConditionsFilter computed.
        it.deprecated('v6.8.0.0')('passes an override of orderConditionsFilter to the rule select', async () => {
            const overriddenCriteria = new Shopware.Data.Criteria(1, 5);
            Shopware.Component.override('sw-promotion-v2-conditions', {
                computed: {
                    orderConditionsFilter() {
                        return overriddenCriteria;
                    },
                },
            });

            const wrapper = await createWrapper();

            expect(
                wrapper.getComponent('.sw-promotion-v2-conditions__rule-select-order-conditions').props('ruleFilter'),
            ).toBe(overriddenCriteria);
        });

        it.activeFeatureFlags(['v6.8.0.0'])('leaves the rule filter to the default of the rule select', async () => {
            const wrapper = await createWrapper();

            expect(
                wrapper.getComponent('.sw-promotion-v2-conditions__rule-select-order-conditions').props('ruleFilter'),
            ).toBeUndefined();
        });
    });
});
