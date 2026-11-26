/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

const loginService = {
    verifyUserToken: jest.fn(() => Promise.resolve('verified-token')),
    getBearerAuthentication: jest.fn(() => ({ access: 'old-token' })),
    setBearerAuthentication: jest.fn(),
};

const ssoContext = { authToken: { access: 'sso-verified-token' } };
const userVerificationService = {
    getSsoOptions: jest.fn(() => Promise.resolve({ ssoSession: false, loginUrl: '' })),
    verifyWithSso: jest.fn(() => Promise.resolve(ssoContext)),
};

async function createWrapper(ssoOptions = { ssoSession: false, loginUrl: '' }) {
    userVerificationService.getSsoOptions.mockResolvedValueOnce(ssoOptions);

    const wrapper = mount(await wrapTestComponent('sw-verify-user-modal', { sync: true }), {
        global: {
            provide: { loginService, userVerificationService },
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

    it('asks for the password and emits the verified context', async () => {
        wrapper = await createWrapper();

        expect(wrapper.find('.sw-modal').attributes('title')).toBe(
            'sw-users-permissions.users.user-detail.passwordConfirmation.titleConfirmPasswordModal',
        );
        expect(wrapper.find('.sw-verify-user-modal__sso-text').exists()).toBe(false);
        expect(wrapper.find('.sw-verify-user-modal__verify-with-sso').exists()).toBe(false);

        await wrapper.find('.sw-settings-user-detail__confirm-password input').setValue('secret');
        await wrapper.find('.mt-button--primary').trigger('click');
        await flushPromises();

        expect(loginService.verifyUserToken).toHaveBeenCalledWith('secret');
        expect(loginService.setBearerAuthentication).toHaveBeenCalledWith({ access: 'verified-token' });
        expect(wrapper.emitted('verified')[0][0].authToken.access).toBe('verified-token');
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('shows the password error and closes when the password is wrong', async () => {
        loginService.verifyUserToken.mockRejectedValueOnce(new Error('wrong password'));
        wrapper = await createWrapper();
        const createNotification = jest
            .spyOn(Shopware.Store.get('notification'), 'createNotification')
            .mockResolvedValue(undefined);

        await wrapper.find('.sw-settings-user-detail__confirm-password input').setValue('wrong');
        await wrapper.find('.mt-button--primary').trigger('click');
        await flushPromises();

        expect(createNotification).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: 'sw-users-permissions.users.user-detail.passwordConfirmation.notificationPasswordErrorMessage',
            }),
        );
        expect(wrapper.emitted('verified')).toBeUndefined();
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('emits close without a verification when the user cancels', async () => {
        wrapper = await createWrapper();

        await wrapper.find('.sw-settings-user-detail__confirm-password input').setValue('secret');
        await wrapper.find('.mt-button--secondary').trigger('click');

        expect(loginService.verifyUserToken).not.toHaveBeenCalled();
        expect(wrapper.emitted('verified')).toBeUndefined();
        expect(wrapper.emitted('close')).toHaveLength(1);
        expect(wrapper.find('.sw-settings-user-detail__confirm-password input').element.value).toBe('');
    });

    it('offers the identity provider next to the password when the shop has one', async () => {
        wrapper = await createWrapper({ ssoSession: false, loginUrl: 'https://shop.test/sso' });

        expect(wrapper.find('.sw-settings-user-detail__confirm-password').exists()).toBe(true);
        expect(wrapper.find('.sw-verify-user-modal__verify-with-sso').classes()).toContain('mt-button--secondary');
    });

    it('offers only the identity provider on an SSO session', async () => {
        wrapper = await createWrapper({ ssoSession: true, loginUrl: 'https://shop.test/sso' });

        expect(wrapper.find('.sw-modal').attributes('title')).toBe('global.sw-verify-user-modal.titleVerifyWithSso');
        expect(wrapper.find('.sw-verify-user-modal__sso-text').text()).toBe('global.sw-verify-user-modal.textVerifyWithSso');
        expect(wrapper.find('.sw-settings-user-detail__confirm-password').exists()).toBe(false);
        expect(wrapper.find('.sw-verify-user-modal__verify-with-sso').classes()).toContain('mt-button--primary');
        expect(wrapper.findAll('.mt-button--primary')).toHaveLength(1);
    });

    it('emits the verified context after the identity provider login', async () => {
        wrapper = await createWrapper({ ssoSession: true, loginUrl: 'https://shop.test/sso' });

        await wrapper.find('.sw-verify-user-modal__verify-with-sso').trigger('click');
        // the window opens within the click, before any awaited request
        expect(userVerificationService.verifyWithSso).toHaveBeenCalledWith('https://shop.test/sso');
        await flushPromises();

        expect(wrapper.emitted('verified')).toEqual([[ssoContext]]);
        expect(wrapper.emitted('close')).toHaveLength(1);
        expect(loginService.verifyUserToken).not.toHaveBeenCalled();
    });

    it('ignores a second click while the identity provider window is open', async () => {
        let finishLogin;
        userVerificationService.verifyWithSso.mockReturnValueOnce(
            new Promise((resolve) => {
                finishLogin = resolve;
            }),
        );
        wrapper = await createWrapper({ ssoSession: true, loginUrl: 'https://shop.test/sso' });

        const button = wrapper.find('.sw-verify-user-modal__verify-with-sso');
        await button.trigger('click');
        await button.trigger('click');

        expect(userVerificationService.verifyWithSso).toHaveBeenCalledTimes(1);
        expect(button.attributes('disabled')).toBeDefined();

        finishLogin(ssoContext);
        await flushPromises();

        expect(wrapper.emitted('verified')).toEqual([[ssoContext]]);
        expect(button.attributes('disabled')).toBeUndefined();
    });

    it('stays open for another try when the identity provider login fails', async () => {
        userVerificationService.verifyWithSso.mockRejectedValueOnce(new Error('cancelled'));
        wrapper = await createWrapper({ ssoSession: true, loginUrl: 'https://shop.test/sso' });

        await wrapper.find('.sw-verify-user-modal__verify-with-sso').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('verified')).toBeUndefined();
        expect(wrapper.emitted('close')).toBeUndefined();
    });
});
