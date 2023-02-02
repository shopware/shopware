import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-promotion/component/sw-promotion-discount-component';

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 * @feature-deprecated (flag:FEATURE_NEXT_13810)
 */
function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-promotion-discount-component'), {
        localVue,
        stubs: {
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-select-field': {
                template: '<div class="sw-select-field"></div>'
            },
            'sw-field': {
                template: '<div class="sw-field"></div>'
            },
            'sw-icon': {
                template: '<div class="sw-icon"></div>'
            },
            'sw-card': {
                template: '<div class="sw-card"><slot></slot></div>'
            },
            'sw-context-button': {
                template: '<div class="sw-context-button"><slot></slot></div>'
            },
            'sw-context-menu-item': {
                template: '<div class="sw-context-menu-item"><slot></slot></div>'
            },
            'sw-modal': {
                template: '<div class="sw-modal"><slot></slot><slot name="footer"></slot></div>'
            },
            'sw-one-to-many-grid': {
                template: '<div class="sw-one-to-many-grid"></div>'
            }
        },
        provide: {
            acl: {
                can: (key) => {
                    if (!key) { return true; }

                    return privileges.includes(key);
                }
            },
            repositoryFactory: {
                create: (entity) => {
                    if (entity === 'currency') {
                        return { search: () => Promise.resolve([{ id: 'promotionId1', isSystemDefault: true }]) };
                    }
                    return { search: () => Promise.resolve([{ id: 'promotionId1' }]) };
                }
            },

            ruleConditionDataProviderService: {
                getRestrictedRules: () => Promise.resolve([])
            }
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
            },
            discount: {
                promotionId: 'promotionId',
                scope: 'delivery',
                type: 'absolute',
                value: 100,
                considerAdvancedRules: false,
                maxValue: null,
                sorterKey: 'PRICE_ASC',
                applierKey: 'ALL',
                usageKey: 'ALL',
                apiAlias: null,
                id: 'discountId',
                discountRules: [],
                promotionDiscountPrices: []
            }
        }
    });
}

describe('src/module/sw-promotion/component/sw-promotion-discount-component', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    beforeAll(() => {
        Shopware.Service().register('syncService', () => {
            return {
                httpClient: {
                    get() {
                        return Promise.resolve([{}]);
                    }
                },
                getBasicHeaders() {
                    return {};
                }
            };
        });
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have disabled form fields', async () => {
        expect(wrapper.vm.isEditingDisabled).toBe(true);

        let elements = wrapper.findAll('.sw-field');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));

        elements = wrapper.findAll('.sw-context-menu-item');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));

        elements = wrapper.findAll('.sw-select-field');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));
    });

    it('should not have disabled form fields', async () => {
        wrapper = createWrapper([
            'promotion.editor'
        ]);

        expect(wrapper.vm.isEditingDisabled).toBe(false);

        let elements = wrapper.findAll('.sw-field');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());

        elements = wrapper.findAll('.sw-context-menu-item');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());

        elements = wrapper.findAll('.sw-select-field');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());
    });
});
