import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-profile/page/sw-profile-index';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-profile-index'), {
        localVue,
        stubs: {
            'sw-page': {
                template: '<div class="sw-page"><slot name="smart-bar-actions"></slot></div>'
            },
            'sw-search-bar': true,
            'sw-notification-center': true,
            'sw-language-switch': true,
            'sw-button': true,
            'sw-button-process': true,
            'sw-card-view': true,
            'sw-language-info': true,
            'sw-tabs': true,
            'sw-tabs-item': true
        },
        provide: {
            acl: {
                can: (key) => {
                    if (!key) { return true; }

                    return privileges.includes(key);
                }
            },
            repositoryFactory: {
                create: () => ({
                    get: () => Promise.resolve()
                })
            },
            loginService: {},
            userService: {
                getUser: () => Promise.resolve()
            }
        }
    });
}

describe('src/module/sw-profile/page/sw-profile-index', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to save own user', async () => {
        const wrapper = createWrapper();
        await wrapper.setData({
            isLoading: false
        });

        const saveButton = wrapper.find('.sw-profile__save-action');

        expect(saveButton.attributes().isLoading).toBeFalsy();
        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to save own user', async () => {
        const wrapper = await createWrapper([
            'user.update_profile'
        ]);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        await wrapper.setData({
            isLoading: false,
            isUserLoading: false
        });
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-profile__save-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should be able to change new password', () => {
        const wrapper = createWrapper();

        wrapper.vm.onChangeNewPassword('Shopware');

        expect(wrapper.vm.newPassword).toBe('Shopware');
    });

    it('should be able to change new password confirm', () => {
        const wrapper = createWrapper();

        wrapper.vm.onChangeNewPasswordConfirm('Shopware');

        expect(wrapper.vm.newPasswordConfirm).toBe('Shopware');
    });
});
