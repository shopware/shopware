import { email } from 'src/core/service/validation.service';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-profile-index.html.twig';

const { Component, Mixin, State } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Component.getComponentHelper();
const types = Shopware.Utils.types;

Component.register('sw-profile-index', {
    template,

    inject: ['userService', 'loginService', 'repositoryFactory'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            user: { username: '', email: '' },
            languages: [],
            imageSize: 140,
            oldPassword: null, // @deprecated tag:v6.4.0 use confirmPassword instead
            newPassword: null,
            newPasswordConfirm: null,
            avatarMediaItem: null,
            uploadTag: 'sw-profile-upload-tag',
            isLoading: false,
            isUserLoading: true,
            isSaveSuccessful: false,
            confirmPasswordModal: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        ...mapPropertyErrors('user', [
            'email'
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

        userMediaCriteria() {
            if (this.user.id) {
                // ToDo: If SwSidebarMedia has the new data handling, change this too
                return CriteriaFactory.equals('userId', this.user.id);
            }

            return null;
        },

        languageId() {
            return Shopware.State.get('session').languageId;
        },

        confirmPassword: {
            get() {
                return this.oldPassword;
            },
            set(value) {
                this.oldPassword = value;
            }
        }
    },

    watch: {
        'user.avatarMedia'() {
            if (this.user.avatarMedia.id) {
                this.setMediaItem({ targetId: this.user.avatarMedia.id });
            }
        },

        languageId() {
            this.createdComponent();
        }
    },

    created() {
        this.createdComponent();
    },

    beforeMount() {
        this.beforeMountComponent();
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
                this.userPromise
            ];

            Promise.all(promises).then(() => {
                this.loadLanguages();
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

            return this.languageRepository.search(languageCriteria, Shopware.Context.api).then((result) => {
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

        async getUserData() {
            const routeUser = this.$route.params.user;
            if (routeUser) {
                return this.userRepository.get(routeUser.id, Shopware.Context.api);
            }

            const user = await this.userService.getUser();
            return this.userRepository.get(user.data.id, Shopware.Context.api);
        },

        async saveFinish() {
            this.isSaveSuccessful = false;
            this.user = await this.getUserData();
        },

        onSave() {
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

        /**
         * @deprecated tag:v6.4.0 will be remove because of password confirmation logic change
         */
        validateOldPassword() {
            return this.loginService.loginByUsername(this.user.username, this.oldPassword).then((response) => {
                return types.isString(response.access);
            }).catch(() => {
                return false;
            });
        },


        verifyUserToken() {
            const { username } = State.get('session').currentUser;

            return this.loginService.verifyUserByUsername(username, this.confirmPassword).then(({ access }) => {
                this.confirmPassword = '';

                if (types.isString(access)) {
                    return access;
                }

                return false;
            }).catch(() => {
                this.confirmPassword = '';
                this.createErrorMessage(this.$tc('sw-profile.index.notificationOldPasswordErrorMessage'));

                return false;
            });
        },

        createErrorMessage(errorMessage) {
            this.createNotificationError({
                title: this.$tc('sw-profile.index.notificationPasswordErrorTitle'),
                message: errorMessage
            });
        },

        saveUser(authToken) {
            const context = { ...Shopware.Context.api };
            context.authToken.access = authToken;

            this.userRepository.save(this.user, context).then(() => {
                this.$refs.mediaSidebarItem.getList();

                Shopware.Service('localeHelper').setLocaleWithId(this.user.localeId);

                if (this.newPassword) {
                    // re-issue a valid jwt token, as all user tokens were invalidated on password change
                    this.loginService.loginByUsername(this.user.username, this.newPassword).then(() => {
                        this.isLoading = false;
                        this.isSaveSuccessful = true;
                    }).catch(() => {
                        this.handleUserSaveError();
                    });
                } else {
                    this.isLoading = false;
                    this.isSaveSuccessful = true;
                }

                this.oldPassword = '';
                this.newPassword = '';
                this.newPasswordConfirm = '';
            }).catch(() => {
                this.handleUserSaveError();
            });
        },

        setMediaItem({ targetId }) {
            this.mediaRepository.get(targetId, Shopware.Context.api).then((response) => {
                this.avatarMediaItem = response;
            });
            this.user.avatarId = targetId;
        },

        onDropMedia(mediaItem) {
            this.setMediaItem({ targetId: mediaItem.id });
        },

        async onSubmitConfirmPassword() {
            const verifiedToken = await this.verifyUserToken();

            if (!verifiedToken) {
                return;
            }

            this.confirmPasswordModal = false;
            this.isSaveSuccessful = false;
            this.isLoading = true;

            this.saveUser(verifiedToken);
        },

        onCloseConfirmPasswordModal() {
            this.confirmPassword = '';
            this.confirmPasswordModal = false;
        },

        setMediaFromSidebar(mediaEntity) {
            this.avatarMediaItem = mediaEntity;
            this.user.avatarId = mediaEntity.id;
        },

        onUnlinkAvatar() {
            this.avatarMediaItem = null;
            this.user.avatarId = null;
        },

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        },

        handleUserSaveError() {
            this.createNotificationError({
                title: this.$tc('sw-profile.index.notificationPasswordErrorTitle'),
                message: this.$tc('sw-profile.index.notificationSaveErrorMessage')
            });
            this.isLoading = false;
        }
    }
});
