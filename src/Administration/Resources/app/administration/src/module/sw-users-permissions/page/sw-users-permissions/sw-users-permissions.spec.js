/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

describe('modules/sw-users-permissions/page/sw-users-permissions', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = mount(
            await wrapTestComponent('sw-users-permissions', {
                sync: true,
            }),
            {
                global: {
                    renderStubDefaultSlot: true,
                    stubs: {
                        'sw-page': {
                            template: '<div><slot name="content"></slot></div>',
                        },
                        'sw-card-view': true,
                        'sw-users-permissions-user-listing': true,
                        'sw-users-permissions-role-listing': true,
                        'sw-users-permissions-configuration': true,
                        'sw-icon': true,
                        'sw-button-process': true,
                    },
                },
            },
        );

        await flushPromises();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the user list', async () => {
        const userListComponent = wrapper.find('sw-users-permissions-user-listing-stub');

        expect(userListComponent.exists()).toBeTruthy();
    });

    it('should change the loading state correctly', () => {
        expect(wrapper.vm.isLoading).toBe(true);

        wrapper.vm.onChangeLoading(false);

        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should finish saving correctly', async () => {
        await wrapper.setData({
            isSaveSuccessful: true,
        });

        wrapper.vm.onSaveFinish();

        expect(wrapper.vm.isSaveSuccessful).toBe(false);
    });
});
