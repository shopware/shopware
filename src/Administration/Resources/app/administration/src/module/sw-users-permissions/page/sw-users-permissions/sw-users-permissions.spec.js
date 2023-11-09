/**
 * @package services-settings
 */
import { shallowMount } from '@vue/test-utils';
import swUsersPermissions from 'src/module/sw-users-permissions/page/sw-users-permissions';

Shopware.Component.register('sw-users-permissions', swUsersPermissions);

describe('modules/sw-users-permissions/page/sw-users-permissions', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = shallowMount(await Shopware.Component.build('sw-users-permissions'), {
            stubs: {
                'sw-page': {
                    template: '<div><slot name="content"></slot></div>',
                },
                'sw-card-view': true,
                'sw-users-permissions-user-listing': true,
                'sw-users-permissions-role-listing': true,
                'sw-users-permissions-configuration': true,
            },
        });
    });

    afterEach(() => {
        wrapper.destroy();
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

    it('should save the system config successful', async () => {
        await wrapper.setData({
            $refs: {
                configuration: {
                    $refs: {
                        systemConfig: {
                            saveAll: () => Promise.resolve(),
                        },
                    },
                },
            },
        });

        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.$refs.configuration.$refs.systemConfig.saveAll = jest.fn(() => {
            return Promise.resolve();
        });

        await wrapper.vm.onSave();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.createNotificationError).not.toHaveBeenCalled();

        wrapper.vm.createNotificationError.mockRestore();
        wrapper.vm.$refs.configuration.$refs.systemConfig.saveAll.mockRestore();
    });

    it('should save system config failed', async () => {
        await wrapper.setData({
            $refs: {
                configuration: {
                    $refs: {
                        systemConfig: {
                            saveAll: () => Promise.resolve(),
                        },
                    },
                },
            },
        });

        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.$refs.configuration.$refs.systemConfig.saveAll = jest.fn(() => {
            return Promise.reject(new Error('Oops!'));
        });

        await wrapper.vm.onSave();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'Oops!',
        });

        wrapper.vm.createNotificationError.mockRestore();
        wrapper.vm.$refs.configuration.$refs.systemConfig.saveAll.mockRestore();
    });

    it('should finish saving correctly', async () => {
        await wrapper.setData({
            isSaveSuccessful: true,
        });

        wrapper.vm.onSaveFinish();

        expect(wrapper.vm.isSaveSuccessful).toBe(false);
    });
});
