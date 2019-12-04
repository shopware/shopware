import template from './sw-settings-user-detail.html.twig';
import './sw-settings-user-detail.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { warn } = Shopware.Utils.debug;

Component.register('sw-settings-user-detail', {
    template,

    inject: ['userService', 'userValidationService', 'integrationService', 'repositoryFactory'],

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
            changePasswordModal: false,
            newPassword: '',
            isEmailUsed: false,
            isUsernameUsed: false,
            isIntegrationsLoading: false,
            isSaveSuccessful: false,
            isModalLoading: false,
            showSecretAccessKey: false,
            showDeleteModal: null,
            skeletonItemAmount: 3
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.fullName;
        },

        fullName() {
            return this.salutation(this.user, this.$tc('sw-settings-user.user-detail.labelNewUser'));
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
            return this.newPassword === '' || this.newPassword === null;
        },

        isCurrentUser() {
            if (!this.user || !this.currentUser) {
                return true;
            }
            return this.userId === this.currentUser.id;
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        integrationColumns() {
            return [{
                property: 'accessKey',
                label: this.$tc('sw-settings-user.user-detail.labelAccessKey')
            }, {
                property: 'writeAccess',
                label: this.$tc('sw-settings-user.user-detail.labelPermissions')
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
                return;
            }

            const languagePromise = new Promise((resolve) => {
                Shopware.StateDeprecated.getStore('language').setCurrentId(this.languageId);
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
            const languageCriteria = new Criteria();
            languageCriteria.addAssociation('locale');
            languageCriteria.addSorting(Criteria.sort('locale.name', 'ASC'));
            languageCriteria.addSorting(Criteria.sort('locale.territory', 'ASC'));
            languageCriteria.limit = 500;

            return this.languageRepository.search(languageCriteria, Shopware.Context.api).then((result) => {
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

            const criteria = new Criteria();
            criteria.addAssociation('accessKeys');
            criteria.addAssociation('locale');

            return this.userRepository.get(this.userId, Shopware.Context.api, criteria).then((user) => {
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
            return this.keyRepository.search(new Criteria(), Shopware.Context.api).then((accessKeys) => {
                this.integrations = accessKeys;
            });
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

        onSortColumn(column) {
            if (this.sortBy === column.dataIndex) {
                this.sortDirection = (this.sortDirection === 'ASC' ? 'DESC' : 'ASC');
            } else {
                this.sortDirection = 'ASC';
                this.sortBy = column.dataIndex;
            }
            this.loadKeys();
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;
            let promises = [];

            if (this.currentUser.id === this.user.id) {
                promises = [this.localeRepository.get(this.user.localeId, Shopware.Context.api).then(({ code }) => {
                    Shopware.State.dispatch('setAdminLocale', code);
                })];
            }

            return Promise.all(promises).then(this.checkEmail().then(() => {
                if (!this.isEmailUsed) {
                    this.isLoading = true;
                    const titleSaveError = this.$tc('sw-settings-user.user-detail.notification.saveError.title');
                    const messageSaveError = this.$tc(
                        'sw-settings-user.user-detail.notification.saveError.message', 0, { name: this.fullName }
                    );

                    return this.userRepository.save(this.user, Shopware.Context.api).then(() => {
                        this.isLoading = false;
                        this.isSaveSuccessful = true;
                    }).catch((exception) => {
                        this.createNotificationError({
                            title: titleSaveError,
                            message: messageSaveError
                        });
                        warn(this._name, exception.message, exception.response);
                        this.isLoading = false;
                        throw exception;
                    });
                }
                return Promise.resolve();
            }));
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.user.list' });
        },

        onChangePassword() {
            this.changePasswordModal = true;
        },

        onClosePasswordModal() {
            this.newPassword = '';
            this.changePasswordModal = false;
        },

        onSubmit() {
            this.changePasswordModal = false;
            this.user.password = this.newPassword;
            this.newPassword = '';
            this.onSave();
        },

        onShowDetailModal(id) {
            if (!id) {
                this.addAccessKey();
                return;
            }

            this.keyRepository.get(id, Shopware.Context.api).then((entity) => {
                this.currentIntegration = entity;
            });
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
            this.keyRepository.save(this.currentIntegration, Shopware.Context.api).then(this.loadKeys);
            this.onCloseDetailModal();
        },

        onCloseDeleteModal() {
            this.showDeleteModal = null;
        },

        onConfirmDelete(id) {
            if (!id) {
                return false;
            }

            this.onCloseDeleteModal();
            return this.keyRepository.delete(id, Shopware.Context.api).then(this.loadKeys);
        }
    }
});
