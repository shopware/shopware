/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper({ isSso = false, isSsoRejects = false } = {}) {
    const loginService = {
        verifyUserToken: jest.fn(() => Promise.resolve('verified-token')),
        getBearerAuthentication: jest.fn(() => ({ access: 'old-token' })),
        setBearerAuthentication: jest.fn(),
    };
    const ssoSettingsService = {
        isSso: jest.fn(() => (isSsoRejects ? Promise.reject(new Error('offline')) : Promise.resolve({ isSso }))),
    };

    const wrapper = mount(await wrapTestComponent('sw-verify-user-modal', { sync: true }), {
        global: {
            provide: {
                loginService,
                ssoSettingsService,
            },
        },
    });
    await flushPromises();

    return { wrapper, loginService, ssoSettingsService };
}

describe('src/app/component/utils/sw-verify-user-modal', () => {
    let wrapper;

    afterEach(() => {
        wrapper?.unmount();
    });

    it('asks for the password and emits the verified context for a non-SSO session', async () => {
        let loginService;
        ({ wrapper, loginService } = await createWrapper());

        expect(wrapper.find('.sw-modal').exists()).toBe(true);
        expect(wrapper.emitted('verified')).toBeUndefined();

        await wrapper.find('.sw-settings-user-detail__confirm-password input').setValue('secret');
        await wrapper.find('.mt-button--primary').trigger('click');
        await flushPromises();

        expect(loginService.verifyUserToken).toHaveBeenCalledWith('secret');
        expect(loginService.setBearerAuthentication).toHaveBeenCalledWith({ access: 'verified-token' });
        expect(wrapper.emitted('verified')[0][0].authToken.access).toBe('verified-token');
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('skips the password prompt and emits the current context for an SSO session', async () => {
        let loginService;
        ({ wrapper, loginService } = await createWrapper({ isSso: true }));

        expect(wrapper.find('.sw-modal').exists()).toBe(false);
        expect(loginService.verifyUserToken).not.toHaveBeenCalled();
        expect(wrapper.emitted('verified')).toHaveLength(1);
        expect(wrapper.emitted('verified')[0][0]).toEqual({ ...Shopware.Context.api });
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('falls back to the password prompt when the SSO lookup fails', async () => {
        ({ wrapper } = await createWrapper({ isSsoRejects: true }));

        expect(wrapper.find('.sw-modal').exists()).toBe(true);
        expect(wrapper.emitted('verified')).toBeUndefined();
        expect(wrapper.emitted('close')).toBeUndefined();
    });
});
