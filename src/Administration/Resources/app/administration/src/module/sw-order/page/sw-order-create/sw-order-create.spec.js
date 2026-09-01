import { mount } from '@vue/test-utils';
import findByText from '../../../../../test/_helper_/find-by-text';

/**
 * @sw-package checkout
 */

const remindPaymentMock = jest.fn(() => {
    return Promise.resolve();
});

const routerPushMock = jest.fn();

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

    async function createWrapper({ routeName = 'sw.order.create.general' } = {}) {
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
                        name: routeName,
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
                    $router: {
                        push: routerPushMock,
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
            'sw-card-view': await wrapTestComponent('sw-card-view', {
                sync: true,
            }),
            'sw-tabs': {
                name: 'sw-tabs',
                props: {
                    defaultItem: {
                        type: String,
                        required: false,
                        default: undefined,
                    },
                    positionIdentifier: {
                        type: String,
                        required: false,
                        default: undefined,
                    },
                },
                template: '<div class="sw-tabs"><slot></slot></div>',
            },
            'sw-tabs-item': true,
            'mt-tabs': {
                name: 'mt-tabs',
                props: {
                    defaultItem: {
                        type: String,
                        required: false,
                        default: undefined,
                    },
                    items: {
                        type: Array,
                        required: true,
                    },
                    positionIdentifier: {
                        type: String,
                        required: true,
                    },
                },
                template: '<div class="mt-tabs"></div>',
            },
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
        routerPushMock.mockClear();

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

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy sw-tabs branch.
    it.deprecated('v6.8.0.0')('should render the fallback tabs branch', () => {
        const tabs = wrapper.getComponent({ name: 'sw-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-order-create');
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should render meteor tabs', async () => {
        wrapper = await createWrapper({
            routeName: 'sw.order.create.details',
        });

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-order-create');
        expect(tabs.props('defaultItem')).toBe('sw.order.create.details');
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-order.detail.tabGeneral',
                name: 'sw.order.create.general',
                onClick: expect.any(Function),
            },
            {
                label: 'sw-order.detail.tabDetails',
                name: 'sw.order.create.details',
                onClick: expect.any(Function),
            },
        ]);
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should navigate when a meteor route tab is clicked', async () => {
        wrapper = await createWrapper();

        const detailsTab = wrapper.vm.orderCreateTabs.find((tab) => tab.name === 'sw.order.create.details');

        detailsTab.onClick();

        expect(routerPushMock).toHaveBeenCalledWith({ name: 'sw.order.create.details' });
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
