/**
 * @sw-package framework
 */
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-login-recovery-recovery', { sync: true }), {
        global: {
            stubs: {
                'router-link': true,
                'sw-loader': true,
            },
            provide: {
                userRecoveryService: {
                    checkHash: () => {
                        return Promise.resolve();
                    },
                    updateUserPassword: () => {
                        return Promise.resolve();
                    },
                },
            },
        },
        props: {
            hash: '',
        },
    });
}

describe('src/module/sw-login/view/sw-login-recovery-recovery', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should update password successful', async () => {
        wrapper.vm.$router.push = jest.fn();
        wrapper.vm.userRecoveryService.updateUserPassword = jest.fn(() => Promise.resolve());

        wrapper.vm.newPassword = 'shopware';
        wrapper.vm.newPasswordConfirm = 'shopware';
        await nextTick();
        await wrapper.vm.updatePassword();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.login.index',
        });

        wrapper.vm.$router.push.mockRestore();
        wrapper.vm.userRecoveryService.updateUserPassword.mockRestore();
    });

    it('should redirect to the recovery request with the linkExpired state when the hash is invalid', async () => {
        const replaceSpy = jest.fn();

        wrapper = mount(await wrapTestComponent('sw-login-recovery-recovery', { sync: true }), {
            global: {
                mocks: {
                    $router: { replace: replaceSpy, push: jest.fn() },
                },
                stubs: {
                    'router-link': true,
                    'sw-loader': true,
                },
                provide: {
                    userRecoveryService: {
                        checkHash: () => Promise.reject(),
                        updateUserPassword: () => Promise.resolve(),
                    },
                },
            },
            props: {
                hash: 'expired-hash',
            },
        });

        await flushPromises();

        expect(replaceSpy).toHaveBeenCalledWith({
            name: 'sw.login.index.recovery',
            state: {
                linkExpired: true,
            },
        });
    });

    it('should show a notification when the update fails without a structured api error', async () => {
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.userRecoveryService.updateUserPassword = jest.fn(() => Promise.reject(new Error('Bad gateway')));

        wrapper.vm.newPassword = 'shopware';
        wrapper.vm.newPasswordConfirm = 'shopware';
        await nextTick();
        await wrapper.vm.updatePassword();
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'Bad gateway',
        });
        expect(wrapper.emitted('is-not-loading')).toBeTruthy();
    });

    it('should call updateUserPassword when submit button is clicked', async () => {
        const testHash = 'test-hash-123';
        const testPassword = 'testPassword123';

        wrapper = await createWrapper();
        await wrapper.setProps({ hash: testHash });

        const updateUserPasswordSpy = jest.fn(() => Promise.resolve());
        wrapper.vm.userRecoveryService.updateUserPassword = updateUserPasswordSpy;

        const passwordInputs = wrapper.findAll('input[type="password"]');
        const newPasswordInput = passwordInputs[0];
        const confirmPasswordInput = passwordInputs[1];

        await newPasswordInput.setValue(testPassword);
        await confirmPasswordInput.setValue(testPassword);

        const form = wrapper.find('form');
        await form.trigger('submit');

        expect(updateUserPasswordSpy).toHaveBeenCalledTimes(1);
        expect(updateUserPasswordSpy).toHaveBeenCalledWith(testHash, testPassword, testPassword);
        expect(wrapper.emitted('is-loading')).toBeTruthy();

        await flushPromises();

        expect(wrapper.emitted('is-not-loading')).toBeTruthy();
    });
});
