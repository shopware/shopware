import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-promotion/component/sw-promotion-persona-form';
import promotionState from 'src/module/sw-promotion/page/sw-promotion-detail/state';

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 * @feature-deprecated (flag:FEATURE_NEXT_13810)
 */
function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-promotion-persona-form'), {
        stubs: {
            'sw-entity-single-select': {
                template: '<div class="sw-entity-single-select"></div>'
            },
            'sw-field': {
                template: '<div class="sw-field"><slot></slot></div>'
            },
            'sw-data-grid': {
                template: '<div class="sw-data-grid"><slot></slot><slot name="actions"></slot></div>'
            },
            'sw-pagination': {
                template: '<div class="sw-pagination"><slot></slot></div>'
            },
            'sw-card': {
                template: '<div class="sw-card"><slot></slot></div>'
            },
            'sw-context-menu-item': {
                template: '<div class="sw-context-menu-item"><slot></slot></div>'
            },
            'sw-promotion-rule-select': {
                template: '<div class="sw-promotion-rule-select"><slot></slot></div>'
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
                create: () => {
                    return { search: () => {
                        return Promise.resolve([]);
                    } };
                }
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
                hasOrders: false,
                isNew: () => false
            }
        }
    });
}

describe('src/module/sw-promotion/component/sw-promotion-persona-form', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    beforeAll(() => {
        Shopware.State.registerModule('swPromotionDetail', promotionState);
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have disabled form fields', async () => {
        expect(wrapper.vm.isEditingDisabled).toBe(true);

        const elements = wrapper.findAll('.sw-promotion-rule-select');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));
    });

    it('should not have disabled form fields', async () => {
        wrapper = createWrapper([
            'promotion.editor'
        ]);

        expect(wrapper.vm.isEditingDisabled).toBe(false);

        const elements = wrapper.findAll('.sw-promotion-rule-select');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());
    });
});
