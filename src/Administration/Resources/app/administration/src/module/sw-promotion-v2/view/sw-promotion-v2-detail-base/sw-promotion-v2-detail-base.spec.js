/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-promotion-v2-detail-base', { sync: true }), {
        global: {
            stubs: {
                'sw-card': {
                    template: '<div class="sw-card"><slot></slot></div>',
                },
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-text-field': {
                    template: '<div class="sw-field sw-text-field"></div>',
                    props: ['disabled'],
                },
                'sw-number-field': {
                    template: '<div class="sw-field sw-number-field"></div>',
                    props: ['disabled'],
                },
                'sw-switch-field': {
                    template: '<div class="sw-field sw-switch-field"></div>',
                    props: ['disabled'],
                },
                'sw-select-field': {
                    template: '<div class="sw-field sw-select-field"></div>',
                    props: ['disabled'],
                },
                'sw-datepicker': {
                    template: '<div class="sw-field sw-datepicker"></div>',
                    props: ['disabled'],
                },
                'sw-button-process': {
                    template: '<div class="sw-button-process"></div>',
                    props: ['disabled'],
                },
                'sw-promotion-v2-individual-codes-behavior': true,
                'sw-custom-field-set-renderer': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve([{ id: 'promotionId1' }]),
                    }),
                },
                promotionCodeApiService: {
                    generateCodeFixed: () => 'ABCDEF',
                },
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve([]),
                },
            },
        },
        props: {
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
            },
        },
    });
}

describe('src/module/sw-promotion-v2/component/sw-promotion-v2-detail-base', () => {
    it('should have disabled form fields', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();

        wrapper.findAllComponents('.sw-field').forEach((el) => {
            expect(el.props('disabled')).toBe(true);
        });
        expect(wrapper.findComponent('.sw-button-process').props('disabled')).toBe(true);
    });

    it('should not have disabled form fields', async () => {
        global.activeAclRoles = ['promotion.editor'];

        const wrapper = await createWrapper();

        wrapper.findAllComponents('.sw-field').forEach((el) => expect(el.props('disabled')).toBeFalsy());
        expect(wrapper.findComponent('.sw-button-process').props('disabled')).toBe(false);
    });
});
