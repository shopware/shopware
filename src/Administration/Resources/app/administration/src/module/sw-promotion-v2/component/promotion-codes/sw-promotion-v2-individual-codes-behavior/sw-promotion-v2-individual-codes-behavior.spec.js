/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

async function createWrapper(additionalPromotionData = {}) {
    return mount(
        await wrapTestComponent('sw-promotion-v2-individual-codes-behavior', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-card': {
                        template: '<div class="sw-card"><slot></slot><slot name="toolbar"></slot></div>',
                    },
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                    },
                    'sw-card-filter': {
                        template: '<div class="sw-card-filter"><slot name="filter"></slot></div>',
                    },
                    'sw-modal': {
                        template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                    },
                    'sw-confirm-modal': true,
                    'sw-promotion-v2-generate-codes-modal': {
                        template: '<div class="sw-promotion-v2-generate-codes-modal"></div>',
                    },
                    'sw-one-to-many-grid': true,
                    'sw-empty-state': {
                        template: '<div class="sw-empty-state"><slot></slot><slot name="actions"></slot></div>',
                    },
                    'sw-context-menu-item': true,
                    'sw-button': {
                        template: '<button class="sw-button" @click="$emit(\'click\', $event.target.value)"></button>',
                        props: ['disabled'],
                    },
                    'sw-button-process': {
                        template:
                            '<button class="sw-button-process" @click="$emit(\'click\', $event.target.value)"></button>',
                        props: ['disabled'],
                    },
                    'sw-number-field': {
                        template: '<div class="sw-number-field"><slot></slot></div>',
                        props: ['value'],
                    },
                    'sw-icon': true,
                    'sw-loader': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => Promise.resolve([{ id: 'promotionId1' }]),
                        }),
                    },
                    promotionCodeApiService: {
                        addIndividualCodes() {
                            return new Promise((resolve) => {
                                resolve();
                            });
                        },
                    },
                },
            },
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
                    ...additionalPromotionData,
                },
            },
        },
    );
}

describe('src/module/sw-promotion-v2/component/sw-promotion-v2-individual-codes-behavior', () => {
    it('should open the individual codes generation modal in empty state', async () => {
        const wrapper = await createWrapper();

        let codesModal = wrapper.find('.sw-promotion-v2-generate-codes-modal');
        const addModal = wrapper.find('.sw-promotion-v2-individual-codes-behavior__add-codes-modal');
        const addButton = wrapper.find('.sw-promotion-v2-individual-codes-behavior__add-codes-action');

        expect(codesModal.exists()).toBe(false);
        expect(addModal.exists()).toBe(false);
        expect(addButton.exists()).toBe(false);

        const generateButton = wrapper.find('.sw-promotion-v2-individual-codes-behavior__empty-state-generate-action');
        await generateButton.trigger('click');

        codesModal = wrapper.find('.sw-promotion-v2-generate-codes-modal');
        expect(codesModal.exists()).toBe(true);
    });

    it('should open the individual codes generation modal, when codes already exist', async () => {
        const wrapper = await createWrapper({
            individualCodes: ['dummy'],
        });

        let codesModal = wrapper.find('.sw-promotion-v2-generate-codes-modal');
        expect(codesModal.exists()).toBe(false);

        const generateButton = wrapper.find('.sw-promotion-v2-individual-codes-behavior__generate-codes-action');
        await generateButton.trigger('click');

        codesModal = wrapper.find('.sw-promotion-v2-generate-codes-modal');
        expect(codesModal.exists()).toBe(true);
    });

    it('should open the add codes modal, when codes already exist', async () => {
        const wrapper = await createWrapper({
            individualCodes: ['dummy'],
        });

        let addModal = wrapper.find('.sw-promotion-v2-individual-codes-behavior__add-codes-modal');
        expect(addModal.exists()).toBe(false);

        await wrapper.getComponent('.sw-promotion-v2-individual-codes-behavior__add-codes-action').trigger('click');

        addModal = wrapper.find('.sw-promotion-v2-individual-codes-behavior__add-codes-modal');
        expect(addModal.exists()).toBe(true);

        const codeAmountInput = wrapper.getComponent('.sw-promotion-v2-individual-codes-behavior__code-amount');
        const addCodesModalButton = wrapper.find('.sw-promotion-v2-individual-codes-behavior__add-codes-button-confirm');

        expect(codeAmountInput.props('value')).toBe(10);
        expect(addCodesModalButton.exists()).toBe(true);
        await addCodesModalButton.trigger('click');

        expect(wrapper.vm.addCodesModal).toBe(false);
    });
});
