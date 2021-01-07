import { createLocalVue, shallowMount, enableAutoDestroy } from '@vue/test-utils';
import 'src/module/sw-order/view/sw-order-create-base';
import Vuex from 'vuex';
import orderStore from 'src/module/sw-order/state/order.store';

function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);
    localVue.directive('tooltip', {});
    localVue.filter('currency', v => v);
    return shallowMount(Shopware.Component.build('sw-order-create-base'), {
        localVue,
        stubs: {
            'sw-card-view': true,
            'sw-card': {
                template: `
                    <div class="sw-card__content">
                        <slot name="grid"></slot>
                    </div>
                `
            },
            'sw-order-user-card': true,
            'sw-container': true,
            'sw-order-state-select': true,
            'sw-order-line-items-grid': true,
            'sw-card-section': true,
            'sw-description-list': true,
            'sw-order-saveable-field': true,
            'sw-order-state-history-card': true,
            'sw-order-delivery-metadata': true,
            'sw-order-document-card': true,
            'sw-order-create-details-header': true,
            'sw-order-create-details-body': true,
            'sw-order-create-details-footer': true,
            'sw-order-promotion-tag-field': true,
            'sw-order-line-items-grid-sales-channel': true,
            'sw-switch-field': true
        },
        mocks: {
            $tc: v => v,
            $store: Shopware.State._store
        }
    });
}

enableAutoDestroy(afterEach);

describe('src/module/sw-order/view/sw-order-create-base', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swOrder', orderStore);
        Shopware.Service().register('repositoryFactory', () => {
            return {
                create: () => {
                    return {
                        get: () => { }
                    };
                }
            };
        });
    });

    afterEach(() => {
        Shopware.State.commit('swOrder/setCart', {
            token: null,
            lineItems: []
        });
    });

    it('should be show successful notification', async () => {
        const wrapper = createWrapper();

        wrapper.vm.createNotificationSuccess = jest.fn();

        Shopware.State.commit('swOrder/setCart', {
            token: null,
            lineItems: [],
            errors: {
                'promotion-not-found': {
                    code: 0,
                    key: 'promotion-discount-added-1b8d2c67e3cf435ab3cb64ec394d4339',
                    level: 0,
                    message: 'Discount discount has been added',
                    messageKey: 'promotion-discount-added'
                }
            }
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationSuccess).toHaveBeenCalled();

        wrapper.vm.createNotificationSuccess.mockRestore();
    });

    it('should be show error notification', async () => {
        const wrapper = createWrapper();

        wrapper.vm.createNotificationError = jest.fn();

        Shopware.State.commit('swOrder/setCart', {
            token: null,
            lineItems: [],
            errors: {
                'promotion-discount-added-1b8d2c67e3cf435ab3cb64ec394d4339': {
                    code: 'promotion-code',
                    key: 'promotion-discount-added-1b8d2c67e3cf435ab3cb64ec394d4339',
                    level: 20,
                    message: 'Promotion with code promotion-code not found!',
                    messageKey: 'promotion-discount-added-1b8d2c67e3cf435ab3cb64ec394d4339'
                }
            }
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should be show warning notification', async () => {
        const wrapper = createWrapper();

        wrapper.vm.createNotificationWarning = jest.fn();

        Shopware.State.commit('swOrder/setCart', {
            token: null,
            lineItems: [],
            errors: {
                'promotion-warning': {
                    code: 10,
                    key: 'promotion-warning',
                    level: 10,
                    message: 'Promotion with code promotion-code warning!',
                    messageKey: 'promotion-warning'
                }
            }
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationWarning).toHaveBeenCalled();

        wrapper.vm.createNotificationWarning.mockRestore();
    });

    it('should only display Total row when status is tax free', async () => {
        const wrapper = createWrapper();

        Shopware.State.commit('swOrder/setCart', {
            token: null,
            lineItems: [],
            price: {
                taxStatus: 'tax-free'
            }
        });

        await wrapper.vm.$nextTick();

        const orderSummary = wrapper.find('.sw-order-create-summary__data');
        expect(orderSummary.html()).not.toContain('sw-order.createBase.summaryLabelAmountWithoutTaxes');
        expect(orderSummary.html()).not.toContain('sw-order.createBase.summaryLabelAmountTotal');
        expect(orderSummary.html()).toContain('sw-order.createBase.summaryLabelAmountGrandTotal');
    });

    it('should display Total excluding VAT and Total including VAT row when tax status is not tax free', async () => {
        const wrapper = createWrapper();

        Shopware.State.commit('swOrder/setCart', {
            token: null,
            lineItems: []
        });

        await wrapper.vm.$nextTick();

        const orderSummary = wrapper.find('.sw-order-create-summary__data');
        expect(orderSummary.html()).toContain('sw-order.createBase.summaryLabelAmountWithoutTaxes');
        expect(orderSummary.html()).toContain('sw-order.createBase.summaryLabelAmountTotal');
        expect(orderSummary.html()).not.toContain('sw-order.createBase.summaryLabelAmountGrandTotal');
    });
});
