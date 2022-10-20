import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-promotion-v2/component/promotion-codes/sw-promotion-v2-individual-codes-behavior';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-button-process';

function createWrapper(additionalPromotionData = {}) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-promotion-v2-individual-codes-behavior'), {
        localVue,
        stubs: {
            'sw-card': {
                template: '<div class="sw-card"><slot></slot><slot name="toolbar"></slot></div>'
            },
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-card-filter': {
                template: '<div class="sw-card-filter"><slot name="filter"></slot></div>'
            },
            'sw-modal': {
                template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>'
            },
            'sw-confirm-modal': true,
            'sw-promotion-v2-generate-codes-modal': {
                template: '<div class="sw-promotion-v2-generate-codes-modal"></div>'
            },
            'sw-one-to-many-grid': true,
            'sw-empty-state': {
                template: '<div class="sw-empty-state"><slot></slot><slot name="actions"></slot></div>'
            },
            'sw-context-menu-item': true,
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-button-process': Shopware.Component.build('sw-button-process'),
            'sw-number-field': {
                template: '<div class="sw-number-field"><slot></slot></div>'
            },
            'sw-icon': true
        },
        provide: {
            acl: {
                can: () => true
            },
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve([{ id: 'promotionId1' }])
                })
            },
            promotionCodeApiService: {
                addIndividualCodes() {
                    return new Promise(resolve => resolve());
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
                ...additionalPromotionData
            }
        }
    });
}

describe('src/module/sw-promotion-v2/component/sw-promotion-v2-individual-codes-behavior', () => {
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

    it('should open the individual codes generation modal in empty state', async () => {
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
        wrapper = await createWrapper({
            individualCodes: ['dummy']
        });

        let codesModal = wrapper.find('.sw-promotion-v2-generate-codes-modal');
        expect(codesModal.exists()).toBe(false);

        const generateButton = wrapper.find('.sw-promotion-v2-individual-codes-behavior__generate-codes-action');
        await generateButton.trigger('click');

        codesModal = wrapper.find('.sw-promotion-v2-generate-codes-modal');
        expect(codesModal.exists()).toBe(true);
    });

    it('should open the add codes modal, when codes already exist', async () => {
        wrapper = await createWrapper({
            individualCodes: ['dummy']
        });

        let addModal = wrapper.find('.sw-promotion-v2-individual-codes-behavior__add-codes-modal');
        expect(addModal.exists()).toBe(false);

        const addButton = wrapper.find('.sw-promotion-v2-individual-codes-behavior__add-codes-action');
        await addButton.trigger('click');

        addModal = wrapper.find('.sw-promotion-v2-individual-codes-behavior__add-codes-modal');
        expect(addModal.exists()).toBe(true);

        const codeAmountInput = wrapper.find('.sw-promotion-v2-individual-codes-behavior__code-amount');
        const addCodesModalButton = wrapper.find('.sw-promotion-v2-individual-codes-behavior__add-codes-button-confirm');

        expect(codeAmountInput.attributes().value).toBe('10');
        expect(addCodesModalButton.exists()).toBe(true);
        await addCodesModalButton.trigger('click');

        expect(wrapper.vm.addCodesModal).toBe(false);
    });
});
