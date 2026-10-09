/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

const loginService = {
    verifyUserToken: jest.fn(() => Promise.resolve('verified-token')),
    getBearerAuthentication: jest.fn(() => ({ access: 'old-token' })),
    setBearerAuthentication: jest.fn(),
};

const ssoSettingsService = {
    isSso: jest.fn(() => Promise.resolve({ isSso: false })),
};

async function createWrapper() {
    const wrapper = mount(await wrapTestComponent('sw-verify-user-modal', { sync: true }), {
        global: {
            provide: {
                loginService,
                ssoSettingsService,
            },
        },
    });
    await flushPromises();

    return wrapper;
}

describe('src/app/component/utils/sw-verify-user-modal', () => {
    let wrapper;

    afterEach(() => {
        wrapper?.unmount();
        jest.clearAllMocks();
    });

    it('asks for the password and emits the verified context for a non-SSO session', async () => {
        wrapper = await createWrapper();

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
        ssoSettingsService.isSso.mockResolvedValueOnce({ isSso: true });

        wrapper = await createWrapper();

        expect(wrapper.find('.sw-modal').exists()).toBe(false);
        expect(loginService.verifyUserToken).not.toHaveBeenCalled();
        expect(wrapper.emitted('verified')).toHaveLength(1);
        expect(wrapper.emitted('verified')[0][0]).toEqual({ ...Shopware.Context.api });
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('falls back to the password prompt when the SSO lookup fails', async () => {
        ssoSettingsService.isSso.mockRejectedValueOnce(new Error('offline'));

        wrapper = await createWrapper();

        expect(wrapper.find('.sw-modal').exists()).toBe(true);
        expect(wrapper.emitted('verified')).toBeUndefined();
        expect(wrapper.emitted('close')).toBeUndefined();
    });
});
