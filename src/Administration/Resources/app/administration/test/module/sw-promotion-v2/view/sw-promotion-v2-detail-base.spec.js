import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-promotion-v2/view/sw-promotion-v2-detail-base';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-promotion-v2-detail-base'), {
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
            'sw-switch-field': {
                template: '<div class="sw-switch-field"></div>'
            },
            'sw-select-field': {
                template: '<div class="sw-select-field"></div>'
            },
            'sw-datepicker': {
                template: '<div class="sw-datepicker"></div>'
            },
            'sw-button-process': true
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
            },
            promotionCodeApiService: {
                generateCodeFixed: () => 'ABCDEF'
            },
            customFieldDataProviderService: {
                getCustomFieldSets: () => Promise.resolve([])
            }
        },
        propsData: {
            isCreateMode: false,
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
                hasOrders: false
            }
        }
    });
}

describe('src/module/sw-promotion-v2/component/sw-promotion-v2-detail-base', () => {
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
        const textFields = wrapper.findAll('.sw-text-field');
        expect(textFields.wrappers.length).toBeGreaterThan(0);
        textFields.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));

        const numberFields = wrapper.findAll('.sw-number-field');
        expect(numberFields.wrappers.length).toBeGreaterThan(0);
        numberFields.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));

        const switchFields = wrapper.findAll('.sw-switch-field');
        expect(switchFields.wrappers.length).toBeGreaterThan(0);
        switchFields.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));

        const selectField = wrapper.findAll('.sw-select-field');
        expect(selectField.wrappers.length).toBeGreaterThan(0);
        selectField.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));

        const datepickerFields = wrapper.findAll('.sw-datepicker');
        expect(datepickerFields.wrappers.length).toBeGreaterThan(0);
        datepickerFields.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));
    });

    it('should not have disabled form fields', async () => {
        wrapper = createWrapper([
            'promotion.editor'
        ]);

        await wrapper.vm.$nextTick();

        const textFields = wrapper.findAll('.sw-text-field');
        expect(textFields.wrappers.length).toBeGreaterThan(0);
        textFields.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());

        const numberFields = wrapper.findAll('.sw-number-field');
        expect(numberFields.wrappers.length).toBeGreaterThan(0);
        numberFields.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());

        const switchFields = wrapper.findAll('.sw-switch-field');
        expect(switchFields.wrappers.length).toBeGreaterThan(0);
        switchFields.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());

        const selectFields = wrapper.findAll('.sw-select-field');
        expect(selectFields.wrappers.length).toBeGreaterThan(0);
        selectFields.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());

        const datepickerFields = wrapper.findAll('.sw-datepicker');
        expect(datepickerFields.wrappers.length).toBeGreaterThan(0);
        datepickerFields.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());
    });
});
