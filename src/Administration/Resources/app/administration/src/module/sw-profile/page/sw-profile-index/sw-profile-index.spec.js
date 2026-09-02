/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import EntityCollection from 'src/core/data/entity-collection.data';
import TimezoneService from 'src/core/service/timezone.service';
import useTheme, { USER_THEME_CONFIG_KEY } from 'src/app/composables/use-theme';
import useModuleIconColors, { USER_MODULE_ICON_COLORS_CONFIG_KEY } from 'src/app/composables/use-module-icon-colors';
import 'src/module/sw-profile/store/sw-profile.store';

async function createWrapper(
    privileges = [],
    isSso = { isSso: false },
    saveFunction = () => Promise.resolve({}),
    loginService = { loginByUsername: () => Promise.resolve({}), logout: () => {} },
    { featureActive = false, routeName = 'sw.profile.index.general', routerPush = jest.fn() } = {},
) {
    return mount(await wrapTestComponent('sw-profile-index', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>
                            `,
                },
                'sw-card-view': {
                    template: `<div class="sw-card-view"><slot></slot></div>`,
                },
                'router-view': {
                    template: `<div><slot></slot></div>`,
                },
                'sw-search-bar': true,
                'sw-notification-center': true,
                'sw-language-switch': true,
                'sw-button-process': true,
                'sw-language-info': true,
                'sw-tabs': {
                    name: 'sw-tabs',
                    template: '<div class="sw-tabs"><slot /></div>',
                    props: [
                        'positionIdentifier',
                    ],
                },
                'sw-tabs-item': {
                    name: 'sw-tabs-item',
                    template: '<div class="sw-tabs-item"><slot /></div>',
                    props: [
                        'route',
                        'title',
                    ],
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    template: '<div class="mt-tabs"></div>',
                    props: {
                        defaultItem: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                        items: {
                            type: Array,
                            required: true,
                        },
                        positionIdentifier: {
                            type: String,
                            required: true,
                        },
                    },
                },
                'sw-skeleton': true,
                'sw-verify-user-modal': true,
                'sw-media-modal-v2': true,
            },
            mocks: {
                $route: {
                    name: routeName,
                    params: {},
                },
                $router: {
                    push: routerPush,
                },
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
                                    email: 'foo@bar.baz',
                                }),
                            search: () => Promise.resolve(new EntityCollection('', '', Shopware.Context.api, null, [], 0)),
                            getSyncChangeset: () => ({
                                changeset: [{ changes: { id: '1337' } }],
                            }),
                            save: () => Promise.resolve(),
                        };
                    },
                },
                loginService,
                userService: {
                    getUser: () => Promise.resolve({ data: { id: '87923' } }),
                    updateUser: saveFunction,
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
                    processSearchPreferencesFields: () => ({
                        name: {
                            _searchable: true,
                        },
                    }),
                },
                searchRankingService: {
                    clearCacheUserSearchConfiguration: () => {},
                    isValidTerm: (term) => {
                        return term && term.trim().length >= 1;
                    },
                },
                ssoSettingsService: {
                    isSso: () => {
                        return Promise.resolve(isSso);
                    },
                },
                validationApiService: {
                    validateEmailAddress: () => {
                        return Promise.resolve(true);
                    },
                },
                feature: {
                    isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
                },
            },
        },
    });
}

function createFeatureWrapper(options = {}) {
    return createWrapper([], undefined, undefined, undefined, {
        featureActive: true,
        ...options,
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

    beforeEach(() => {
        Shopware.Store.get('swProfile').$reset();
        jest.spyOn(Shopware.Service('userConfigService'), 'upsert').mockResolvedValue();
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('should render the deprecated tabs when the major feature flag is inactive', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const tabs = wrapper.getComponent({ name: 'sw-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-profile-index');
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should keep the fallback tab route contract', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const tabItems = wrapper.findAllComponents({ name: 'sw-tabs-item' });

        expect(tabItems).toHaveLength(3);
        expect(tabItems[0].props('route')).toStrictEqual({ name: 'sw.profile.index.general' });
        expect(tabItems[0].props('title')).toBe('sw-profile.tabGeneral.title');
        expect(tabItems[0].text()).toBe('sw-profile.tabGeneral.title');

        expect(tabItems[1].props('route')).toStrictEqual({ name: 'sw.profile.index.searchPreferences' });
        expect(tabItems[1].props('title')).toBe('sw-profile.tabSearchPreferences.title');
        expect(tabItems[1].text()).toBe('sw-profile.tabSearchPreferences.title');

        expect(tabItems[2].props('route')).toStrictEqual({ name: 'sw.profile.index.privacyPreferences' });
        expect(tabItems[2].props('title')).toBe('sw-profile.tabPrivacyPreferences.title');
        expect(tabItems[2].text()).toBe('sw-profile.tabPrivacyPreferences.title');
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        const wrapper = await createFeatureWrapper({
            routeName: 'sw.profile.index.searchPreferences',
        });
        await flushPromises();

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-profile-index');
        expect(tabs.props('defaultItem')).toBe('sw.profile.index.searchPreferences');
        expect(tabs.props('items')).toEqual([
            expect.objectContaining({
                label: 'sw-profile.tabGeneral.title',
                name: 'sw.profile.index.general',
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-profile.tabSearchPreferences.title',
                name: 'sw.profile.index.searchPreferences',
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-profile.tabPrivacyPreferences.title',
                name: 'sw.profile.index.privacyPreferences',
                onClick: expect.any(Function),
            }),
        ]);
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
    });

    it('should navigate when a meteor tab item is clicked', async () => {
        const routerPush = jest.fn();
        const wrapper = await createFeatureWrapper({
            routerPush,
        });
        await flushPromises();

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });
        const privacyTab = tabs.props('items').find((item) => {
            return item.name === 'sw.profile.index.privacyPreferences';
        });

        privacyTab.onClick();

        expect(routerPush).toHaveBeenCalledWith({ name: 'sw.profile.index.privacyPreferences' });
    });

    it('should not be able to save own user', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.isLoading = false;
        await nextTick();

        const saveButton = wrapper.find('.sw-profile__save-action');

        expect(saveButton.attributes().isLoading).toBeFalsy();
        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to save own user', async () => {
        const wrapper = await createWrapper([
            'user.update_profile',
        ]);
        await flushPromises();

        wrapper.vm.isLoading = false;
        wrapper.vm.isUserLoading = false;
        await nextTick();
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

        wrapper.vm.isLoading = false;
        await nextTick();
        await flushPromises();

        wrapper.vm.setMediaItem({ targetId: mediaId });
        await flushPromises();

        expect(wrapper.vm.user.avatarId).toBe(mediaId);
        expect(wrapper.vm.avatarMediaItem.id).toBe(mediaId);
    });

    it('should show the password confirm modal', async () => {
        const updateFunction = jest.fn(() => Promise.resolve({}));
        const wrapper = await createWrapper(['user.update_profile'], { isSso: false }, updateFunction);
        await flushPromises();

        const saveButton = wrapper.find('.sw-profile__save-action');
        await saveButton.trigger('click');
        await flushPromises();

        const passwordConfirmModal = wrapper.find('sw-verify-user-modal-stub');

        expect(passwordConfirmModal.exists()).toBeTruthy();
        expect(updateFunction).not.toHaveBeenCalled();
    });

    it('should update the user', async () => {
        const updateFunction = jest.fn(() => Promise.resolve({}));
        const wrapper = await createWrapper(['user.update_profile'], { isSso: true }, updateFunction);
        await flushPromises();

        const saveButton = wrapper.find('.sw-profile__save-action');
        await saveButton.trigger('click');
        await flushPromises();

        expect(updateFunction).toHaveBeenCalled();
    });

    describe('theme selection', () => {
        afterEach(async () => {
            useTheme().setTheme('system');
            await nextTick();

            localStorage.removeItem('mt-theme');
        });

        it('should apply and persist the chosen theme on save', async () => {
            const wrapper = await createWrapper(
                ['user.update_profile'],
                { isSso: true },
                jest.fn(() => Promise.resolve({})),
            );
            await flushPromises();

            wrapper.vm.onChangeUserTheme('dark');

            const saveButton = wrapper.find('.sw-profile__save-action');
            await saveButton.trigger('click');
            await flushPromises();

            expect(useTheme().theme.value).toBe('dark');
            expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
            expect(Shopware.Service('userConfigService').upsert).toHaveBeenCalledWith({
                [USER_THEME_CONFIG_KEY]: { theme: 'dark' },
            });
        });

        it('should not persist the theme before saving', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            wrapper.vm.onChangeUserTheme('dark');
            await flushPromises();

            expect(useTheme().theme.value).toBe('system');
            expect(Shopware.Service('userConfigService').upsert).not.toHaveBeenCalled();
        });
    });

    describe('module icon colors', () => {
        afterEach(() => {
            useModuleIconColors().enabled.value = false;
        });

        it('should apply and persist the chosen preference on save', async () => {
            const wrapper = await createWrapper(
                ['user.update_profile'],
                { isSso: true },
                jest.fn(() => Promise.resolve({})),
            );
            await flushPromises();

            wrapper.vm.onChangeUserModuleIconColors(true);

            const saveButton = wrapper.find('.sw-profile__save-action');
            await saveButton.trigger('click');
            await flushPromises();

            expect(useModuleIconColors().enabled.value).toBe(true);
            expect(Shopware.Service('userConfigService').upsert).toHaveBeenCalledWith({
                [USER_MODULE_ICON_COLORS_CONFIG_KEY]: { enabled: true },
            });
        });

        it('should not persist the preference before saving', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            wrapper.vm.onChangeUserModuleIconColors(true);
            await flushPromises();

            expect(useModuleIconColors().enabled.value).toBe(false);
            expect(Shopware.Service('userConfigService').upsert).not.toHaveBeenCalled();
        });
    });

    it('should save minSearchTermLength and userSearchPreferences', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.$route = {
            name: 'sw.profile.index.searchPreferences',
        };

        wrapper.vm.saveMinSearchTermLength = jest.fn(() => Promise.resolve());
        wrapper.vm.saveUserSearchPreferences = jest.fn(() => Promise.resolve());

        wrapper.vm.onSave();

        expect(wrapper.vm.saveMinSearchTermLength).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.saveUserSearchPreferences).toHaveBeenCalledTimes(1);

        wrapper.vm.saveMinSearchTermLength.mockRestore();
        wrapper.vm.saveUserSearchPreferences.mockRestore();
    });

    it('should save user search preferences through the admin user config store', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        Shopware.Store.get('swProfile').searchPreferences = [
            {
                entityName: 'product',
                _searchable: true,
                fields: [
                    {
                        fieldName: 'name',
                    },
                ],
            },
        ];
        Shopware.Store.get('swProfile').userSearchPreferences = null;

        await wrapper.vm.saveUserSearchPreferences();

        expect(Shopware.Service('userConfigService').upsert).toHaveBeenCalledWith({
            'search.preferences': [
                {
                    product: {
                        _searchable: true,
                        name: {
                            _searchable: true,
                        },
                    },
                },
            ],
        });
        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.isSaveSuccessful).toBe(true);
    });

    it('should re-login before updateCurrentUser when password changes (user:editor path)', async () => {
        const loginByUsername = jest.fn(() => Promise.resolve({}));
        const loginService = { loginByUsername, logout: jest.fn() };

        const wrapper = await createWrapper(['user:editor'], { isSso: false }, () => Promise.resolve({}), loginService);
        await flushPromises();

        await wrapper.setData({
            newPassword: 'NewPassword123',
            newPasswordConfirm: 'NewPassword123',
            user: {
                id: '87923',
                username: 'admin',
                localeId: '1337',
                email: 'foo@bar.baz',
            },
        });

        let updateCurrentUserCalled = false;
        let loginCalledBeforeUpdate = false;

        wrapper.vm.updateCurrentUser = jest.fn(async () => {
            updateCurrentUserCalled = true;
            loginCalledBeforeUpdate = loginByUsername.mock.calls.length > 0;
        });

        wrapper.vm.saveUser({});
        await flushPromises();

        expect(loginByUsername).toHaveBeenCalledWith('admin', 'NewPassword123');
        expect(updateCurrentUserCalled).toBe(true);
        expect(loginCalledBeforeUpdate).toBe(true);
        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should NOT re-login when no password change (user:editor path)', async () => {
        const loginByUsername = jest.fn(() => Promise.resolve({}));
        const loginService = { loginByUsername, logout: jest.fn() };

        const wrapper = await createWrapper(['user:editor'], { isSso: false }, () => Promise.resolve({}), loginService);
        await flushPromises();

        await wrapper.setData({
            newPassword: null,
            user: {
                id: '87923',
                username: 'admin',
                localeId: '1337',
                email: 'foo@bar.baz',
            },
        });

        wrapper.vm.updateCurrentUser = jest.fn(async () => {});

        wrapper.vm.saveUser({});
        await flushPromises();

        expect(loginByUsername).not.toHaveBeenCalled();
        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should re-login before updateCurrentUser when password changes (non-user:editor path)', async () => {
        const loginByUsername = jest.fn(() => Promise.resolve({}));
        const loginService = { loginByUsername, logout: jest.fn() };
        const updateUser = jest.fn(() => Promise.resolve({}));

        const wrapper = await createWrapper([], { isSso: false }, updateUser, loginService);
        await flushPromises();

        await wrapper.setData({
            newPassword: 'NewPassword123',
            newPasswordConfirm: 'NewPassword123',
            user: {
                id: '87923',
                username: 'admin',
                localeId: '1337',
                email: 'foo@bar.baz',
            },
        });

        let updateCurrentUserCalled = false;
        let loginCalledBeforeUpdate = false;

        wrapper.vm.updateCurrentUser = jest.fn(async () => {
            updateCurrentUserCalled = true;
            loginCalledBeforeUpdate = loginByUsername.mock.calls.length > 0;
        });

        wrapper.vm.saveUser({});
        await flushPromises();

        expect(loginByUsername).toHaveBeenCalledWith('admin', 'NewPassword123');
        expect(updateCurrentUserCalled).toBe(true);
        expect(loginCalledBeforeUpdate).toBe(true);
        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should NOT re-login when no password change (non-user:editor path)', async () => {
        const loginByUsername = jest.fn(() => Promise.resolve({}));
        const loginService = { loginByUsername, logout: jest.fn() };
        const updateUser = jest.fn(() => Promise.resolve({}));

        const wrapper = await createWrapper([], { isSso: false }, updateUser, loginService);
        await flushPromises();

        await wrapper.setData({
            newPassword: null,
            user: {
                id: '87923',
                username: 'admin',
                localeId: '1337',
                email: 'foo@bar.baz',
            },
        });

        wrapper.vm.updateCurrentUser = jest.fn(async () => {});

        wrapper.vm.saveUser({});
        await flushPromises();

        expect(loginByUsername).not.toHaveBeenCalled();
        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should show an error and not succeed when save fails (non-user:editor path)', async () => {
        const loginByUsername = jest.fn(() => Promise.resolve({}));
        const loginService = { loginByUsername, logout: jest.fn() };
        const updateUser = jest.fn(() => Promise.reject(new Error('Save failed')));

        const wrapper = await createWrapper([], { isSso: false }, updateUser, loginService);
        await flushPromises();

        await wrapper.setData({
            newPassword: 'NewPassword123',
            user: {
                id: '87923',
                username: 'admin',
                localeId: '1337',
                email: 'foo@bar.baz',
            },
        });

        wrapper.vm.createNotificationError = jest.fn();

        wrapper.vm.saveUser({});
        await flushPromises();

        expect(loginByUsername).not.toHaveBeenCalled();
        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();
    });

    it('should log out and not show a save error when re-login fails after password change (user:editor path)', async () => {
        const loginByUsername = jest.fn(() => Promise.reject(new Error('Network error')));
        const logout = jest.fn();
        const loginService = { loginByUsername, logout };

        const wrapper = await createWrapper(['user:editor'], { isSso: false }, () => Promise.resolve({}), loginService);
        await flushPromises();

        await wrapper.setData({
            newPassword: 'NewPassword123',
            newPasswordConfirm: 'NewPassword123',
            user: {
                id: '87923',
                username: 'admin',
                localeId: '1337',
                email: 'foo@bar.baz',
            },
        });

        wrapper.vm.updateCurrentUser = jest.fn(async () => {});
        wrapper.vm.handleUserSaveError = jest.fn();

        wrapper.vm.saveUser({});
        await flushPromises();

        expect(loginByUsername).toHaveBeenCalledWith('admin', 'NewPassword123');
        expect(logout).toHaveBeenCalled();
        expect(wrapper.vm.handleUserSaveError).not.toHaveBeenCalled();
    });

    it('should log out and not show a save error when re-login fails after password change (non-user:editor path)', async () => {
        const loginByUsername = jest.fn(() => Promise.reject(new Error('Network error')));
        const logout = jest.fn();
        const loginService = { loginByUsername, logout };
        const updateUser = jest.fn(() => Promise.resolve({}));

        const wrapper = await createWrapper([], { isSso: false }, updateUser, loginService);
        await flushPromises();

        await wrapper.setData({
            newPassword: 'NewPassword123',
            newPasswordConfirm: 'NewPassword123',
            user: {
                id: '87923',
                username: 'admin',
                localeId: '1337',
                email: 'foo@bar.baz',
            },
        });

        wrapper.vm.updateCurrentUser = jest.fn(async () => {});
        wrapper.vm.createNotificationError = jest.fn();

        wrapper.vm.saveUser({});
        await flushPromises();

        expect(loginByUsername).toHaveBeenCalledWith('admin', 'NewPassword123');
        expect(logout).toHaveBeenCalled();
        expect(wrapper.vm.createNotificationError).not.toHaveBeenCalled();
    });
});
