import { email } from 'src/core/service/validation.service';
import template from './sw-profile-index.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Component.getComponentHelper();

Component.register('sw-profile-index', {
    template,

    inject: ['userService', 'loginService', 'repositoryFactory', 'acl', 'feature'],

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
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        ...mapPropertyErrors('user', [
            'email',
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
        'user.avatarMedia'() {
            if (this.user.avatarMedia.id) {
                this.setMediaItem({ targetId: this.user.avatarMedia.id });
            }
        },

        languageId() {
            this.createdComponent();
        },
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
                this.userPromise,
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

        async getUserData() {
            const routeUser = this.$route.params.user;
            if (routeUser) {
                return this.userRepository.get(routeUser.id);
            }

            const user = await this.userService.getUser();
            return this.userRepository.get(user.data.id);
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

        createErrorMessage(errorMessage) {
            this.createNotificationError({
                message: errorMessage,
            });
        },

        saveUser(authToken) {
            if (!this.acl.can('user:editor')) {
                const changes = this.userRepository.getSyncChangeset([this.user]);
                delete changes.changeset[0].changes.id;

                this.userService.updateUser(changes.changeset[0].changes).then(() => {
                    this.isLoading = false;
                    this.isSaveSuccessful = true;

                    Shopware.Service('localeHelper').setLocaleWithId(this.user.localeId);
                });

                return;
            }

            const context = { ...Shopware.Context.api };
            context.authToken.access = authToken;

            this.userRepository.save(this.user, context).then(() => {
                this.$refs.mediaSidebarItem.getList();

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
    },
});
