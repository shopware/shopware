/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

interface MethodRow {
    type: string;
    enabled: boolean;
    isPrimary: boolean;
    isSecondFactor: boolean;
    priority: number;
}

interface StoredMethodSettings {
    enabled?: boolean;
    isPrimary?: boolean;
    isSecondFactor?: boolean;
    priority?: number;
}

interface IndexVm {
    methods: MethodRow[];
    mfaChallengeTtlSeconds: number;
    isSaveSuccessful: boolean;
    isEditable: boolean;
    onSave(): Promise<void>;
}

async function createWrapper({
    privileges = [
        'admin_auth.viewer',
        'admin_auth.editor',
    ],
    configValues = {},
    saveValues = jest.fn(() => Promise.resolve()),
} = {}) {
    const getValues = jest.fn(() => Promise.resolve(configValues));

    const wrapper = mount(await wrapTestComponent('sw-settings-admin-auth-index', { sync: true }), {
        global: {
            provide: {
                systemConfigApiService: {
                    getValues,
                    saveValues,
                },
                acl: {
                    can: (identifier: string) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
            },
            stubs: {
                'sw-page': {
                    template: `
                        <div class="sw-page">
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                            <slot></slot>
                        </div>
                    `,
                },
                'sw-card-view': {
                    template: '<div class="sw-card-view"><slot></slot></div>',
                },
                'sw-tabs': {
                    template: '<div class="sw-tabs"><slot></slot></div>',
                },
                'sw-tabs-item': true,
            },
        },
    });

    await flushPromises();

    const vm = wrapper.vm as unknown as IndexVm;

    return { wrapper, vm, getValues, saveValues };
}

describe('module/sw-settings-admin-auth/page/sw-settings-admin-auth-index', () => {
    it('should load the method defaults when nothing is stored yet', async () => {
        const { vm, getValues } = await createWrapper();

        expect(getValues).toHaveBeenCalledWith('core.adminAuth', null);

        expect(vm.methods.map((method) => method.type)).toEqual([
            'password',
            'webauthn',
            'totp',
            'recovery_codes',
        ]);
        expect(vm.mfaChallengeTtlSeconds).toBe(300);
    });

    it('should merge the stored settings over the defaults and clamp them to the capabilities', async () => {
        const { vm } = await createWrapper({
            configValues: {
                'core.adminAuth.methods': {
                    webauthn: { enabled: false, priority: 200 },
                    // stored nonsense: password can never be a second factor, totp never primary
                    password: { isSecondFactor: true },
                    totp: { isPrimary: true },
                },
                'core.adminAuth.mfaChallengeTtlSeconds': 600,
            },
        });

        const byType = Object.fromEntries(
            vm.methods.map((method) => [
                method.type,
                method,
            ]),
        );

        expect(byType.webauthn).toMatchObject({ enabled: false, priority: 200 });
        expect(byType.password.isSecondFactor).toBe(false);
        expect(byType.totp.isPrimary).toBe(false);

        // highest priority first
        expect(vm.methods[0].type).toBe('webauthn');

        expect(vm.mfaChallengeTtlSeconds).toBe(600);
    });

    it('should fall back to the default challenge lifetime for invalid stored values', async () => {
        const { vm } = await createWrapper({
            configValues: {
                'core.adminAuth.mfaChallengeTtlSeconds': -5,
            },
        });

        expect(vm.mfaChallengeTtlSeconds).toBe(300);
    });

    it('should save the method settings and the challenge lifetime', async () => {
        const { vm, saveValues } = await createWrapper();

        vm.methods.find((method) => method.type === 'password')!.enabled = false;
        vm.mfaChallengeTtlSeconds = 120;

        await vm.onSave();
        await flushPromises();

        expect(saveValues).toHaveBeenCalledTimes(1);

        const payload = (saveValues.mock.calls[0] as unknown[])[0] as {
            'core.adminAuth.methods': Record<string, StoredMethodSettings>;
            'core.adminAuth.mfaChallengeTtlSeconds': number;
        };
        expect(payload['core.adminAuth.mfaChallengeTtlSeconds']).toBe(120);
        expect(payload['core.adminAuth.methods'].password).toEqual({
            enabled: false,
            isPrimary: true,
            isSecondFactor: false,
            priority: 100,
        });
        expect(Object.keys(payload['core.adminAuth.methods'])).toEqual([
            'password',
            'webauthn',
            'totp',
            'recovery_codes',
        ]);
    });

    it('should show an error notification when saving fails', async () => {
        const { vm } = await createWrapper({
            saveValues: jest.fn(() => Promise.reject(new Error('nope'))),
        });

        const notificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');

        await vm.onSave();
        await flushPromises();

        expect(notificationSpy).toHaveBeenCalledWith(expect.objectContaining({ variant: 'error' }));
        expect(vm.isSaveSuccessful).toBe(false);

        notificationSpy.mockRestore();
    });

    it('should disable the save action without the editor privilege', async () => {
        const { wrapper, vm } = await createWrapper({ privileges: ['admin_auth.viewer'] });

        expect(vm.isEditable).toBe(false);
        expect(wrapper.get('.sw-settings-admin-auth-index__save-action').attributes('disabled')).toBeDefined();
    });
});
