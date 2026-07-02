/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import type { MfaMethod } from 'src/core/service/api/admin-auth.service';

const originalPublicKeyCredential = window.PublicKeyCredential;
const originalCredentials = Object.getOwnPropertyDescriptor(window.navigator, 'credentials');

function mockWebAuthnSupport(create = jest.fn()) {
    Object.defineProperty(window, 'PublicKeyCredential', {
        value: function PublicKeyCredential() {},
        configurable: true,
        writable: true,
    });
    Object.defineProperty(window.navigator, 'credentials', {
        value: { create },
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

interface SecurityVm {
    enrollment: { id: string } | null;
    recoveryCodes: string[] | null;
    recoverySaved: boolean;
    verifiedToken: string | null;
    onVerified(context: { authToken?: { access?: string } }): void;
    onStartEnroll(): void;
    onSubmitConfirm(): void;
    doEnroll(): Promise<void>;
}

const totpMethod: MfaMethod = {
    id: 'a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4',
    type: 'totp',
    active: true,
    label: 'My phone',
    lastUsedAt: null,
};

async function createWrapper({ methods = [totpMethod], adminAuthService = {} } = {}) {
    const adminAuthServiceMock = {
        getMfaMethods: jest.fn(() => Promise.resolve(methods)),
        totpRegisterOptions: jest.fn(() =>
            Promise.resolve({
                id: 'enrollment-id',
                secret: 'JBSWY3DPEHPK3PXP',
                otpauthUri: 'otpauth://totp/Shopware%20Admin?secret=JBSWY3DPEHPK3PXP',
            }),
        ),
        totpRegisterVerify: jest.fn(() => Promise.resolve({ success: true })),
        webauthnRegisterOptions: jest.fn(() =>
            Promise.resolve({
                options: {
                    challenge: 'Y2hhbGxlbmdl',
                    user: { id: 'dXNlci1pZA' },
                },
                challengeToken: 'signed-challenge-token',
            }),
        ),
        webauthnRegisterVerify: jest.fn(() => Promise.resolve({ id: 'method-id', success: true })),
        generateRecoveryCodes: jest.fn(() =>
            Promise.resolve([
                'AAAA-BBBB',
                'CCCC-DDDD',
            ]),
        ),
        deleteMfaMethod: jest.fn(() => Promise.resolve()),
        ...adminAuthService,
    };

    const wrapper = mount(await wrapTestComponent('sw-profile-index-security', { sync: true }), {
        global: {
            provide: {
                adminAuthService: adminAuthServiceMock,
            },
            stubs: {
                'sw-verify-user-modal': {
                    template: '<div class="sw-verify-user-modal-stub"></div>',
                },
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                },
            },
        },
    });

    await flushPromises();

    const vm = wrapper.vm as unknown as SecurityVm;

    return { wrapper, vm, adminAuthServiceMock };
}

describe('module/sw-profile/view/sw-profile-index-security', () => {
    afterEach(() => {
        restoreWebAuthnSupport();
    });

    it('loads and renders the enrolled methods without re-verification', async () => {
        const { wrapper, adminAuthServiceMock } = await createWrapper();

        expect(adminAuthServiceMock.getMfaMethods).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.sw-profile-index-security__method-label').text()).toBe('My phone');
        expect(wrapper.find('.sw-verify-user-modal-stub').exists()).toBe(false);
    });

    it('shows an empty state when no method is enrolled', async () => {
        const { wrapper } = await createWrapper({ methods: [] });

        expect(wrapper.find('.sw-profile-index-security__empty-state').exists()).toBe(true);
    });

    it('requires identity verification before starting a TOTP enrollment', async () => {
        const { wrapper, vm, adminAuthServiceMock } = await createWrapper();

        await wrapper.get('.sw-profile-index-security__add-totp').trigger('click');
        await wrapper.get('.sw-profile-index-security__enroll-generate').trigger('click');
        await flushPromises();

        // No verified token yet: the modal opens and nothing is sent.
        expect(wrapper.find('.sw-verify-user-modal-stub').exists()).toBe(true);
        expect(adminAuthServiceMock.totpRegisterOptions).not.toHaveBeenCalled();

        vm.onVerified({ authToken: { access: 'verified-token' } });
        await flushPromises();

        expect(adminAuthServiceMock.totpRegisterOptions).toHaveBeenCalledWith(expect.any(String), {
            Authorization: 'Bearer verified-token',
        });

        // The QR code is rendered locally from the otpauth URI.
        const qrImage = wrapper.get('.sw-profile-index-security__qr-image');
        expect(qrImage.attributes('src')).toContain('data:image/svg+xml');
        expect(wrapper.get('.sw-profile-index-security__secret-value').text()).toBe('JBSWY3DPEHPK3PXP');
    });

    it('confirms a TOTP enrollment with a valid code and reloads the methods', async () => {
        const { wrapper, vm, adminAuthServiceMock } = await createWrapper();

        vm.onVerified({ authToken: { access: 'verified-token' } });
        vm.onStartEnroll();
        await vm.doEnroll();
        await flushPromises();

        await wrapper.get('.sw-profile-index-security__confirm-code input').setValue('123456');

        const confirmButton = wrapper.get('.sw-profile-index-security__confirm-button');
        expect(confirmButton.attributes('disabled')).toBeUndefined();

        await confirmButton.trigger('click');
        await flushPromises();

        expect(adminAuthServiceMock.totpRegisterVerify).toHaveBeenCalledWith('enrollment-id', '123456', {
            Authorization: 'Bearer verified-token',
        });
        expect(adminAuthServiceMock.getMfaMethods).toHaveBeenCalledTimes(2);
        expect(vm.enrollment).toBeNull();
    });

    it('rejects an invalid confirmation code client-side', async () => {
        const { wrapper, vm, adminAuthServiceMock } = await createWrapper();

        vm.onVerified({ authToken: { access: 'verified-token' } });
        vm.onStartEnroll();
        await vm.doEnroll();
        await flushPromises();

        await wrapper.get('.sw-profile-index-security__confirm-code input').setValue('12345');
        vm.onSubmitConfirm();
        await flushPromises();

        expect(adminAuthServiceMock.totpRegisterVerify).not.toHaveBeenCalled();
    });

    it('hides the passkey button when the browser does not support WebAuthn', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.sw-profile-index-security__add-passkey').exists()).toBe(false);
    });

    it('registers a passkey through the WebAuthn ceremony', async () => {
        const create = jest.fn(() =>
            Promise.resolve({
                id: 'credential-id',
                rawId: new Uint8Array([
                    1,
                    2,
                    3,
                ]).buffer,
                type: 'public-key',
                response: {
                    attestationObject: new Uint8Array([4]).buffer,
                    clientDataJSON: new Uint8Array([5]).buffer,
                },
            }),
        );
        mockWebAuthnSupport(create);

        const { wrapper, vm, adminAuthServiceMock } = await createWrapper();

        vm.onVerified({ authToken: { access: 'verified-token' } });

        await wrapper.get('.sw-profile-index-security__add-passkey').trigger('click');
        await flushPromises();

        expect(adminAuthServiceMock.webauthnRegisterOptions).toHaveBeenCalledWith({
            Authorization: 'Bearer verified-token',
        });
        expect(create).toHaveBeenCalledTimes(1);
        expect(adminAuthServiceMock.webauthnRegisterVerify).toHaveBeenCalledWith(
            expect.objectContaining({
                credential: expect.stringContaining('"id":"credential-id"') as unknown as string,
                challengeToken: 'signed-challenge-token',
            }),
            { Authorization: 'Bearer verified-token' },
        );
        expect(adminAuthServiceMock.getMfaMethods).toHaveBeenCalledTimes(2);
    });

    it('shows the generated recovery codes exactly once and requires a saved confirmation', async () => {
        const { wrapper, vm, adminAuthServiceMock } = await createWrapper();

        vm.onVerified({ authToken: { access: 'verified-token' } });

        await wrapper.get('.sw-profile-index-security__generate-recovery').trigger('click');
        await flushPromises();

        expect(adminAuthServiceMock.generateRecoveryCodes).toHaveBeenCalledWith({
            Authorization: 'Bearer verified-token',
        });

        const codes = wrapper.findAll('.sw-profile-index-security__recovery-code');
        expect(codes).toHaveLength(2);
        expect(codes[0].text()).toBe('AAAA-BBBB');

        // "Done" is blocked until the user confirms having saved the codes.
        expect(wrapper.get('.sw-profile-index-security__recovery-done').attributes('disabled')).toBeDefined();

        vm.recoverySaved = true;
        await flushPromises();
        await wrapper.get('.sw-profile-index-security__recovery-done').trigger('click');
        await flushPromises();

        expect(vm.recoveryCodes).toBeNull();
        expect(wrapper.findAll('.sw-profile-index-security__recovery-code')).toHaveLength(0);
    });

    it('deletes a method only after the confirm modal and re-verification', async () => {
        const { wrapper, vm, adminAuthServiceMock } = await createWrapper();

        await wrapper.get('.sw-profile-index-security__method-delete').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-profile-index-security__delete-modal').exists()).toBe(true);
        expect(adminAuthServiceMock.deleteMfaMethod).not.toHaveBeenCalled();

        await wrapper.get('.sw-profile-index-security__delete-confirm').trigger('click');
        await flushPromises();

        // Still gated behind the identity verification.
        expect(adminAuthServiceMock.deleteMfaMethod).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-verify-user-modal-stub').exists()).toBe(true);

        vm.onVerified({ authToken: { access: 'verified-token' } });
        await flushPromises();

        expect(adminAuthServiceMock.deleteMfaMethod).toHaveBeenCalledWith(totpMethod.id, {
            Authorization: 'Bearer verified-token',
        });
    });

    it('drops the verified token and asks to re-verify on a 403 response', async () => {
        const { vm, adminAuthServiceMock } = await createWrapper({
            adminAuthService: {
                totpRegisterOptions: jest.fn(() => Promise.reject({ response: { status: 403 } })),
            },
        });

        const notificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');

        vm.onVerified({ authToken: { access: 'expired-token' } });
        vm.onStartEnroll();
        await vm.doEnroll();
        await flushPromises();

        expect(adminAuthServiceMock.totpRegisterOptions).toHaveBeenCalledTimes(1);
        expect(vm.verifiedToken).toBeNull();
        expect(notificationSpy).toHaveBeenCalledWith(expect.objectContaining({ variant: 'error' }));

        notificationSpy.mockRestore();
    });
});
