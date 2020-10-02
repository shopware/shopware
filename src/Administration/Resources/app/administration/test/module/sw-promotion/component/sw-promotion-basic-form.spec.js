import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-promotion/component/sw-promotion-basic-form';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-promotion-basic-form'), {
        localVue,
        stubs: {
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-field': {
                template: '<div class="sw-field"></div>'
            },
            'sw-promotion-sales-channel-select': {
                template: '<div class="sw-promotion-sales-channel-select"></div>'
            },
            'sw-datepicker': {
                template: '<div class="sw-field-datepicker"></div>'
            },
            'sw-entity-multi-select': {
                template: '<div class="sw-entity-multi-select"></div>'
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
                create: () => ({
                    search: () => Promise.resolve([{ id: 'promotionId1' }])
                })
            }
        },
        mocks: {
            $tc: v => v
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
            }
        }
    });
}

describe('src/module/sw-promotion/component/sw-promotion-basic-form', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have disabled form fields', async () => {
        let elements = wrapper.findAll('.sw-field');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));

        elements = wrapper.findAll('.sw-field-datepicker');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));

        elements = wrapper.findAll('.sw-entity-multi-select');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));

        elements = wrapper.findAll('.sw-promotion-sales-channel-select');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));
    });

    it('should not have disabled form fields', async () => {
        wrapper = createWrapper([
            'promotion.editor'
        ]);

        await wrapper.vm.$nextTick();

        let elements = wrapper.findAll('.sw-field');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());

        elements = wrapper.findAll('.sw-field-datepicker');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());

        elements = wrapper.findAll('.sw-entity-multi-select');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());

        elements = wrapper.findAll('.sw-promotion-sales-channel-select');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());
    });
});
