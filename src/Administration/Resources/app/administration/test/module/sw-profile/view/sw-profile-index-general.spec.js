import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-profile/view/sw-profile-index-general';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-profile-index-general'), {
        localVue,
        stubs: {
            'sw-card': true,
            'sw-container': true,
            'sw-text-field': true,
            'sw-select-field': true,
            'sw-password-field': true
        },
        provide: {
            acl: {
                can: (key) => {
                    if (!key) { return true; }

                    return privileges.includes(key);
                }
            }
        },
        propsData: {
            user: {},
            languages: [],
            newPassword: null,
            newPasswordConfirm: null,
            avatarMediaItem: null,
            isUserLoading: false,
            languageId: null,
            isDisabled: true,
            userRepository: {}
        }
    });
}

describe('src/module/sw-profile/view/sw-profile-index-general', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to change new password', async () => {
        const spyNewPasswordChangeEmit = jest.spyOn(wrapper.vm, '$emit');

        await wrapper.setData({
            computedNewPassword: 'Shopware'
        });

        expect(spyNewPasswordChangeEmit).toBeCalledWith('new-password-change', 'Shopware');
    });

    it('should be able to change new password confirm', async () => {
        const spyNewPasswordConfirmChangeEmit = jest.spyOn(wrapper.vm, '$emit');

        await wrapper.setData({
            computedNewPasswordConfirm: 'Shopware'
        });

        expect(spyNewPasswordConfirmChangeEmit).toBeCalledWith('new-password-confirm-change', 'Shopware');
    });

    it('should be able to upload media', () => {
        const spyMediaUploadEmit = jest.spyOn(wrapper.vm, '$emit');

        wrapper.vm.onUploadMedia({ targetId: 'targetId' });

        expect(spyMediaUploadEmit).toBeCalledWith('media-upload', { targetId: 'targetId' });
    });

    it('should be able to drop media', () => {
        const spyMediaUploadEmit = jest.spyOn(wrapper.vm, '$emit');

        wrapper.vm.onDropMedia({ id: 'targetId' });

        expect(spyMediaUploadEmit).toBeCalledWith('media-upload', { targetId: 'targetId' });
    });

    it('should be able to remove media', () => {
        const spyMediaRemoveEmit = jest.spyOn(wrapper.vm, '$emit');

        wrapper.vm.onRemoveMedia();

        expect(spyMediaRemoveEmit).toBeCalledWith('media-remove');
    });

    it('should be able to open media', () => {
        const spyMediaOpenEmit = jest.spyOn(wrapper.vm, '$emit');

        wrapper.vm.onOpenMedia();

        expect(spyMediaOpenEmit).toBeCalledWith('media-open');
    });
});
