/**
 * @package buyers-experience
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

const mockCode = 'PREFIX_ABCD_SUFFIX';
async function createWrapper(propsData = {}) {
    return mount(await wrapTestComponent('sw-promotion-v2-generate-codes-modal', { sync: true }), {
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
                useIndividualCodes: false,
                individualCodePattern: 'PREFIX_%s%s%s%s_SUFFIX',
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
                salesChannels: [{
                    promotionId: 'promotionId',
                    salesChannelId: 'salesChannelId',
                    priority: 1,
                    createdAt: '2020-08-17T13:24:52.692+00:00',
                    id: 'promotionSalesChannelId',
                }],
                discounts: [],
                individualCodes: [],
                personaRules: [],
                personaCustomers: [],
                orderRules: [],
                cartRules: [],
                translations: [],
                hasOrders: false,
                ...propsData,
            },
        },
        global: {
            stubs: {
                'sw-card': {
                    template: '<div class="sw-card"><slot /></div>',
                },
                'sw-container': {
                    template: '<div class="sw-container"><slot /></div>',
                },
                'sw-text-field': {
                    template: '<input class="sw-text-field"></input>',
                    props: ['value'],
                },
                'sw-number-field': {
                    template: '<input class="sw-number-field"></input>',
                    props: ['value'],
                },
                'sw-switch-field': true,
                'sw-field-error': true,
                'sw-modal': {
                    template: '<div class="sw-modal"><slot /></div>',
                },
                'sw-alert': true,
                'sw-button': true,
                'sw-button-process': true,
            },
            provide: {
                promotionCodeApiService: {
                    generatePreview() {
                        return new Promise((resolve) => {
                            resolve(mockCode);
                        });
                    },
                },
            },
        },
    });
}

describe('src/module/sw-promotion-v2/component/sw-promotion-v2-generate-codes-modal', () => {
    beforeAll(() => {
        jest.useFakeTimers();
    });

    afterAll(() => {
        jest.useRealTimers();
    });

    it('should generate a correct preview', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        jest.advanceTimersByTime(1000);

        expect(wrapper.getComponent('.sw-promotion-v2-generate-codes-modal__prefix').props('value')).toBe('PREFIX_');
        expect(wrapper.getComponent('.sw-promotion-v2-generate-codes-modal__replacement').props('value')).toBe(4);
        expect(wrapper.getComponent('.sw-promotion-v2-generate-codes-modal__suffix').props('value')).toBe('_SUFFIX');

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.getComponent('.sw-promotion-v2-generate-codes-modal__preview').props('value')).toBe(mockCode);
    });

    it('should generate a correct preview in custom mode', async () => {
        const wrapper = await createWrapper({
            individualCodePattern: '%d%d%d',
        });

        await wrapper.vm.$nextTick();
        jest.advanceTimersByTime(1000);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-promotion-v2-generate-codes-modal__prefix').exists()).toBe(false);
        expect(wrapper.find('.sw-promotion-v2-generate-codes-modal__suffix').exists()).toBe(false);

        const inputCustomPattern = wrapper.getComponent('.sw-promotion-v2-generate-codes-modal__custom-pattern');
        const inputPreview = wrapper.getComponent('.sw-promotion-v2-generate-codes-modal__preview');

        expect(inputCustomPattern.props('value')).toBe('%d%d%d');
        expect(inputPreview.props('value')).toBe(mockCode);

        expect(wrapper.get('.sw-promotion-v2-generate-codes-modal__custom-pattern').isVisible()).toBe(true);
        await wrapper.vm.$nextTick();

        expect(wrapper.getComponent('.sw-promotion-v2-generate-codes-modal__preview').props('value')).toBe(mockCode);
    });

    it('should show or hide alert depends on existing individualCodes', async () => {
        const alertWarningClass = '.sw-promotion-v2-generate-codes-modal__warning';
        const wrapper = await createWrapper();
        jest.advanceTimersByTime(1000);

        expect(wrapper.find(alertWarningClass).exists()).toBeFalsy();

        await wrapper.setProps({
            promotion: {
                individualCodes: [{
                    promotionId: '47ad67b4113641b0a7c8bdfa5690d384',
                    code: 'xyzT',
                    id: '9ddf9c0562524e2388456e13fdab1949',
                }],
            },
        });

        await wrapper.vm.$nextTick();
        expect(wrapper.get(alertWarningClass).exists()).toBeTruthy();
    });
});
