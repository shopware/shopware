import { createLocalVue, shallowMount } from '@vue/test-utils';
import swPromotionDiscountComponent from 'src/module/sw-promotion-v2/component/sw-promotion-discount-component';
import 'src/app/component/form/sw-select-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/select/entity/sw-entity-many-to-many-select';
import 'src/app/component/utils/sw-loader';
import swPromotionV2RuleSelect from 'src/module/sw-promotion-v2/component/sw-promotion-v2-rule-select';

const { Criteria, EntityCollection } = Shopware.Data;

Shopware.Component.register('sw-promotion-discount-component', swPromotionDiscountComponent);
Shopware.Component.register('sw-promotion-v2-rule-select', swPromotionV2RuleSelect);

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(await Shopware.Component.build('sw-promotion-discount-component'), {
        localVue,
        stubs: {
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-select-field': await Shopware.Component.build('sw-select-field'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field': await Shopware.Component.build('sw-field'),
            'sw-switch-field': await Shopware.Component.build('sw-switch-field'),
            'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
            'sw-promotion-v2-rule-select': await Shopware.Component.build('sw-promotion-v2-rule-select'),
            'sw-entity-many-to-many-select': await Shopware.Component.build('sw-entity-many-to-many-select'),
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-loader': await Shopware.Component.build('sw-loader'),
            'sw-select-selection-list': true,
            'sw-number-field': true,
            'sw-field-error': true,
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
                getAwarenessConfigurationByAssignmentName: () => ({ snippet: 'fooBar' }),
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
                personaRules: new EntityCollection('', 'rule', Shopware.Context.api, new Criteria(1, 25)),
                personaCustomers: [],
                orderRules: new EntityCollection('', 'rule', Shopware.Context.api, new Criteria(1, 25)),
                cartRules: new EntityCollection('', 'rule', Shopware.Context.api, new Criteria(1, 25)),
                translations: [],
                hasOrders: false
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
                promotionDiscountPrices: []
            }
        }
    });
}

describe('src/module/sw-promotion-v2/component/sw-promotion-discount-component', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
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
        elements.wrappers.forEach(el => expect(el.classes()).toContain('is--disabled'));

        elements = wrapper.findAll('.sw-context-menu-item');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));

        elements = wrapper.findAll('select');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));
    });

    it('should not have disabled form fields', async () => {
        wrapper = await createWrapper([
            'promotion.editor'
        ]);

        expect(wrapper.vm.isEditingDisabled).toBe(false);

        let elements = wrapper.findAll('.sw-field');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.classes()).not.toContain('is--disabled'));

        elements = wrapper.findAll('.sw-context-menu-item');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());

        elements = wrapper.findAll('select');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());
    });

    it('should show product rule selection, if considerAdvancedRules switch is checked', async () => {
        wrapper = await createWrapper([
            'promotion.editor'
        ]);

        expect(wrapper.find('.sw-promotion-discount-component__select-discount-rules').exists()).toBeFalsy();
        await wrapper.find('[name="sw-field--discount-considerAdvancedRules"]').setChecked();
        expect(wrapper.find('.sw-promotion-discount-component__select-discount-rules').exists()).toBeTruthy();
    });
});
