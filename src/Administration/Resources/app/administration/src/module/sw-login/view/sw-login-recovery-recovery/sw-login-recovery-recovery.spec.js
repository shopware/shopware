/**
 * @package admin
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-login-recovery-recovery', { sync: true }), {
        global: {
            stubs: {
                'router-link': true,
                'sw-loader': true,
                'sw-button': true,
                'sw-icon': true,
                'sw-password-field': true,
                'sw-alert': true,
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

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.element).toMatchSnapshot();
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
});
