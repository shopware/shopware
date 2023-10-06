/**
 * @package buyers-experience
 */
import { shallowMount } from '@vue/test-utils';
import swPromotionV2GenerateCodesModalCmp from './index';

Shopware.Component.register('sw-promotion-v2-generate-codes-modal', swPromotionV2GenerateCodesModalCmp);

const mockCode = 'PREFIX_ABCD_SUFFIX';
async function createWrapper(propsData = {}) {
    return shallowMount(await Shopware.Component.build('sw-promotion-v2-generate-codes-modal'), {
        stubs: {
            'sw-card': {
                template: '<div class="sw-card"><slot></slot></div>',
            },
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>',
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
            'sw-modal': true,
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
        jest.advanceTimersByTime(1000);

        expect(wrapper.get('.sw-promotion-v2-generate-codes-modal__prefix').props('value')).toBe('PREFIX_');
        expect(wrapper.get('.sw-promotion-v2-generate-codes-modal__replacement').props('value')).toBe(4);
        expect(wrapper.get('.sw-promotion-v2-generate-codes-modal__suffix').props('value')).toBe('_SUFFIX');

        await wrapper.vm.$nextTick();

        expect(wrapper.get('.sw-promotion-v2-generate-codes-modal__preview').props('value')).toBe(mockCode);
    });

    it('should generate a correct preview in custom mode', async () => {
        const wrapper = await createWrapper({
            individualCodePattern: '%d%d%d',
        });

        const inputPrefix = wrapper.find('.sw-promotion-v2-generate-codes-modal__prefix');
        const inputCustomPattern = wrapper.find('.sw-promotion-v2-generate-codes-modal__custom-pattern');
        const inputPreview = wrapper.find('.sw-promotion-v2-generate-codes-modal__preview');

        expect(inputPrefix.exists()).toBeFalsy();
        expect(inputCustomPattern.isVisible()).toBe(true);
        jest.advanceTimersByTime(1000);

        await wrapper.vm.$nextTick();

        expect(inputPreview.isVisible()).toBe(true);
        expect(inputPreview.props('value')).toBe(mockCode);

        expect(wrapper.get('.sw-promotion-v2-generate-codes-modal__custom-pattern').isVisible()).toBe(true);

        await wrapper.vm.$nextTick();

        expect(wrapper.get('.sw-promotion-v2-generate-codes-modal__preview').props('value')).toBe(mockCode);
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
