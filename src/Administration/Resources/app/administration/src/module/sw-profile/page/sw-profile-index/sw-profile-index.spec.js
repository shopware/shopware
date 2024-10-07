/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import TimezoneService from 'src/core/service/timezone.service';

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-profile-index', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: '<div class="sw-page"><slot name="smart-bar-actions"></slot></div>',
                },
                'sw-search-bar': true,
                'sw-notification-center': true,
                'sw-language-switch': true,
                'sw-button': true,
                'sw-button-process': true,
                'sw-card-view': true,
                'sw-language-info': true,
                'sw-tabs': true,
                'sw-tabs-item': true,
                'sw-skeleton': true,
                'router-view': true,
                'sw-verify-user-modal': true,
                'sw-media-modal-v2': true,
            },
            provide: {
                acl: {
                    can: (key) => {
                        if (!key) {
                            return true;
                        }

                        return privileges.includes(key);
                    },
                },
                repositoryFactory: {
                    create: (entityName) => {
                        if (entityName === 'media') {
                            return {
                                get: () => Promise.resolve({ id: '2142' }),
                            };
                        }

                        return {
                            get: () =>
                                Promise.resolve({
                                    id: '87923',
                                    localeId: '1337',
                                }),
                            search: () => Promise.resolve(new EntityCollection('', '', Shopware.Context.api, null, [], 0)),
                            getSyncChangeset: () => ({
                                changeset: [{ changes: { id: '1337' } }],
                            }),
                        };
                    },
                },
                loginService: {},
                userService: {
                    getUser: () => Promise.resolve({ data: { id: '87923' } }),
                    updateUser: () => Promise.resolve({}),
                },
                mediaDefaultFolderService: {},
                searchPreferencesService: {
                    getDefaultSearchPreferences: () => {},
                    getUserSearchPreferences: () => {},
                    createUserSearchPreferences: () => {
                        return {
                            key: 'search.preferences',
                            userId: 'userId',
                        };
                    },
                },
                searchRankingService: {
                    clearCacheUserSearchConfiguration: () => {},
                },
                userConfigService: {
                    upsert: () => {
                        return Promise.resolve();
                    },
                    search: () => {
                        return Promise.resolve();
                    },
                },
            },
        },
    });
}

describe('src/module/sw-profile/page/sw-profile-index', () => {
    beforeAll(() => {
        Shopware.Service().register('timezoneService', () => {
            return new TimezoneService();
        });

        Shopware.Service().register('localeHelper', () => {
            return {
                setLocaleWithId: jest.fn(),
            };
        });
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to save own user', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.find('.sw-profile__save-action');

        expect(saveButton.attributes().isLoading).toBeFalsy();
        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to save own user', async () => {
        const wrapper = await createWrapper([
            'user.update_profile',
        ]);
        await flushPromises();

        await wrapper.setData({
            isLoading: false,
            isUserLoading: false,
        });
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-profile__save-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should be able to change new password', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.onChangeNewPassword('Shopware');

        expect(wrapper.vm.newPassword).toBe('Shopware');
    });

    it('should be able to change new password confirm', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.onChangeNewPasswordConfirm('Shopware');

        expect(wrapper.vm.newPasswordConfirm).toBe('Shopware');
    });

    it('should reset general data if route changes', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.createdComponent = jest.fn();
        wrapper.vm.beforeMountComponent = jest.fn();

        wrapper.vm.resetGeneralData();

        expect(wrapper.vm.newPassword).toBeNull();
        expect(wrapper.vm.newPasswordConfirm).toBeNull();

        expect(wrapper.vm.createdComponent).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.beforeMountComponent).toHaveBeenCalledTimes(1);

        wrapper.vm.createdComponent.mockRestore();
        wrapper.vm.beforeMountComponent.mockRestore();
    });

    it('should handle user-save errors correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.createNotificationError = jest.fn();

        wrapper.vm.$route = {
            name: 'sw.profile.index.general',
        };

        await wrapper.setData({
            isLoading: true,
            $route: {
                name: 'sw.profile.index.general',
            },
        });
        wrapper.vm.handleUserSaveError();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-profile.index.notificationSaveErrorMessage',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should be able to save the user after verifying password successful', async () => {
        const wrapper = await createWrapper();
        const saveUserSpyOn = jest.spyOn(wrapper.vm, 'saveUser');

        wrapper.vm.onVerifyPasswordFinished({ foo: 'bar' });

        expect(wrapper.vm.confirmPasswordModal).toBe(false);
        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(wrapper.vm.isLoading).toBe(true);

        expect(saveUserSpyOn).toHaveBeenCalledWith({ foo: 'bar' });
    });

    it('should handle avatarId and load the media', async () => {
        const wrapper = await createWrapper();
        const mediaId = '2142';

        await wrapper.setData({ isLoading: false });
        await flushPromises();

        wrapper.vm.setMediaItem({ targetId: mediaId });
        await flushPromises();

        expect(wrapper.vm.user.avatarId).toBe(mediaId);
        expect(wrapper.vm.avatarMediaItem.id).toBe(mediaId);
    });
});
