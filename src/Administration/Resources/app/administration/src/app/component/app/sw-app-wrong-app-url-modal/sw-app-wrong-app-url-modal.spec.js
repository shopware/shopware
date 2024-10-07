/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

const STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN = 'sw-app-wrong-app-url-modal-shown';
let stubs = {};

describe('sw-app-wrong-app-url-modal', () => {
    let wrapper = null;
    const deleteNotificationMock = jest.fn();

    async function createWrapper() {
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
                    </div>
                `,
            },
            'sw-button': await wrapTestComponent('sw-button'),
            'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
            'sw-icon': await wrapTestComponent('sw-icon'),
            'icons-small-default-x-line-medium': {
                template: '<span class="sw-icon sw-icon--small-default-x-line-medium"></span>',
            },
            'router-link': true,
            'sw-loader': true,
        };

        return mount(
            await wrapTestComponent('sw-app-wrong-app-url-modal', {
                sync: true,
            }),
            {
                global: {
                    stubs: {
                        'sw-button': await wrapTestComponent('sw-button'),
                        'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                        'sw-icon': await wrapTestComponent('sw-icon'),
                        ...stubs,
                    },
                    provide: {
                        shortcutService: {
                            startEventListener() {},
                            stopEventListener() {},
                        },
                    },
                },
            },
        );
    }

    beforeAll(() => {
        if (Shopware.State.get('context')) {
            Shopware.State.unregisterModule('context');
        }

        Shopware.State.registerModule('context', {
            namespaced: true,
            state: {
                app: {
                    config: {
                        settings: {
                            appUrlReachable: true,
                            appsRequireAppUrl: false,
                        },
                    },
                },
                api: {
                    assetPath: 'http://localhost:8000/bundles/administration/',
                },
            },
        });
        Shopware.State.unregisterModule('notification');

        Shopware.State.registerModule('notification', {
            namespaced: true,
            mutations: {
                removeNotification: deleteNotificationMock,
            },
            actions: {
                createNotification: jest.fn(),
            },
        });
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should show modal', async () => {
        Shopware.State.get('context').app.config.settings.appUrlReachable = false;
        Shopware.State.get('context').app.config.settings.appsRequireAppUrl = true;
        localStorage.removeItem(STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN);

        wrapper = await createWrapper();

        const modal = wrapper.findComponent(stubs['sw-modal']);
        expect(modal.isVisible()).toBe(true);
        expect(deleteNotificationMock).toHaveBeenCalledTimes(0);
    });

    it('should not show modal if APP_URL is reachable', async () => {
        Shopware.State.get('context').app.config.settings.appUrlReachable = true;
        Shopware.State.get('context').app.config.settings.appsRequireAppUrl = true;
        localStorage.removeItem(STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN);

        wrapper = await createWrapper();

        const modal = wrapper.findComponent(stubs['sw-modal']);
        expect(modal.exists()).toBe(false);
        expect(deleteNotificationMock).toHaveBeenCalledTimes(1);
    });

    it('should not show modal if no apps are require app url, but it should show notification', async () => {
        Shopware.State.get('context').app.config.settings.appUrlReachable = false;
        Shopware.State.get('context').app.config.settings.appsRequireAppUrl = false;
        localStorage.removeItem(STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN);

        wrapper = await createWrapper();

        const modal = wrapper.findComponent(stubs['sw-modal']);
        expect(modal.exists()).toBe(false);
        expect(deleteNotificationMock).toHaveBeenCalledTimes(0);
    });

    it('should not show modal if it was shown, but it should show notification', async () => {
        Shopware.State.get('context').app.config.settings.appUrlReachable = false;
        Shopware.State.get('context').app.config.settings.appsRequireAppUrl = false;
        localStorage.setItem(STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN, true);

        wrapper = await createWrapper();

        const modal = wrapper.findComponent(stubs['sw-modal']);
        expect(modal.exists()).toBe(false);
        expect(deleteNotificationMock).toHaveBeenCalledTimes(0);
    });

    it('should create notification and set localstorage on close', async () => {
        Shopware.State.get('context').app.config.settings.appUrlReachable = false;
        Shopware.State.get('context').app.config.settings.appsRequireAppUrl = true;
        localStorage.removeItem(STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN);

        wrapper = await createWrapper();

        const modal = wrapper.findComponent(stubs['sw-modal']);
        expect(modal.isVisible()).toBe(true);

        modal.vm.$emit('modal-close');

        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(deleteNotificationMock).toHaveBeenCalledTimes(0);
    });

    it('should return filters from filter registry', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
    });
});
