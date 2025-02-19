import { mount } from '@vue/test-utils';
import findByText from '../../../../../test/_helper_/find-by-text';

/**
 * @sw-package checkout
 */

const remindPaymentMock = jest.fn(() => {
    return Promise.resolve();
});

const contextState = {
    id: 'context',
    state: () => ({
        api: {
            languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            systemLanguageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        },
    }),
    actions: {
        resetLanguageToDefault: jest.fn(),
    },
};

describe('src/module/sw-order/page/sw-order-create', () => {
    let wrapper;
    let stubs;

    async function createWrapper() {
        return mount(await wrapTestComponent('sw-order-create', { sync: true }), {
            global: {
                stubs,
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            get: () =>
                                Promise.resolve({
                                    translated: {
                                        distinguishableName: 'Cash on Delivery',
                                    },
                                }),
                        }),
                    },
                    shortcutService: {
                        startEventListener: () => {},
                        stopEventListener: () => {},
                    },
                },
                mocks: {
                    $route: {
                        meta: {
                            $module: {
                                routes: {
                                    detail: {
                                        children: {
                                            base: {},
                                            other: {},
                                        },
                                    },
                                },
                            },
                        },
                    },
                },
            },
        });
    }

    beforeAll(async () => {
        stubs = {
            'router-view': true,
            'sw-icon': true,
            'sw-loader': true,
            'sw-app-actions': true,
            'sw-notification-center': true,
            'sw-help-center': true,
            'sw-search-bar': true,
            'sw-language-switch': true,
            'sw-card-view': await wrapTestComponent('sw-card-view', {
                sync: true,
            }),
            'sw-tabs': await wrapTestComponent('sw-tabs', { sync: true }),
            'sw-tabs-item': true,
            'sw-page': await wrapTestComponent('sw-page', { sync: true }),
            'sw-button-process': await wrapTestComponent('sw-button-process', {
                sync: true,
            }),
            'sw-modal': {
                template: `
                    <div class="sw-modal">
                        <slot></slot>
                        <footer class="sw-modal__footer">
                            <slot name="modal-footer"></slot>
                        </footer>
                    </div>
                `,
            },
            'sw-order-create-invalid-promotion-modal': true,
            'sw-app-topbar-button': true,
            'sw-help-center-v2': true,
            'router-link': true,
            'sw-error-summary': true,
            'sw-tabs-deprecated': true,
        };
    });

    beforeEach(async () => {
        wrapper = await createWrapper();

        Shopware.Store.unregister('swOrder');
        Shopware.Store.register({
            id: 'swOrder',
            state() {
                return {
                    defaultSalesChannel: null,
                    cart: {
                        token: 'CART-TOKEN',
                        lineItems: [{}],
                    },
                    customer: {},
                    promotionCodes: [],
                };
            },
            getters: {
                invalidPromotionCodes() {
                    return [];
                },
            },
            actions: {
                saveOrder() {
                    return Promise.resolve({
                        data: {
                            id: Shopware.Utils.createId(),
                            transactions: [
                                {
                                    paymentMethodId: Shopware.Utils.createId(),
                                },
                            ],
                        },
                    });
                },
                createCart() {
                    return {
                        token: null,
                        lineItems: [],
                    };
                },
                remindPayment: remindPaymentMock,
            },
        });

        if (Shopware.Store.get('context')) {
            Shopware.Store.unregister('context');
        }

        Shopware.Store.register(contextState);
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should open remind payment modal on save order', async () => {
        await wrapper.find('.sw-button-process').trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showRemindPaymentModal).toBeTruthy();
        const modal = wrapper.find('.sw-modal');
        expect(modal.isVisible).toBeTruthy();
    });

    it('should be able to close remind payment modal', async () => {
        await wrapper.find('.sw-button-process').trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showRemindPaymentModal).toBeTruthy();

        const modal = wrapper.find('.sw-modal');
        expect(modal.isVisible).toBeTruthy();

        await findByText(modal, 'button', 'global.default.no').trigger('click');

        expect(wrapper.vm.isSaveSuccessful).toBeTruthy();
        expect(wrapper.vm.showRemindPaymentModal).not.toBeTruthy();
    });

    it('should remind payment on primary modal action', async () => {
        await wrapper.find('.sw-button-process').trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showRemindPaymentModal).toBeTruthy();

        const modal = wrapper.find('.sw-modal');
        expect(modal.isVisible).toBeTruthy();

        await findByText(modal, 'button', 'sw-order.create.remindPaymentModal.primaryAction').trigger('click');

        expect(remindPaymentMock).toHaveBeenCalledTimes(1);

        await wrapper.vm.$nextTick();
        expect(wrapper.vm.isSaveSuccessful).toBeTruthy();
        expect(wrapper.vm.showRemindPaymentModal).not.toBeTruthy();
    });

    it('should be set context language after the process is successful', async () => {
        const buttonProcess = wrapper.find('.sw-button-process');
        await buttonProcess.trigger('click');

        await wrapper.getComponent('.sw-button-process').vm.$emit('update:processSuccess');
        await flushPromises();

        expect(Shopware.Store.get('context').api.languageId).toBe('2fbb5fe2e29a4d70aa5854ce7ce3e20b');
    });
});
