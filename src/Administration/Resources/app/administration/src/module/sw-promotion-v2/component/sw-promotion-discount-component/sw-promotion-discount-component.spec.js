/**
 * @package buyers-experience
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

const { Criteria, EntityCollection } = Shopware.Data;

async function createWrapper() {
    return mount(await wrapTestComponent('sw-promotion-discount-component', { sync: true }), {
        global: {
            stubs: {
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-select-field': {
                    template: '<select class="sw-field sw-select-field" :value="value" @change="$emit(\'update:value\', $event.target.value)"><slot></slot></select>',
                    props: ['value', 'disabled'],
                },
                'sw-switch-field': {
                    template: '<input class="sw-field sw-switch-field" type="checkbox" :value="value" @change="$emit(\'update:value\', $event.target.checked)" />',
                    props: ['value', 'disabled'],
                },
                'sw-promotion-v2-rule-select': {
                    template: '<div class="sw-promotion-v2-rule-select"></div>',
                },
                'sw-loader': {
                    template: '<div class="sw-loader"></div>',
                },
                'sw-number-field': {
                    template: '<input class="sw-field sw-number-field" type="number" :value="value" @input="$emit(\'update:value\', $event.target.value)" />',
                    props: ['value', 'disabled'],
                },
                'sw-icon': {
                    template: '<div class="sw-icon"></div>',
                },
                'sw-card': {
                    template: '<div class="sw-card"><slot></slot></div>',
                },
                'sw-context-button': {
                    template: '<div class="sw-context-button"><slot></slot></div>',
                },
                'sw-context-menu-item': {
                    template: '<div class="sw-context-menu-item"><slot></slot></div>',
                    props: ['disabled'],
                },
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="footer"></slot></div>',
                },
                'sw-one-to-many-grid': {
                    template: '<div class="sw-one-to-many-grid"></div>',
                },
                'sw-button': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entity) => {
                        if (entity === 'currency') {
                            return { search: () => Promise.resolve([{ id: 'promotionId1', isSystemDefault: true }]) };
                        }
                        return { search: () => Promise.resolve([{ id: 'promotionId1' }]) };
                    },
                },

                ruleConditionDataProviderService: {
                    getAwarenessConfigurationByAssignmentName: () => ({ snippet: 'fooBar' }),
                    getRestrictedRules: () => Promise.resolve([]),
                },
            },
        },
        props: {
            promotion: {
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
                personaRules: new EntityCollection('', 'rule', Shopware.Context.api, new Criteria(1, 25)),
                personaCustomers: [],
                orderRules: new EntityCollection('', 'rule', Shopware.Context.api, new Criteria(1, 25)),
                cartRules: new EntityCollection('', 'rule', Shopware.Context.api, new Criteria(1, 25)),
                translations: [],
                hasOrders: false,
            },
            discount: {
                isNew: () => false,
                promotionId: 'promotionId',
                scope: 'cart',
                type: 'absolute',
                value: 100,
                considerAdvancedRules: false,
                maxValue: null,
                sorterKey: 'PRICE_ASC',
                applierKey: 'ALL',
                usageKey: 'ALL',
                apiAlias: null,
                id: 'discountId',
                discountRules: new EntityCollection('', 'rule', Shopware.Context.api, new Criteria(1, 25)),
                promotionDiscountPrices: [],
            },
        },
    });
}

describe('src/module/sw-promotion-v2/component/sw-promotion-discount-component', () => {
    beforeAll(() => {
        Shopware.Service().register('syncService', () => {
            return {
                httpClient: {
                    get() {
                        return Promise.resolve([{}]);
                    },
                },
                getBasicHeaders() {
                    return {};
                },
            };
        });
    });

    it('should have disabled form fields', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();

        expect(wrapper.vm.isEditingDisabled).toBe(true);

        let elements = wrapper.findAllComponents('.sw-field');
        expect(elements.length).toBeGreaterThan(0);
        elements.forEach(el => expect(el.props('disabled')).toBe(true));

        elements = wrapper.findAllComponents('.sw-context-menu-item');
        expect(elements.length).toBeGreaterThan(0);
        elements.forEach(el => expect(el.props('disabled')).toBe(true));
    });

    it('should not have disabled form fields', async () => {
        global.activeAclRoles = ['promotion.editor'];

        const wrapper = await createWrapper();

        expect(wrapper.vm.isEditingDisabled).toBe(false);

        let elements = wrapper.findAllComponents('.sw-field');
        expect(elements.length).toBeGreaterThan(0);
        elements.forEach(el => expect(el.props('disabled')).toBe(false));

        elements = wrapper.findAllComponents('.sw-context-menu-item');
        expect(elements.length).toBeGreaterThan(0);
        elements.forEach(el => expect(el.props('disabled')).toBe(false));
    });

    it('should show product rule selection, if considerAdvancedRules switch is checked', async () => {
        global.activeAclRoles = ['promotion.editor'];

        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-promotion-discount-component__select-discount-rules').exists()).toBeFalsy();
        await wrapper.getComponent('.sw-switch-field[label="sw-promotion.detail.main.discounts.flagProductScopeLabel"]').vm.$emit('update:value', true);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-promotion-discount-component__select-discount-rules').exists()).toBeTruthy();
    });
});
