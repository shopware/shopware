/**
 * @package admin
 */
import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-login/view/sw-login-recovery-recovery';

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-login-recovery-recovery'), {
        stubs: {
            'router-link': true,
            'sw-loader': true,
            'sw-button': true,
            'sw-icon': true,
            'sw-password-field': true,
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
        propsData: {
            hash: '',
        },
    });
}

describe('src/module/sw-login/view/sw-login-recovery-recovery', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
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
