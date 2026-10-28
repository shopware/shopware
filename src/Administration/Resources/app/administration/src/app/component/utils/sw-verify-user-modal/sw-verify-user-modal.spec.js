/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

const loginService = {
    verifyUserToken: jest.fn(() => Promise.resolve('verified-token')),
    getBearerAuthentication: jest.fn(() => ({ access: 'old-token' })),
    setBearerAuthentication: jest.fn(),
};

async function createWrapper(props = {}) {
    const wrapper = mount(await wrapTestComponent('sw-verify-user-modal', { sync: true }), {
        props,
        global: {
            provide: { loginService },
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

        expect(wrapper.find('.sw-modal').exists()).toBe(true);
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

    it('offers the identity provider when the shop has one', async () => {
        wrapper = await createWrapper({ ssoAvailable: true });

        await wrapper.find('.sw-verify-user-modal__verify-with-sso').trigger('click');

        expect(wrapper.emitted('verify-with-sso')).toHaveLength(1);
        expect(wrapper.emitted('close')).toBeUndefined();
        expect(loginService.verifyUserToken).not.toHaveBeenCalled();
    });
});
