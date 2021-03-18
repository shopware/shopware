import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vue from 'vue';
import extensionStore from 'src/module/sw-extension/store/extensions.store';
import ShopwareExtensionService from 'src/module/sw-extension/service/shopware-extension.service';

let isLoggedIn = false;

function createWrapper() {
    const localVue = createLocalVue();
    localVue.filter('asset', key => key);

    return shallowMount(Shopware.Component.build('sw-extension-my-extensions-account'), {
        localVue,
        propsData: {},
        mocks: {
            $tc: v => v
        },
        stubs: {
            'sw-avatar': true,
            'sw-loader': true,
            'sw-meteor-card': Shopware.Component.build('sw-meteor-card'),
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-text-field': {
                props: ['value'],
                template: `
                    <input type="text" :value="value" @input="$emit('input', $event.target.value)" />
                `
            },
            'sw-password-field': {
                props: ['value'],
                template: `
<input type="password" :value="value" @input="$emit('input', $event.target.value)" />
`
            }
        },
        provide: {
            shopwareExtensionService: new ShopwareExtensionService(),
            systemConfigApiService: {
                getValues: () => {
                    return Promise.resolve({
                        'core.store.apiUri': 'https://api.shopware.com',
                        'core.store.licenseHost': 'sw6.test.shopware.in',
                        'core.store.shopSecret': 'very.s3cret',
                        'core.store.shopwareId': 'max@muster.com'
                    });
                }
            }
        }
    });
}

describe('src/module/sw-extension/page/sw-extension-my-extensions-account', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(async () => {
        Shopware.Application.view = {
            setReactive: Vue.set
        };

        Shopware.State.registerModule('shopwareExtensions', extensionStore);
        Shopware.Service().register('storeService', () => {
            return {
                login: (shopwareId, password) => {
                    if (shopwareId !== 'max@muster.com') {
                        return Promise.reject();
                    }
                    if (password !== 'v3ryS3cret') {
                        return Promise.reject();
                    }

                    isLoggedIn = true;

                    return Promise.resolve();
                },
                logout: () => {
                    isLoggedIn = false;

                    return Promise.resolve();
                },
                checkLogin() {
                    return Promise.resolve({
                        storeTokenExists: isLoggedIn
                    });
                }
            };
        });

        Shopware.Feature.init({
            FEATURE_NEXT_12608: true
        });

        await import('src/module/sw-extension/page/sw-extension-my-extensions-account');
        await import('src/app/component/meteor/sw-meteor-card');
        await import('src/app/component/base/sw-button');
    });

    beforeEach(async () => {
        isLoggedIn = false;
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the login fields when not logged in', async () => {
        const shopwareIdField = wrapper.find('.sw-extension-my-extensions-account__shopware-id-field');
        const passwordField = wrapper.find('.sw-extension-my-extensions-account__password-field');
        const loginButton = wrapper.find('.sw-extension-my-extensions-account__login-button');

        // check if fields exists when user is not logged in
        expect(shopwareIdField.isVisible()).toBe(true);
        expect(passwordField.isVisible()).toBe(true);
        expect(loginButton.isVisible()).toBe(true);
    });

    it('should login when user clicks login', async () => {
        // check if login status is not visible
        let loginStatus = wrapper.find('.sw-extension-my-extensions-account__wrapper-content-login-status-id');
        expect(loginStatus.exists()).toBe(false);

        // get fields
        const shopwareIdField = wrapper.find('.sw-extension-my-extensions-account__shopware-id-field');
        const passwordField = wrapper.find('.sw-extension-my-extensions-account__password-field');
        const loginButton = wrapper.find('.sw-extension-my-extensions-account__login-button');

        // enter credentials
        await shopwareIdField.setValue('max@muster.com');
        await passwordField.setValue('v3ryS3cret');

        // login
        await loginButton.trigger('click');
        await wrapper.vm.$nextTick();

        // check if layout switches
        loginStatus = wrapper.find('.sw-extension-my-extensions-account__wrapper-content-login-status-id');
        expect(loginStatus.exists()).toBe(true);
        expect(loginStatus.text()).toBe('max@muster.com');
    });

    it('should show the logged in view when logged in ', async () => {
        // set logged in to true
        isLoggedIn = true;

        // create component with logged in view
        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // check if layout shows the logged in information
        const loginStatus = wrapper.find('.sw-extension-my-extensions-account__wrapper-content-login-status-id');
        expect(loginStatus.exists()).toBe(true);
        expect(loginStatus.text()).toBe('max@muster.com');
    });

    it('should logout when user clicks logout button ', async () => {
        // set logged in to true
        isLoggedIn = true;

        // create component with logged in view
        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // check if logout button exists
        let logoutButton = wrapper.find('.sw-extension-my-extensions-account__logout-button');
        expect(logoutButton.exists()).toBe(true);

        // click on logout
        await logoutButton.trigger('click');

        // check if logout button disappears
        logoutButton = wrapper.find('.sw-extension-my-extensions-account__logout-button');
        expect(logoutButton.exists()).toBe(false);

        // check if user is sees login view
        const loginButton = wrapper.find('.sw-extension-my-extensions-account__login-button');
        expect(loginButton.exists()).toBe(true);
    });
});
