/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

const strategies = [
    {
        description: '',
        name: 'move-shop-permanently',
    },
    {
        description: '',
        name: 'reinstall-apps',
    },
    {
        description: '',
        name: 'uninstall-apps',
    },
];

describe('sw-app-shop-id-change-modal', () => {
    let wrapper = null;
    let stubs;
    let shopIdChangeService;
    let reloadPage;

    async function createWrapper() {
        shopIdChangeService = {
            getChangeStrategies: () => Promise.resolve(strategies),
            checkShopId: jest.fn(() => Promise.resolve()),
            changeShopId: jest.fn(() => Promise.resolve()),
        };

        reloadPage = jest.fn();
        const component = await wrapTestComponent('sw-app-shop-id-change-modal', {
            sync: true,
        });

        return mount(
            { ...component, methods: { ...component.methods, _reloadPage: reloadPage } },
            {
                props: {
                    shopIdCheck: {
                        fingerprints: {
                            mismatchingFingerprints: {
                                app_url: {
                                    identifier: 'app_url',
                                    storedStamp: 'old-app-url',
                                    expectedStamp: 'new-app-url',
                                    score: 100,
                                },
                                installation_path: {
                                    identifier: 'installation_path',
                                    storedStamp: 'old-installation-path',
                                    expectedStamp: 'new-installation-path',
                                    score: 100,
                                },
                                sales_channel_domain_urls: {
                                    identifier: 'sales_channel_domain_urls',
                                    storedStamp: 'old-sales-channel-domain-urls',
                                    expectedStamp: 'new-sales-channel-domain-urls',
                                    score: 25,
                                },
                            },
                            matchingFingerprints: [],
                            score: 225,
                            threshold: 75,
                        },
                        apps: ['Test Foo App', 'Test Bar App', 'Test Baz App'],
                    },
                },
                global: {
                    stubs,
                    provide: {
                        shopIdChangeService,
                        shortcutService: {
                            startEventListener() {},
                            stopEventListener() {},
                        },
                    },
                },
            },
        );
    }

    function strategyButtons() {
        return wrapper.findAll('.sw-app-shop-id-change-modal__button-strategy');
    }

    function isActive(button) {
        return button.classes('sw-app-shop-id-change-modal__button-strategy--active');
    }

    beforeAll(async () => {
        stubs = {
            'sw-modal': {
                template: `
                    <div class="sw-modal">
                        <slot name="modal-header">
                            <slot name="modal-title"></slot>
                        </slot>
                        <slot name="modal-body">
                             <slot></slot>
                        </slot>
                        <slot name="modal-footer">
                        </slot>
                    </div>`,
            },
            'sw-loader': await wrapTestComponent('sw-loader'),
            'router-link': true,
            'sw-help-text': true,
            'sw-confirm-modal': {
                template: '<div class="sw-confirm-modal"></div>',
                emits: ['confirm', 'cancel', 'close'],
            },
        };
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should select the first strategy by default', async () => {
        expect(strategyButtons()).toHaveLength(3);
        expect(isActive(strategyButtons().at(0))).toBe(true);
    });

    it('emits modal-close if modal is closed', async () => {
        wrapper.findComponent(stubs['sw-modal']).vm.$emit('modal-close');

        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('selects clicked strategy', async () => {
        await strategyButtons().at(1).trigger('click');
        expect(strategyButtons().map(isActive)).toStrictEqual([false, true, false]);

        await strategyButtons().at(2).trigger('click');
        expect(strategyButtons().map(isActive)).toStrictEqual([false, false, true]);

        await strategyButtons().at(0).trigger('click');
        expect(strategyButtons().map(isActive)).toStrictEqual([true, false, false]);
    });

    it('should send the selected strategy', async () => {
        await strategyButtons().at(1).trigger('click');
        await wrapper.get('.mt-button--primary').trigger('click');
        await flushPromises();

        expect(shopIdChangeService.changeShopId.mock.calls[0][0].name).toBe(strategies[1].name);
        expect(reloadPage).toHaveBeenCalled();
    });

    it('shows why the shop identifier change failed', async () => {
        const createNotificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');
        shopIdChangeService.changeShopId.mockRejectedValueOnce({
            response: {
                data: {
                    errors: [{ detail: 'APP_URL "https://shop.test" could not be verified right now, try again later' }],
                },
            },
        });

        await strategyButtons().at(1).trigger('click');
        await wrapper.get('.mt-button--primary').trigger('click');
        await flushPromises();

        expect(createNotificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                message: 'APP_URL "https://shop.test" could not be verified right now, try again later',
                variant: 'error',
            }),
        );
    });

    it('asks for confirmation before keeping the shop identifier', async () => {
        await strategyButtons().at(0).trigger('click');
        await wrapper.get('.mt-button--primary').trigger('click');

        expect(shopIdChangeService.changeShopId).not.toHaveBeenCalled();

        wrapper.findComponent(stubs['sw-confirm-modal']).vm.$emit('confirm');
        await flushPromises();

        expect(shopIdChangeService.changeShopId.mock.calls[0][0].name).toBe('move-shop-permanently');
        expect(wrapper.find('.sw-confirm-modal').exists()).toBe(false);
    });

    it('keeps the shop identifier unchanged when the confirmation is cancelled', async () => {
        await strategyButtons().at(0).trigger('click');
        await wrapper.get('.mt-button--primary').trigger('click');

        wrapper.findComponent(stubs['sw-confirm-modal']).vm.$emit('cancel');
        await flushPromises();

        expect(shopIdChangeService.changeShopId).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-confirm-modal').exists()).toBe(false);
    });
});
