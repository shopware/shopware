/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

const originalPublicKeyCredential = window.PublicKeyCredential;
const originalCredentials = Object.getOwnPropertyDescriptor(window.navigator, 'credentials');

function mockWebAuthnSupport() {
    Object.defineProperty(window, 'PublicKeyCredential', {
        value: function PublicKeyCredential() {},
        configurable: true,
        writable: true,
    });
    Object.defineProperty(window.navigator, 'credentials', {
        value: { get: jest.fn() },
        configurable: true,
    });
}

function restoreWebAuthnSupport() {
    Object.defineProperty(window, 'PublicKeyCredential', {
        value: originalPublicKeyCredential,
        configurable: true,
        writable: true,
    });

    if (originalCredentials) {
        Object.defineProperty(window.navigator, 'credentials', originalCredentials);
    } else {
        Object.defineProperty(window.navigator, 'credentials', {
            value: undefined,
            configurable: true,
        });
    }
}

async function createWrapper({ methods = ['totp'], loginService = {} } = {}) {
    const loginServiceMock = {
        verifySecondFactor: jest.fn(() => Promise.resolve({})),
        verifySecondFactorWebauthn: jest.fn(() => Promise.resolve({})),
        clearPendingMfa: jest.fn(),
        ...loginService,
    };

    const wrapper = mount(await wrapTestComponent('sw-login-mfa', { sync: true }), {
        props: {
            methods,
        },
        global: {
            mocks: {
                $t: (...args: unknown[]) => JSON.stringify([...args]),
            },
            provide: {
                loginService: loginServiceMock,
            },
        },
    });

    await flushPromises();

    return { wrapper, loginServiceMock };
}

