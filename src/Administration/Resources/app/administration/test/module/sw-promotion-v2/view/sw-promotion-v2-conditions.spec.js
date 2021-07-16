import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-promotion-v2/view/sw-promotion-v2-conditions';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-promotion-v2-conditions'), {
        localVue,
        stubs: {
            'sw-card': {
                template: '<div class="sw-card"><slot></slot></div>'
            },
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-text-field': {
                template: '<div class="sw-text-field"></div>'
            },
            'sw-number-field': {
                template: '<div class="sw-number-field"></div>'
            },
            'sw-entity-multi-select': {
                template: '<div class="sw-entity-multi-select"></div>'
            },
            'sw-promotion-v2-sales-channel-select': {
                template: '<div class="sw-promotion-v2-sales-channel-select"></div>'
            },
            'sw-promotion-v2-rule-select': {
                template: '<div class="sw-promotion-v2-rule-select"></div>'
            },
            'sw-switch-field': {
                template: '<div class="sw-switch-field"></div>'
            },
            'sw-promotion-v2-cart-condition-form': true
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
                    search: () => Promise.resolve([{ id: 'promotionId1' }])
                })
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
                useIndividualCodes: false,
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
                hasOrders: false,
                isNew() {
                    return true;
                }
            }
        }
    });
}

describe('src/module/sw-promotion-v2/component/sw-promotion-v2-conditions', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable adding discounts when privileges not set', () => {
        expect(
            wrapper.find('.sw-promotion-v2-conditions__sales-channel-selection').attributes().disabled
        ).toBeTruthy();
        expect(
            wrapper.find('.sw-promotion-v2-conditions__rules-exclusion-selection').attributes().disabled
        ).toBeTruthy();
        expect(
            wrapper.find('.sw-promotion-v2-conditions__rule-select-customer').attributes().disabled
        ).toBeTruthy();
        expect(
            wrapper.find('.sw-promotion-v2-conditions__rule-select-order-conditions').attributes().disabled
        ).toBeTruthy();
    });

    it('should enable adding discounts when privilege is set', () => {
        wrapper = createWrapper([
            'promotion.editor'
        ]);

        expect(
            wrapper.find('.sw-promotion-v2-conditions__sales-channel-selection').attributes().disabled
        ).toBeFalsy();
        expect(
            wrapper.find('.sw-promotion-v2-conditions__rules-exclusion-selection').attributes().disabled
        ).toBeFalsy();
        expect(
            wrapper.find('.sw-promotion-v2-conditions__rule-select-customer').attributes().disabled
        ).toBeFalsy();
        expect(
            wrapper.find('.sw-promotion-v2-conditions__rule-select-order-conditions').attributes().disabled
        ).toBeFalsy();
    });
});
