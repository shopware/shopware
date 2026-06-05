import { mount } from '@vue/test-utils';
import findByText from '../../../../../test/_helper_/find-by-text';
import swOrderCreate from './index';

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

function createTabs() {
    const routerPush = jest.fn(() => Promise.resolve());
    const tabs = swOrderCreate.computed.tabs.call({
        $router: {
            push: routerPush,
        },
        $t: (snippet) => snippet,
    });

    return {
        routerPush,
        tabs,
    };
}

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
            'sw-loader': true,
            'sw-app-actions': true,
            'sw-notification-center': true,
            'sw-help-center': true,
            'sw-search-bar': true,
            'sw-language-switch': true,
            'sw-context-menu-item': true,
            'sw-context-button': true,
            'mt-tabs': true,
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
            'sw-app-topbar-sidebar': true,
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

    it('builds mt-tabs route items', () => {
        const { tabs } = createTabs();

        expect(tabs).toEqual([
            expect.objectContaining({
                label: 'sw-order.detail.tabGeneral',
                name: 'sw.order.create.general',
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-order.detail.tabDetails',
                name: 'sw.order.create.details',
                onClick: expect.any(Function),
            }),
        ]);
    });

    it('pushes the matching order create route when a tab is clicked', () => {
        const { routerPush, tabs } = createTabs();

        tabs[0].onClick();
        tabs[1].onClick();

        expect(routerPush).toHaveBeenNthCalledWith(1, { name: 'sw.order.create.general' });
        expect(routerPush).toHaveBeenNthCalledWith(2, { name: 'sw.order.create.details' });
    });

    it('should open remind payment modal on save order', async () => {
        await wrapper.find('.sw-button-process').trigger('click');
        await flushPromises();

        expect(wrapper.vm.showRemindPaymentModal).toBe(true);
        const modal = wrapper.find('.sw-modal');
        expect(modal.isVisible()).toBe(true);
    });

    it('should be able to close remind payment modal', async () => {
        await wrapper.find('.sw-button-process').trigger('click');
        await flushPromises();

        expect(wrapper.vm.showRemindPaymentModal).toBe(true);

        const modal = wrapper.find('.sw-modal');
        expect(modal.isVisible()).toBe(true);

        await findByText(modal, 'button', 'global.default.no').trigger('click');

        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.showRemindPaymentModal).not.toBe(true);
    });

    it('should remind payment on primary modal action', async () => {
        await wrapper.find('.sw-button-process').trigger('click');
        await flushPromises();

        expect(wrapper.vm.showRemindPaymentModal).toBe(true);

        const modal = wrapper.find('.sw-modal');
        expect(modal.isVisible()).toBe(true);

        await findByText(modal, 'button', 'sw-order.create.remindPaymentModal.primaryAction').trigger('click');
        await flushPromises();

        expect(remindPaymentMock).toHaveBeenCalledTimes(1);

        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.showRemindPaymentModal).not.toBe(true);
    });

    it('should be set context language after the process is successful', async () => {
        const buttonProcess = wrapper.find('.sw-button-process');
        await buttonProcess.trigger('click');
        await flushPromises();

        await wrapper.getComponent('.sw-button-process').vm.$emit('update:processSuccess');
        await flushPromises();

        expect(Shopware.Store.get('context').api.languageId).toBe('2fbb5fe2e29a4d70aa5854ce7ce3e20b');
    });

    it('should NOT set isSaveSuccessful immediately after save order, only after modal interaction', async () => {
        await wrapper.find('.sw-button-process').trigger('click');
        await flushPromises();

        expect(wrapper.vm.showRemindPaymentModal).toBe(true);
        expect(wrapper.vm.isSaveSuccessful).toBe(false);

        const modal = wrapper.find('.sw-modal');
        await findByText(modal, 'button', 'global.default.no').trigger('click');

        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.showRemindPaymentModal).toBe(false);
    });
});
