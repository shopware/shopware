/**
 * @package admin
 */

import { createLocalVue, mount } from '@vue/test-utils';
import 'src/app/component/app/sw-app-wrong-app-url-modal';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-icon';

const STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN = 'sw-app-wrong-app-url-modal-shown';
let stubs = {};

describe('sw-app-wrong-app-url-modal', () => {
    let wrapper = null;
    const notificationMock = jest.fn();
    const deleteNotificationMock = jest.fn();

    async function createWrapper() {
        stubs = {
            'sw-modal': {
                template: '<div class="sw-modal"><slot name="modal-footer">Test</slot></div>',
            },
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-icon': await Shopware.Component.build('sw-icon'),
            'icons-small-default-x-line-medium': {
                template: '<span class="sw-icon sw-icon--small-default-x-line-medium"></span>',
            },
        };
        const localVue = createLocalVue();

        const modal = await Shopware.Component.build('sw-app-wrong-app-url-modal');

        modal.methods.createSystemNotificationInfo = notificationMock;

        return mount(modal, {
            localVue,
            stubs: {
                'sw-button': await Shopware.Component.build('sw-button'),
                'sw-icon': await Shopware.Component.build('sw-icon'),
                ...stubs,
            },
            provide: {
                shortcutService: {
                    startEventListener() {
                    },
                    stopEventListener() {
                    },
                },
            },
        });
    }

    beforeAll(() => {
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
            },
        });
        Shopware.State.unregisterModule('notification');

        Shopware.State.registerModule('notification', {
            namespaced: true,
            mutations: {
                removeNotification: deleteNotificationMock,
            },
        });
    });

    afterEach(() => {
        if (wrapper) {
            wrapper.destroy();
            wrapper = null;
        }
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
        expect(notificationMock).toHaveBeenCalledTimes(0);
        expect(deleteNotificationMock).toHaveBeenCalledTimes(0);
    });

    it('should not show modal if APP_URL is reachable', async () => {
        Shopware.State.get('context').app.config.settings.appUrlReachable = true;
        Shopware.State.get('context').app.config.settings.appsRequireAppUrl = true;
        localStorage.removeItem(STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN);

        wrapper = await createWrapper();

        const modal = wrapper.findComponent(stubs['sw-modal']);
        expect(modal.exists()).toBe(false);
        expect(notificationMock).toHaveBeenCalledTimes(0);
        expect(deleteNotificationMock).toHaveBeenCalledTimes(1);
    });

    it('should not show modal if no apps are require app url, but it should show notification', async () => {
        Shopware.State.get('context').app.config.settings.appUrlReachable = false;
        Shopware.State.get('context').app.config.settings.appsRequireAppUrl = false;
        localStorage.removeItem(STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN);

        wrapper = await createWrapper();

        const modal = wrapper.findComponent(stubs['sw-modal']);
        expect(modal.exists()).toBe(false);
        expect(notificationMock).toHaveBeenCalledTimes(1);
        expect(deleteNotificationMock).toHaveBeenCalledTimes(0);
    });

    it('should not show modal if it was shown, but it should show notification', async () => {
        Shopware.State.get('context').app.config.settings.appUrlReachable = false;
        Shopware.State.get('context').app.config.settings.appsRequireAppUrl = false;
        localStorage.setItem(STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN, true);

        wrapper = await createWrapper();

        const modal = wrapper.findComponent(stubs['sw-modal']);
        expect(modal.exists()).toBe(false);
        expect(notificationMock).toHaveBeenCalledTimes(1);
        expect(deleteNotificationMock).toHaveBeenCalledTimes(0);
    });

    it('should create notification and set localstorage on close', async () => {
        Shopware.State.get('context').app.config.settings.appUrlReachable = false;
        Shopware.State.get('context').app.config.settings.appsRequireAppUrl = true;
        localStorage.removeItem(STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN);

        wrapper = await createWrapper();

        const modal = wrapper.findComponent(stubs['sw-modal']);
        expect(modal.isVisible()).toBe(true);
        expect(notificationMock).toHaveBeenCalledTimes(0);

        modal.vm.$emit('modal-close');

        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(notificationMock).toHaveBeenCalledTimes(1);
        expect(deleteNotificationMock).toHaveBeenCalledTimes(0);
    });
});
