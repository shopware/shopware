/**
 * @package system-settings
 */
import { email } from 'src/core/service/validation.service';
import template from './sw-users-permissions-user-detail.html.twig';
import './sw-users-permissions-user-detail.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Component.getComponentHelper();
const { warn } = Shopware.Utils.debug;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'userService',
        'loginService',
        'userValidationService',
        'integrationService',
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel',
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
            timezoneOptions: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        ...mapPropertyErrors('user', [
            'firstName',
            'lastName',
            'email',
            'username',
            'localeId',
            'password',
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
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('accessKeys');
            criteria.addAssociation('locale');
            criteria.addAssociation('aclRoles');

            return criteria;
        },

        aclRoleCriteria() {
            const criteria = new Criteria(1, 25);

            // Roles created by apps should not be assignable in the admin
            criteria.addFilter(Criteria.equals('app.id', null));
            criteria.addFilter(Criteria.equals('deletedAt', null));

            return criteria;
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        languageCriteria() {
            const criteria = new Criteria(1, 500);

            criteria.addAssociation('locale');
            criteria.addSorting(Criteria.sort('locale.name', 'ASC'));
            criteria.addSorting(Criteria.sort('locale.territory', 'ASC'));

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
                label: this.$tc('sw-users-permissions.users.user-detail.labelAccessKey'),
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
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },
    },

    watch: {
        languageId() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Shopware.ExtensionAPI.publishData({
                id: 'sw-users-permissions-user-detail__currentUser',
                path: 'currentUser',
                scope: this,
            });
            this.isLoading = true;

            if (!this.languageId) {
                this.isLoading = false;
                return;
            }

            this.timezoneOptions = Shopware.Service('timezoneService').getTimezoneOptions();
            const languagePromise = new Promise((resolve) => {
                Shopware.State.commit('context/setApiLanguageId', this.languageId);
                resolve(this.languageId);
            });

            const promises = [
                languagePromise,
                this.loadLanguages(),
                this.loadUser(),
                this.loadCurrentUser(),
            ];

            Promise.all(promises).then(() => {
                this.isLoading = false;
            });
        },

        // @deprecated tag:v6.6.0 - Unused
        loadTimezones() {
        },

        loadLanguages() {
            return this.languageRepository.search(this.languageCriteria).then((result) => {
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
            const newKey = this.keyRepository.create();

            this.isModalLoading = true;
            newKey.quantityStart = 1;
            this.integrationService.generateKey({}, {}, true).then((response) => {
                newKey.accessKey = response.accessKey;
                newKey.secretAccessKey = response.secretAccessKey;
                this.currentIntegration = newKey;
                this.isModalLoading = false;
                this.showSecretAccessKey = true;
            });
        },

        checkEmail() {
            if (!this.user.email) {
                return Promise.resolve();
            }

            if (!email(this.user.email)) {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc(
                        'sw-users-permissions.users.user-detail.notification.invalidEmailErrorMessage',
                    ),
                });
                return Promise.reject();
            }

            return this.userValidationService.checkUserEmail({
                email: this.user.email,
                id: this.user.id,
            }).then(({ emailIsUnique }) => {
                this.isEmailUsed = !emailIsUnique;
            });
        },

        checkUsername() {
            return this.userValidationService.checkUserUsername({
                username: this.user.username,
                id: this.user.id,
            }).then(({ usernameIsUnique }) => {
                this.isUsernameUsed = !usernameIsUnique;
            });
        },

        setMediaItem({ targetId }) {
            this.mediaRepository.get(targetId).then((media) => {
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

        saveUser(context) {
            this.isSaveSuccessful = false;
            this.isLoading = true;
            let promises = [];

            if (this.currentUser.id === this.user.id) {
                promises = [Shopware.Service('localeHelper').setLocaleWithId(this.user.localeId)];
            }

            return Promise.all(promises).then(
                this.checkEmail()
                    .then(() => {
                        if (!this.isEmailUsed) {
                            this.isLoading = true;
                            const titleSaveError = this.$tc('global.default.error');
                            const messageSaveError = this.$tc(
                                'sw-users-permissions.users.user-detail.notification.saveError.message',
                                0,
                                { name: this.fullName },
                            );

                            return this.userRepository.save(this.user, context).then(() => {
                                return this.updateCurrentUser();
                            }).then(() => {
                                this.createdComponent();

                                this.confirmPasswordModal = false;
                                this.isSaveSuccessful = true;
                            }).catch((exception) => {
                                this.createNotificationError({
                                    title: titleSaveError,
                                    message: messageSaveError,
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
                            message: this.$tc(
                                'sw-users-permissions.users.user-detail.notification.duplicateEmailErrorMessage',
                            ),
                        });

                        return Promise.resolve();
                    })
                    .catch(() => Promise.reject())
                    .finally(() => {
                        this.isLoading = false;
                    }),
            );
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

        setPassword(password) {
            if (typeof password === 'string' && password.length <= 0) {
                this.$delete(this.user, 'password');
                return;
            }

            this.$set(this.user, 'password', password);
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

        onCloseConfirmPasswordModal() {
            this.confirmPasswordModal = false;
        },
    },
};
