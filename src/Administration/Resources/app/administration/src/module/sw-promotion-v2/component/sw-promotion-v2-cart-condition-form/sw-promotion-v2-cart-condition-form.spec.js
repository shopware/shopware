import { createLocalVue, shallowMount } from '@vue/test-utils';
import swPromotionV2CartConditionForm from 'src/module/sw-promotion-v2/component/sw-promotion-v2-cart-condition-form';

Shopware.Component.register('sw-promotion-v2-cart-condition-form', swPromotionV2CartConditionForm);

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(await Shopware.Component.build('sw-promotion-v2-cart-condition-form'), {
        localVue,
        stubs: {
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>',
            },
            'sw-switch-field': {
                template: '<div class="sw-switch-field"></div>',
            },
            'sw-card': {
                template: '<div class="sw-card"><slot></slot></div>',
            },
            'sw-promotion-rule-select': {
                template: '<div class="sw-promotion-rule-select"></div>',
            },
            'sw-promotion-v2-rule-select': true,
        },
        provide: {
            acl: {
                can: (key) => {
                    if (!key) { return true; }

                    return privileges.includes(key);
                },
            },

            promotionSyncService: {
                loadPackagers: () => Promise.resolve(),
                loadSorters: () => Promise.resolve(),
            },

            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve([{ id: 'promotionId1' }]),
                }),
            },

            ruleConditionDataProviderService: {},
        },
        propsData: {
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
                personaRules: [],
                personaCustomers: [],
                orderRules: [],
                cartRules: [],
                translations: [],
                hasOrders: false,
                isNew: () => false,
            },
        },
    });
}

describe('src/module/sw-promotion-v2/component/sw-promotion-v2-cart-condition-form', () => {
    beforeAll(() => {
        Shopware.Service().register('syncService', () => {
            return {
                httpClient: {
                    get() {
                        return Promise.resolve({});
                    },
                },
                getBasicHeaders() {
                    return {};
                },
            };
        });
    });

    it('should have disabled form fields', async () => {
        const wrapper = await createWrapper();

        const elements = wrapper.findAll('.sw-switch-field');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));

        const promotionSelectionElements = wrapper.findAll('.sw-promotion-v2-cart-condition-form__rule-select-cart');
        expect(promotionSelectionElements.wrappers.length).toBeGreaterThan(0);
        promotionSelectionElements.wrappers.forEach(el => expect(el.attributes().disabled).toBe('true'));
    });

    it('should not have disabled form fields', async () => {
        const wrapper = await createWrapper([
            'promotion.editor',
        ]);

        const elements = wrapper.findAll('.sw-switch-field');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());

        const promotionSelectionElements = wrapper.findAll('.sw-promotion-v2-cart-condition-form__rule-select-cart');
        expect(promotionSelectionElements.wrappers.length).toBeGreaterThan(0);
        promotionSelectionElements.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());
    });

    it('should add conditions association', async () => {
        const wrapper = await createWrapper();
        const criteria = wrapper.vm.ruleFilter;

        expect(criteria.associations[0].association).toBe('conditions');
    });
});