describe('module/sw-login/view/sw-login-mfa/sw-login-mfa.spec.ts', () => {
    afterEach(() => {
        restoreWebAuthnSupport();
    });

    it('should only accept a valid 6-digit TOTP code', async () => {
        const { wrapper } = await createWrapper();

        const verifyButton = wrapper.get('.sw-login-mfa__verify-action');
        expect(verifyButton.attributes('disabled')).toBeDefined();

        await wrapper.get('input[name="sw-field--mfa-code"]').setValue('12345');
        expect(wrapper.vm.codeValid).toBe(false);

        await wrapper.get('input[name="sw-field--mfa-code"]').setValue('12a456');
        expect(wrapper.vm.codeValid).toBe(false);

        await wrapper.get('input[name="sw-field--mfa-code"]').setValue('123456');
        expect(wrapper.vm.codeValid).toBe(true);
        expect(wrapper.get('.sw-login-mfa__verify-action').attributes('disabled')).toBeUndefined();
    });

    it('should not send an invalid code', async () => {
        const { wrapper, loginServiceMock } = await createWrapper();

        await wrapper.get('input[name="sw-field--mfa-code"]').setValue('12345');
        await (wrapper.vm as unknown as { verifyCode(): Promise<void> }).verifyCode();

        expect(loginServiceMock.verifySecondFactor).not.toHaveBeenCalled();
    });

    it('should verify a TOTP code and emit mfa-success', async () => {
        const { wrapper, loginServiceMock } = await createWrapper();

        await wrapper.get('input[name="sw-field--mfa-code"]').setValue('123456');
        await wrapper.get('.sw-login-mfa__verify-action').trigger('click');
        await flushPromises();

        expect(loginServiceMock.verifySecondFactor).toHaveBeenCalledWith('totp', '123456');
        expect(wrapper.emitted('mfa-success')).toHaveLength(1);
        expect(wrapper.emitted('is-loading')).toHaveLength(1);
        expect(wrapper.emitted('is-not-loading')).toHaveLength(1);
    });

    it('should toggle to the recovery code input and verify with the recovery_codes method', async () => {
        const { wrapper, loginServiceMock } = await createWrapper({
            methods: [
                'totp',
                'recovery_codes',
            ],
        });

        await wrapper.get('.sw-login-mfa__recovery-toggle').trigger('click');

        expect(wrapper.find('input[name="sw-field--mfa-code"]').exists()).toBe(false);

        await wrapper.get('input[name="sw-field--mfa-recovery-code"]').setValue('ABCD-EFGH');
        expect(wrapper.vm.codeValid).toBe(true);

        await wrapper.get('.sw-login-mfa__verify-action').trigger('click');
        await flushPromises();

        expect(loginServiceMock.verifySecondFactor).toHaveBeenCalledWith('recovery_codes', 'ABCD-EFGH');
        expect(wrapper.emitted('mfa-success')).toHaveLength(1);
    });

    it('should not offer the recovery toggle when recovery codes are not available', async () => {
        const { wrapper } = await createWrapper({ methods: ['totp'] });

        expect(wrapper.find('.sw-login-mfa__recovery-toggle').exists()).toBe(false);
    });

    it('should default to the recovery code input when TOTP is not available', async () => {
        const { wrapper } = await createWrapper({ methods: ['recovery_codes'] });

        expect(wrapper.find('input[name="sw-field--mfa-code"]').exists()).toBe(false);
        expect(wrapper.find('input[name="sw-field--mfa-recovery-code"]').exists()).toBe(true);
        expect(wrapper.find('.sw-login-mfa__recovery-toggle').exists()).toBe(false);
    });

    it('should stay on the step and show a notification when the code is rejected', async () => {
        const { wrapper, loginServiceMock } = await createWrapper({
            loginService: {
                verifySecondFactor: jest.fn(() => Promise.reject({ response: { status: 400 } })),
            },
        });

        const notificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');

        await wrapper.get('input[name="sw-field--mfa-code"]').setValue('123456');
        await wrapper.get('.sw-login-mfa__verify-action').trigger('click');
        await flushPromises();

        expect(loginServiceMock.verifySecondFactor).toHaveBeenCalledWith('totp', '123456');
        expect(wrapper.emitted('mfa-success')).toBeUndefined();
        expect(notificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: JSON.stringify(['sw-login.mfa.invalidCode']),
            }),
        );

        // the code is cleared, the input stays visible for another attempt
        expect(wrapper.vm.code).toBe('');
        expect(wrapper.find('input[name="sw-field--mfa-code"]').exists()).toBe(true);

        notificationSpy.mockRestore();
    });

    it('should emit mfa-cancel and reset its state on cancel', async () => {
        const { wrapper } = await createWrapper();

        await wrapper.get('input[name="sw-field--mfa-code"]').setValue('123456');
        await wrapper.get('.sw-login-mfa__cancel-action').trigger('click');

        expect(wrapper.emitted('mfa-cancel')).toHaveLength(1);
        expect(wrapper.vm.code).toBe('');
    });

    it('should only show the passkey button when webauthn is allowed and supported', async () => {
        const { wrapper: unsupportedWrapper } = await createWrapper({
            methods: [
                'totp',
                'webauthn',
            ],
        });
        expect(unsupportedWrapper.find('.sw-login-mfa__passkey-action').exists()).toBe(false);

        mockWebAuthnSupport();

        const { wrapper } = await createWrapper({
            methods: [
                'totp',
                'webauthn',
            ],
        });
        expect(wrapper.find('.sw-login-mfa__passkey-action').exists()).toBe(true);

        const { wrapper: noWebauthnWrapper } = await createWrapper({ methods: ['totp'] });
        expect(noWebauthnWrapper.find('.sw-login-mfa__passkey-action').exists()).toBe(false);
    });

    it('should verify with a passkey and emit mfa-success', async () => {
        mockWebAuthnSupport();

        const { wrapper, loginServiceMock } = await createWrapper({
            methods: [
                'totp',
                'webauthn',
            ],
        });

        await wrapper.get('.sw-login-mfa__passkey-action').trigger('click');
        await flushPromises();

        expect(loginServiceMock.verifySecondFactorWebauthn).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('mfa-success')).toHaveLength(1);
    });

    it('should surface a ceremony error message when the passkey verification fails', async () => {
        mockWebAuthnSupport();

        const { wrapper } = await createWrapper({
            methods: [
                'totp',
                'webauthn',
            ],
            loginService: {
                verifySecondFactorWebauthn: jest.fn(() => Promise.reject(new Error('The passkey request was cancelled.'))),
            },
        });

        const notificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');

        await wrapper.get('.sw-login-mfa__passkey-action').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('mfa-success')).toBeUndefined();
        expect(notificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: 'The passkey request was cancelled.',
            }),
        );

        notificationSpy.mockRestore();
    });
});
