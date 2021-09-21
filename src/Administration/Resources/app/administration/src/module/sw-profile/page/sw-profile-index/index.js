import { email } from 'src/core/service/validation.service';
import { KEY_USER_SEARCH_PREFERENCE } from 'src/app/service/search-ranking.service';
import template from './sw-profile-index.html.twig';
import swProfileState from '../../state/sw-profile.state';

const { Component, Mixin, State } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState, mapPropertyErrors } = Component.getComponentHelper();

Component.register('sw-profile-index', {
    template,

    inject: [
        'userService',
        'loginService',
        'mediaDefaultFolderService',
        'repositoryFactory',
        'acl',
        'feature',
        'searchPreferencesService',
        'searchRankingService',
        'userConfigService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            user: { username: '', email: '' },
            languages: [],
            imageSize: 140,
            newPassword: null,
            newPasswordConfirm: null,
            confirmPassword: null,
            avatarMediaItem: null,
            uploadTag: 'sw-profile-upload-tag',
            isLoading: false,
            isUserLoading: true,
            isSaveSuccessful: false,
            confirmPasswordModal: false,
            mediaDefaultFolderId: null,
            showMediaModal: false,
            timezoneOptions: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        ...mapState('swProfile', [
            'searchPreferences',
            'userSearchPreferences',
        ]),

        ...mapPropertyErrors('user', [
            'email',
            'timeZone',
        ]),

        isDisabled() {
            return true; // TODO use ACL here with NEXT-1653
        },

        userRepository() {
            return this.repositoryFactory.create('user');
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        localeRepository() {
            return this.repositoryFactory.create('locale');
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        userConfigRepository() {
            return this.repositoryFactory.create('user_config');
        },

        userMediaCriteria() {
            if (this.user.id) {
                // ???
                // ToDo: If SwSidebarMedia has the new data handling, change this too
                // return CriteriaFactory.equals('userId', this.user.id);
                return null;
            }

            return null;
        },

        languageId() {
            return Shopware.State.get('session').languageId;
        },
    },

    watch: {
        '$route'(newValue) {
            if (!newValue || newValue.name === 'sw.profile.index.searchPreferences') {
                return;
            }

            this.resetGeneralData();
        },

        'user.avatarMedia.id'() {
            if (!this.user.avatarMedia?.id) {
                return;
            }

            if (!this.acl.can('media.creator')) {
                return;
            }

            this.setMediaItem({ targetId: this.user.avatarMedia.id });
        },

        languageId() {
            this.createdComponent();
        },
    },

    beforeCreate() {
        State.registerModule('swProfile', swProfileState);
    },

    created() {
        this.createdComponent();
    },

    beforeMount() {
        this.beforeMountComponent();
    },

    beforeDestroy() {
        State.unregisterModule('swProfile');
    },

    methods: {
        createdComponent() {
            this.isUserLoading = true;

            const languagePromise = new Promise((resolve) => {
                resolve(this.languageId);
            });

            this.userPromise = this.getUserData();

            const promises = [
                languagePromise,
                this.userPromise,
            ];

            if (this.feature.isActive('FEATURE_NEXT_6040') && this.acl.can('media.creator')) {
                this.getMediaDefaultFolderId()
                    .then((id) => {
                        this.mediaDefaultFolderId = id;
                    })
                    .catch(() => {
                        this.mediaDefaultFolderId = null;
                    });
            }

            Promise.all(promises).then(() => {
                this.loadLanguages();
                this.loadTimezones();
            }).then(() => {
                this.isUserLoading = false;
            });
        },

        beforeMountComponent() {
            this.userPromise.then((user) => {
                this.user = user;
            });
        },

        loadLanguages() {
            const factoryContainer = Shopware.Application.getContainer('factory');
            const localeFactory = factoryContainer.locale;
            const registeredLocales = Array.from(localeFactory.getLocaleRegistry().keys());

            const languageCriteria = new Criteria();
            languageCriteria.addAssociation('locale');
            languageCriteria.addSorting(Criteria.sort('locale.name', 'ASC'));
            languageCriteria.addSorting(Criteria.sort('locale.territory', 'ASC'));
            languageCriteria.addFilter(Criteria.equalsAny('locale.code', registeredLocales));
            languageCriteria.limit = 500;

            return this.languageRepository.search(languageCriteria).then((result) => {
                this.languages = [];
                const localeIds = [];
                let fallbackId = '';

                result.forEach((lang) => {
                    lang.customLabel = `${lang.locale.translated.name} (${lang.locale.translated.territory})`;
                    this.languages.push(lang);

                    localeIds.push(lang.localeId);
                    if (lang.locale.code === Shopware.Context.app.fallbackLocale) {
                        fallbackId = lang.localeId;
                    }
                });

                if (!localeIds.includes(this.user.localeId)) {
                    this.user.localeId = fallbackId;
                    this.saveUser();
                }
                this.isUserLoading = false;

                return this.languages;
            });
        },

        loadTimezones() {
            return Shopware.Service('timezoneService').loadTimezones()
                .then((result) => {
                    this.timezoneOptions.push({
                        label: 'UTC',
                        value: 'UTC',
                    });

                    const loadedTimezoneOptions = result.map(timezone => ({
                        label: timezone,
                        value: timezone,
                    }));

                    this.timezoneOptions.push(...loadedTimezoneOptions);
                });
        },

        async getUserData() {
            const routeUser = this.$route.params.user;
            if (routeUser) {
                return this.userRepository.get(routeUser.id);
            }

            const user = await this.userService.getUser();
            return this.userRepository.get(user.data.id);
        },

        resetGeneralData() {
            if (!this.feature.isActive('FEATURE_NEXT_6040')) {
                return;
            }

            this.avatarMediaItem = null;
            this.newPassword = null;
            this.newPasswordConfirm = null;

            this.createdComponent();
            this.beforeMountComponent();
        },

        async saveFinish() {
            this.isSaveSuccessful = false;
            this.user = await this.getUserData();
        },

        onSave() {
            if (this.$route.name === 'sw.profile.index.searchPreferences' && this.feature.isActive('FEATURE_NEXT_6040')) {
                this.saveUserSearchPreferences();

                return;
            }

            if (this.checkEmail() === false) {
                return;
            }

            const passwordCheck = this.checkPassword();

            if (passwordCheck === null || passwordCheck === true) {
                this.confirmPasswordModal = true;
            }
        },

        checkEmail() {
            if (!this.user.email || !email(this.user.email)) {
                this.createErrorMessage(this.$tc('sw-profile.index.notificationInvalidEmailErrorMessage'));

                return false;
            }
            return true;
        },

        checkPassword() {
            if (this.newPassword && this.newPassword.length > 0) {
                if (this.newPassword !== this.newPasswordConfirm) {
                    this.createErrorMessage(this.$tc('sw-profile.index.notificationPasswordErrorMessage'));
                    return false;
                }

                this.user.password = this.newPassword;

                return true;
            }

            return null;
        },

        createErrorMessage(errorMessage) {
            this.createNotificationError({
                message: errorMessage,
            });
        },

        saveUser(authToken) {
            if (!this.acl.can('user:editor')) {
                const changes = this.userRepository.getSyncChangeset([this.user]);
                delete changes.changeset[0].changes.id;

                this.userService.updateUser(changes.changeset[0].changes).then(async () => {
                    await this.updateCurrentUser();

                    this.isLoading = false;
                    this.isSaveSuccessful = true;

                    Shopware.Service('localeHelper').setLocaleWithId(this.user.localeId);
                });

                return;
            }

            const context = { ...Shopware.Context.api };
            context.authToken.access = authToken;

            this.userRepository.save(this.user, context).then(async () => {
                // @feature-deprecated (FEATURE_NEXT_6040) tag:v6.5.0 - can be removed
                if (this.$refs.mediaSidebarItem) {
                    this.$refs.mediaSidebarItem.getList();
                }

                await this.updateCurrentUser();
                Shopware.Service('localeHelper').setLocaleWithId(this.user.localeId);

                if (this.newPassword) {
                    // re-issue a valid jwt token, as all user tokens were invalidated on password change
                    this.loginService.loginByUsername(this.user.username, this.newPassword).then(() => {
                        this.isSaveSuccessful = true;
                    }).catch(() => {
                        this.handleUserSaveError();
                    }).finally(() => {
                        this.isLoading = false;
                    });
                } else {
                    this.isLoading = false;
                    this.isSaveSuccessful = true;
                }

                this.confirmPassword = '';
                this.newPassword = '';
                this.newPasswordConfirm = '';
            }).catch(() => {
                this.handleUserSaveError();
            });
        },

        updateCurrentUser() {
            return this.userService.getUser().then((response) => {
                const data = response.data;
                delete data.password;

                return Shopware.State.commit('setCurrentUser', data);
            });
        },

        setMediaItem({ targetId }) {
            this.mediaRepository.get(targetId).then((response) => {
                this.avatarMediaItem = response;
            });
            this.user.avatarId = targetId;
        },

        onDropMedia(mediaItem) {
            this.setMediaItem({ targetId: mediaItem.id });
        },

        onSubmitConfirmPassword() {
            return this.loginService.verifyUserToken(this.confirmPassword).then((verifiedToken) => {
                if (!verifiedToken) {
                    return;
                }

                const authObject = {
                    ...this.loginService.getBearerAuthentication(),
                    ...{
                        access: verifiedToken,
                    },
                };

                this.loginService.setBearerAuthentication(authObject);

                this.confirmPasswordModal = false;
                this.isSaveSuccessful = false;
                this.isLoading = true;

                this.saveUser(verifiedToken);
            }).catch(() => {
                this.createErrorMessage(this.$tc('sw-profile.index.notificationOldPasswordErrorMessage'));
            }).finally(() => {
                this.confirmPassword = '';
            });
        },

        onCloseConfirmPasswordModal() {
            this.confirmPassword = '';
            this.confirmPasswordModal = false;
        },

        /* @feature-deprecated (FEATURE_NEXT_6040) tag:v6.5.0 - Will be removed */
        setMediaFromSidebar(mediaEntity) {
            this.avatarMediaItem = mediaEntity;
            this.user.avatarId = mediaEntity.id;
        },

        onUnlinkAvatar() {
            this.avatarMediaItem = null;
            this.user.avatarId = null;
        },

        /* @feature-deprecated (FEATURE_NEXT_6040) tag:v6.5.0 - Will be removed */
        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        },

        openMediaModal() {
            this.showMediaModal = true;
        },

        handleUserSaveError() {
            this.createNotificationError({
                message: this.$tc('sw-profile.index.notificationSaveErrorMessage'),
            });
            this.isLoading = false;
        },

        onChangeNewPassword(newPassword) {
            this.newPassword = newPassword;
        },

        onChangeNewPasswordConfirm(newPasswordConfirm) {
            this.newPasswordConfirm = newPasswordConfirm;
        },

        onMediaSelectionChange([mediaEntity]) {
            this.avatarMediaItem = mediaEntity;
            this.user.avatarId = mediaEntity.id;
        },

        getMediaDefaultFolderId() {
            return this.mediaDefaultFolderService.getDefaultFolderId('user');
        },

        saveUserSearchPreferences() {
            const value = this.searchPreferences.map(({ entityName, _searchable, fields }) => {
                return {
                    [entityName]: {
                        _searchable,
                        ...this.searchPreferencesService.processSearchPreferencesFields(fields),
                    },
                };
            });

            this.userSearchPreferences.value = value;
            this.searchRankingService.clearCacheUserSearchConfiguration();

            this.isLoading = true;
            this.isSaveSuccessful = false;
            return this.userConfigService.upsert({ [KEY_USER_SEARCH_PREFERENCE]: value })
                .then(() => {
                    this.isLoading = false;
                    this.isSaveSuccessful = true;
                })
                .catch((error) => {
                    this.isLoading = false;
                    this.isSaveSuccessful = false;
                    this.createNotificationError({ message: error.message });
                });
        },
    },
});
