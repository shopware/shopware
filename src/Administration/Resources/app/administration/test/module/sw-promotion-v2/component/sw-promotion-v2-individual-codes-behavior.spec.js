import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-promotion-v2/component/sw-promotion-v2-individual-codes-behavior';
import 'src/app/component/base/sw-button';

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
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-one-to-many-grid': true,
            'sw-icon': true,
            'sw-context-menu-item': true,
            'sw-confirm-modal': true,
            'sw-empty-state': {
                template: '<div class="sw-empty-state"><slot></slot><slot name="actions"></slot></div>'
            },
            'sw-promotion-v2-generate-codes-modal': {
                template: '<div class="sw-promotion-v2-generate-codes-modal"></div>'
            }
        },
        provide: {
            acl: {
                can: () => true
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
        expect(codesModal.exists()).toBe(false);

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
});
