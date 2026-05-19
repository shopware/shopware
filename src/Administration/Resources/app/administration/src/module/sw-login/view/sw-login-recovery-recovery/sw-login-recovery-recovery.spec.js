/**
 * @sw-package framework
 */
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

        await wrapper.setData({
            newPassword: 'shopware',
            newPasswordConfirm: 'shopware',
        });
        await wrapper.vm.updatePassword();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.login.index',
        });

        wrapper.vm.$router.push.mockRestore();
        wrapper.vm.userRecoveryService.updateUserPassword.mockRestore();
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
    });
});
