import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-promotion-v2/component/promotion-codes/sw-promotion-v2-generate-codes-modal';

const swPromotionV2GenerateCodesModal = Shopware.Component.build('sw-promotion-v2-generate-codes-modal');
const mockCode = 'PREFIX_ABCD_SUFFIX';

function createWrapper(promotionProps = {}) {
    const localVue = createLocalVue();

    return shallowMount(swPromotionV2GenerateCodesModal, {
        localVue,
        stubs: {
            'sw-card': {
                template: '<div class="sw-card"><slot></slot></div>'
            },
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-text-field': {
                template: '<div class="sw-text-field"><slot></slot></div>'
            },
            'sw-number-field': {
                template: '<div class="sw-number-field"><slot></slot></div>'
            },
            'sw-switch-field': true,
            'sw-field-error': true,
            'sw-modal': true,
            'sw-alert': true,
            'sw-button': true,
            'sw-button-process': true
        },
        provide: {
            promotionCodeApiService: {
                generatePreview() {
                    return new Promise(resolve => resolve(mockCode));
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
                useIndividualCodes: false,
                individualCodePattern: 'PREFIX_%s%s%s%s_SUFFIX',
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
                ...promotionProps
            }
        }
    });
}

describe('src/module/sw-promotion-v2/component/sw-promotion-v2-generate-codes-modal', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should generate a correct preview', async () => {
        const inputPrefix = await wrapper.find('.sw-promotion-v2-generate-codes-modal__prefix');
        const inputCodeLength = await wrapper.find('.sw-promotion-v2-generate-codes-modal__replacement');
        const inputSuffix = await wrapper.find('.sw-promotion-v2-generate-codes-modal__suffix');
        const inputPreview = await wrapper.find('.sw-promotion-v2-generate-codes-modal__preview');

        expect(inputPrefix.isVisible()).toBe(true);
        expect(inputPrefix.attributes().value).toBe('PREFIX_');

        expect(inputCodeLength.isVisible()).toBe(true);
        expect(inputCodeLength.attributes().value).toBe('4');

        expect(inputSuffix.isVisible()).toBe(true);
        expect(inputSuffix.attributes().value).toBe('_SUFFIX');

        await swPromotionV2GenerateCodesModal.methods.updatePreview.flush();
        await wrapper.vm.$nextTick();

        expect(inputPreview.isVisible()).toBe(true);
        expect(inputPreview.attributes().value).toBe(mockCode);
    });

    it('should generate a correct preview in custom mode', async () => {
        wrapper = await createWrapper({
            individualCodePattern: '%d%d%d'
        });

        const inputPrefix = await wrapper.find('.sw-promotion-v2-generate-codes-modal__prefix');
        const inputCustomPattern = await wrapper.find('.sw-promotion-v2-generate-codes-modal__custom-pattern');
        const inputPreview = await wrapper.find('.sw-promotion-v2-generate-codes-modal__preview');

        expect(inputPrefix.exists()).toBeFalsy();
        expect(inputCustomPattern.isVisible()).toBe(true);

        await swPromotionV2GenerateCodesModal.methods.updatePreview.flush();
        await wrapper.vm.$nextTick();

        expect(inputPreview.isVisible()).toBe(true);
        expect(inputPreview.attributes().value).toBe(mockCode);
    });
});
