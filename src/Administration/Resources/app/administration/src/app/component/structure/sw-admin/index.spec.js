import 'src/app/component/structure/sw-admin';
import 'src/app/component/utils/sw-notifications';
import 'src/app/component/utils/sw-duplicated-media-v2';
import swSettingsCacheModal from 'src/module/sw-settings-cache/component/sw-settings-cache-modal';
import 'src/app/component/utils/sw-license-violation';
import 'src/app/component/structure/sw-hidden-iframes';
import 'src/app/component/structure/sw-modals-renderer';
import 'src/app/component/app/sw-app-wrong-app-url-modal';
import { shallowMount } from '@vue/test-utils';
import { BroadcastChannel } from 'worker_threads';

Shopware.Component.register('sw-settings-cache-modal', swSettingsCacheModal);
async function createWrapper(isLoggedIn, forwardLogout = () => {}, route = 'sw.wofoo.index') {
    return shallowMount(await Shopware.Component.build('sw-admin'), {
        stubs: {
            'sw-notifications': await Shopware.Component.build('sw-notifications'),
            'sw-duplicated-media-v2': await Shopware.Component.build('sw-duplicated-media-v2'),
            'sw-settings-cache-modal': await Shopware.Component.build('sw-settings-cache-modal'),
            'sw-license-violation': await Shopware.Component.build('sw-license-violation'),
            'sw-hidden-iframes': await Shopware.Component.build('sw-hidden-iframes'),
            'sw-modals-renderer': await Shopware.Component.build('sw-modals-renderer'),
            'sw-app-wrong-app-url-modal': await Shopware.Component.build('sw-app-wrong-app-url-modal'),
            'router-view': true,
        },
        mocks: {
            $router: {
                currentRoute: {
                    name: route,
                }
            }
        },
        provide: {
            cacheApiService: {},
            extensionStoreActionService: {},
            licenseViolationService: {},
            userActivityService: {
                updateLastUserActivity: () => {
                    localStorage.setItem('lastActivity', 'foo');
                }
            },
            loginService: {
                isLoggedIn: () => isLoggedIn,
                forwardLogout
            }
        },
        attachTo: document.body,
    });
}

describe('src/app/component/structure/sw-admin/index.ts', () => {
    let wrapper;

    beforeEach(() => {
        global.BroadcastChannel = BroadcastChannel;
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.destroy();
        }

        await flushPromises();

        localStorage.removeItem('lastActivity');
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper(false);

        expect(wrapper.vm).toBeTruthy();
    });

    it('should update user activity on click', async () => {
        wrapper = await createWrapper(false);

        const lastActivity = localStorage.getItem('lastActivity');

        const app = wrapper.find('#app');
        await app.trigger('mousemove');

        const newLastActivity = localStorage.getItem('lastActivity');

        expect(lastActivity).not.toBe(newLastActivity);
        expect(newLastActivity).toEqual('foo');
    });

    it('should handle session_channel message', async () => {
        const forwardLogout = jest.fn();
        wrapper = await createWrapper(false, forwardLogout);

        const channel = new BroadcastChannel('session_channel');
        channel.postMessage({
            inactive: true,
        });

        await flushPromises();

        expect(forwardLogout).toBeCalledTimes(1);
        expect(forwardLogout).toBeCalledWith(true, true);
        channel.close();
    });

    it('should not handle session_channel message with improper event data', async () => {
        const forwardLogout = jest.fn();
        wrapper = await createWrapper(false, forwardLogout);

        const channel = new BroadcastChannel('session_channel');
        channel.postMessage(null);
        channel.postMessage({});

        await flushPromises();

        expect(forwardLogout).toBeCalledTimes(0);
        channel.close();
    });

    it('should not handle session_channel message on blocked route', async () => {
        const forwardLogout = jest.fn();
        wrapper = await createWrapper(false, forwardLogout, 'sw.login.index.login');

        const channel = new BroadcastChannel('session_channel');
        channel.postMessage({
            inactive: true
        });

        await flushPromises();

        expect(forwardLogout).toBeCalledTimes(0);
        channel.close();
    });

    it('should not handle session_channel message on active', async () => {
        const forwardLogout = jest.fn();
        wrapper = await createWrapper(false, forwardLogout);

        const channel = new BroadcastChannel('session_channel');
        channel.postMessage({
            inactive: false
        });

        await flushPromises();

        expect(forwardLogout).toBeCalledTimes(0);
        channel.close();
    });
});
