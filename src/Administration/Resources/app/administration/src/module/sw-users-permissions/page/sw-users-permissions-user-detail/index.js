import { email } from 'src/core/service/validation.service';
import template from './sw-users-permissions-user-detail.html.twig';
import './sw-users-permissions-user-detail.scss';

const { Component, Mixin, State } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Component.getComponentHelper();
const { warn } = Shopware.Utils.debug;
const types = Shopware.Utils.types;

Component.register('sw-users-permissions-user-detail', {
    template,

    inject: [
        'userService',
        'loginService',
        'userValidationService',
        'integrationService',
        'repositoryFactory'
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation')
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel'
    },

    data() {
        return {
            isLoading: false,
            userId: '',
            user: null,
            currentUser: null,
            languages: [],
            integrations: [],
            currentIntegration: null,
            mediaItem: null,

            // @deprecated tag:v6.4.0 will be removed by changing the password confirmation logic
            changePasswordModal: false,
            newPassword: '',
            newPasswordConfirm: '',
            isEmailUsed: false,
            isUsernameUsed: false,
            isIntegrationsLoading: false,
            isSaveSuccessful: false,
            isModalLoading: false,
            showSecretAccessKey: false,
            showDeleteModal: null,
            skeletonItemAmount: 3,
            confirmPasswordModal: false,
            confirmPassword: ''
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        ...mapPropertyErrors('user', [
            'firstName',
            'lastName',
            'email',
            'username',
            'localeId'
        ]),

        identifier() {
            return this.fullName;
        },

        fullName() {
            return this.salutation(this.user, this.$tc('sw-users-permissions.users.user-detail.labelNewUser'));
        },

        userRepository() {
            return this.repositoryFactory.create('user');
        },

        userCriteria() {
            const criteria = new Criteria();

            criteria.addAssociation('accessKeys');
            criteria.addAssociation('locale');
            criteria.addAssociation('aclRoles');

            return criteria;
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        languageCriteria() {
            const criteria = new Criteria();

            criteria.addAssociation('locale');
            criteria.addSorting(Criteria.sort('locale.name', 'ASC'));
            criteria.addSorting(Criteria.sort('locale.territory', 'ASC'));
            criteria.limit = 500;

            return criteria;
        },

        localeRepository() {
            return this.repositoryFactory.create('locale');
        },

        avatarMedia() {
            return this.mediaItem;
        },

        isError() {
            return this.isEmailUsed || this.isUsernameUsed || !this.hasLanguage;
        },

        hasLanguage() {
            return this.user && this.user.localeId;
        },

        disableConfirm() {
            return this.newPassword !== this.newPasswordConfirm || this.newPassword === '' || this.newPassword === null;
        },

        isCurrentUser() {
            if (!this.user || !this.currentUser) {
                return false;
            }

            return this.userId === this.currentUser.id;
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        integrationColumns() {
            return [{
                property: 'accessKey',
                label: this.$tc('sw-users-permissions.users.user-detail.labelAccessKey')
            }, {
                property: 'writeAccess',
                label: this.$tc('sw-users-permissions.users.user-detail.labelPermissions')
            }];
        },

        secretAccessKeyFieldType() {
            return this.showSecretAccessKey ? 'text' : 'password';
        },

        languageId() {
            return Shopware.State.get('session').languageId;
        },

        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light'
            };
        }
    },

    watch: {
        languageId() {
            this.createdComponent();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            if (!this.languageId) {
                this.isLoading = false;
                return;
            }

            const languagePromise = new Promise((resolve) => {
                Shopware.State.commit('context/setApiLanguageId', this.languageId);
                resolve(this.languageId);
            });

            const promises = [
                languagePromise,
                this.loadLanguages(),
                this.loadUser(),
                this.loadCurrentUser()
            ];

            Promise.all(promises).then(() => {
                this.isLoading = false;
            });
        },

        loadLanguages() {
            return this.languageRepository.search(this.languageCriteria, Shopware.Context.api).then((result) => {
                this.languages = [];
                result.forEach((lang) => {
                    lang.customLabel = `${lang.locale.translated.name} (${lang.locale.translated.territory})`;
                    this.languages.push(lang);
                });

                return this.languages;
            });
        },

        loadUser() {
            this.userId = this.$route.params.id;

            return this.userRepository.get(this.userId, Shopware.Context.api, this.userCriteria).then((user) => {
                this.user = user;

                if (this.user.avatarId) {
                    this.mediaItem = this.user.avatarMedia;
                }

                this.keyRepository = this.repositoryFactory.create(user.accessKeys.entity, this.user.accessKeys.source);
                this.loadKeys();
            });
        },

        loadCurrentUser() {
            return this.userService.getUser().then((response) => {
                this.currentUser = response.data;
            });
        },

        loadKeys() {
            this.integrations = this.user.accessKeys;
        },

        addAccessKey() {
            const newKey = this.keyRepository.create(Shopware.Context.api);

            this.isModalLoading = true;
            newKey.quantityStart = 1;
            this.integrationService.generateKey().then((response) => {
                newKey.accessKey = response.accessKey;
                newKey.secretAccessKey = response.secretAccessKey;
                newKey.writeAccess = false;
                this.currentIntegration = newKey;
                this.isModalLoading = false;
                this.showSecretAccessKey = true;
            });
        },

        checkEmail() {
            if (this.user.email && !email(this.user.email)) {
                this.createNotificationError({
                    title: this.$tc('global.defaul.error'),
                    message: this.$tc(
                        'sw-users-permissions.users.user-detail.notification.invalidEmailErrorMessage'
                    )
                });
                return Promise.reject();
            }

            return this.userValidationService.checkUserEmail({
                email: this.user.email,
                id: this.user.id
            }).then(({ emailIsUnique }) => {
                this.isEmailUsed = !emailIsUnique;
            });
        },

        checkUsername() {
            return this.userValidationService.checkUserUsername({
                username: this.user.username,
                id: this.user.id
            }).then(({ usernameIsUnique }) => {
                this.isUsernameUsed = !usernameIsUnique;
            });
        },

        setMediaItem({ targetId }) {
            this.mediaRepository.get(targetId, Shopware.Context.api).then((media) => {
                this.mediaItem = media;
                this.user.avatarMedia = media;
                this.user.avatarId = targetId;
            });
        },

        onUnlinkLogo() {
            this.mediaItem = null;
            this.user.avatarMedia = null;
            this.user.avatarId = null;
        },

        onSearch(value) {
            this.term = value;
            this.clearSelection();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.confirmPasswordModal = true;
        },

        saveUser(authToken) {
            this.isSaveSuccessful = false;
            this.isLoading = true;
            let promises = [];

            if (this.currentUser.id === this.user.id) {
                promises = [Shopware.Service('localeHelper').setLocaleWithId(this.user.localeId)];
            }

            if (!this.user.title || this.user.title.length <= 0) {
                const firstRole = this.user.aclRoles.first();

                if (firstRole) {
                    this.user.title = firstRole.name;
                }
            }

            return Promise.all(promises).then(this.checkEmail().then(() => {
                if (!this.isEmailUsed) {
                    this.isLoading = true;
                    const titleSaveError = this.$tc('global.default.error');
                    const messageSaveError = this.$tc(
                        'sw-users-permissions.users.user-detail.notification.saveError.message', 0, { name: this.fullName }
                    );

                    const context = { ...Shopware.Context.api };
                    context.authToken.access = authToken;

                    return this.userRepository.save(this.user, context).then(() => {
                        return this.updateCurrentUser();
                    }).then(() => {
                        this.createdComponent();

                        this.isSaveSuccessful = true;
                    }).catch((exception) => {
                        this.createNotificationError({
                            title: titleSaveError,
                            message: messageSaveError
                        });
                        warn(this._name, exception.message, exception.response);
                        this.isLoading = false;
                        throw exception;
                    })
                        .finally(() => {
                            this.isLoading = false;
                        });
                }

                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('sw-users-permissions.users.user-detail.notification.duplicateEmailErrorMessage')
                });

                return Promise.resolve();
            }).finally(() => {
                this.isLoading = false;
            }));
        },

        updateCurrentUser() {
            return this.userService.getUser().then((response) => {
                const data = response.data;
                delete data.password;

                return Shopware.State.commit('setCurrentUser', data);
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.users.permissions.index' });
        },

        /**
         * @deprecated tag:v6.4.0
         */
        onChangePassword() {
            this.changePasswordModal = true;
        },

        /**
         * @deprecated tag:v6.4.0
         */
        onClosePasswordModal() {
            this.newPassword = '';
            this.newPasswordConfirm = '';
            this.changePasswordModal = false;
        },

        /**
         * @deprecated tag:v6.4.0
         */
        async onSubmit() {
            this.user.password = this.newPassword;
            this.newPassword = '';
            this.newPasswordConfirm = '';
            await this.onSave();
            this.user.password = '';
            this.changePasswordModal = false;
        },

        onShowDetailModal(id) {
            if (!id) {
                this.addAccessKey();
                return;
            }

            this.currentIntegration = this.user.accessKeys.get(id);
        },

        onCloseDetailModal() {
            this.currentIntegration = null;
            this.showSecretAccessKey = false;
            this.isModalLoading = false;
        },

        onSaveIntegration() {
            if (!this.currentIntegration) {
                return;
            }

            if (!this.user.accessKeys.has(this.currentIntegration.id)) {
                this.user.accessKeys.add(this.currentIntegration);
            }

            this.onCloseDetailModal();
        },

        onCloseDeleteModal() {
            this.showDeleteModal = null;
        },

        onConfirmDelete(id) {
            if (!id) {
                return;
            }

            this.onCloseDeleteModal();
            this.user.accessKeys.remove(id);
        },

        async onSubmitConfirmPassword() {
            const verifiedToken = await this.verifyUserToken();

            if (!verifiedToken) {
                return;
            }

            await this.saveUser(verifiedToken);

            this.confirmPasswordModal = false;
        },

        onCloseConfirmPasswordModal() {
            this.confirmPassword = '';
            this.confirmPasswordModal = false;
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
                this.createNotificationError({
                    title: this.$tc('sw-settings-user.user-detail.passwordConfirmation.notificationPasswordErrorTitle'),
                    message: this.$tc('sw-settings-user.user-detail.passwordConfirmation.notificationPasswordErrorMessage')
                });

                return false;
            });
        }
    }
});
