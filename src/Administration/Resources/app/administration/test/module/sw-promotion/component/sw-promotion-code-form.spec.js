import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-promotion/component/sw-promotion-code-form';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-promotion-code-form'), {
        localVue,
        stubs: {
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-field': {
                template: '<div class="sw-field"><slot></slot></div>'
            },
            'sw-contextual-field': {
                template: '<div class="sw-contextual-field"><slot></slot></div>'
            },
            'sw-switch-field': Shopware.Component.build('sw-switch-field'),
            'sw-base-field': true,
            'sw-field-error': true,
            'sw-icon': {
                template: '<div class="sw-icon"></div>'
            },
            'sw-modal': true,
            'sw-promotion-individualcodes': true
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
            validationService: () => {}
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

describe('src/module/sw-promotion/component/sw-promotion-code-form', () => {
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

        elements = wrapper.findAll('.sw-field--switch');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.find('input').attributes().disabled).toBe('disabled'));
    });

    it('should not have disabled form fields', async () => {
        wrapper = createWrapper([
            'promotion.editor'
        ]);

        const elements = wrapper.findAll('.sw-field--switch');
        expect(elements.wrappers.length).toBeGreaterThan(0);
        elements.wrappers.forEach(el => expect(el.find('input').attributes().disabled).toBeUndefined());
    });

    it('should not show notification warning when open modal individual codes', async () => {
        wrapper = createWrapper([
            'promotion.editor'
        ]);

        const spyOpenModalIndividualCodes = jest.spyOn(wrapper.vm, 'openModalIndividualCodes');

        expect(wrapper.vm.modalIndividualVisible).toBe(false);

        await wrapper.setProps({
            promotion: {
                ...wrapper.props().promotion,
                name: 'shopware',
                useCodes: false,
                useIndividualCodes: false
            }
        });

        expect(wrapper.vm.promotion.name).toBe('shopware');

        const switchCodes = wrapper.find('.sw-promotion-code-form__switch-codes input');
        await switchCodes.setChecked(true);
        expect(wrapper.vm.promotion.useCodes).toBe(true);

        const switchIndividual = wrapper.find('.sw-promotion-code-form__switch-individual input');
        await switchIndividual.setChecked(true);
        expect(wrapper.vm.promotion.useIndividualCodes).toBe(true);

        const button = wrapper.find('.sw-promotion-code-form__link-manage-individual');
        await button.trigger('click');

        expect(wrapper.vm.modalIndividualVisible).toBe(true);
        expect(spyOpenModalIndividualCodes).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.sw-promotion-code-form__modal-individual').exists()).toBeTruthy();
        expect(wrapper.find('sw-promotion-individualcodes-stub').exists()).toBeTruthy();
    });

    it('should show notification warning when promotion name couldn\'t be open modal individual codes', async () => {
        wrapper = createWrapper([
            'promotion.editor'
        ]);

        wrapper.vm.createNotificationWarning = jest.fn();

        const spyOpenModalIndividualCodes = jest.spyOn(wrapper.vm, 'openModalIndividualCodes');

        await wrapper.setProps({
            promotion: {
                ...wrapper.props().promotion,
                name: null,
                useCodes: false,
                useIndividualCodes: false
            }
        });

        expect(wrapper.vm.promotion.name).toBe(null);

        const switchCodes = wrapper.find('.sw-promotion-code-form__switch-codes input');
        await switchCodes.setChecked(true);
        expect(wrapper.vm.promotion.useCodes).toBe(true);

        const switchIndividual = wrapper.find('.sw-promotion-code-form__switch-individual input');
        await switchIndividual.setChecked(true);
        expect(wrapper.vm.promotion.useIndividualCodes).toBe(true);

        const button = wrapper.find('.sw-promotion-code-form__link-manage-individual');
        await button.trigger('click');

        expect(wrapper.vm.modalIndividualVisible).toBe(false);
        expect(wrapper.find('.sw-promotion-code-form__modal-individual').exists()).toBeFalsy();
        expect(spyOpenModalIndividualCodes).toHaveBeenCalledTimes(1);
        expect(wrapper.find('sw-promotion-individualcodes-stub').exists()).toBeFalsy();
        expect(wrapper.vm.createNotificationWarning).toHaveBeenCalledWith({
            message: 'sw-promotion.detail.main.general.codes.warningEmptyPromotionName'
        });
    });
});
