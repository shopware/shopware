import { Component, Mixin } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import { warn } from 'src/core/service/utils/debug.utils';
import template from './sw-settings-user-detail.html.twig';
import './sw-settings-user-detail.scss';

Component.register('sw-settings-user-detail', {
    template,

    inject: ['userService', 'userValidationService', 'integrationService', 'repositoryFactory', 'context'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation')
    ],

    data() {
        return {
            currentUser: null,
            userId: '',
            user: null,
            mediaItem: null,
            changePasswordModal: false,
            newPassword: '',
            isEmailUsed: false,
            isUsernameUsed: false,
            integrations: [],
            isIntegrationsLoading: false,
            currentIntegration: null,
            isModalLoading: false,
            showSecretAccessKey: false,
            showDeleteModal: null
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.salutation(this.user);
        },

        username() {
            return this.salutation(this.user, this.$tc('sw-settings-user.user-detail.labelNewUser'));
        },

        avatarMedia() {
            return this.mediaItem;
        },

        isLoading() {
            if (!this.user) {
                return true;
            }
            return this.user.isLoading;
        },

        isError() {
            return this.isEmailUsed || this.isUsernameUsed;
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
                dataIndex: 'accessKey',
                label: this.$tc('sw-settings-user.user-detail.labelAccessKey')
            }, {
                property: 'writeAccess',
                dataIndex: 'writeAccess',
                label: this.$tc('sw-settings-user.user-detail.labelPermissions')
            }];
        },

        secretAccessKeyFieldType() {
            return this.showSecretAccessKey ? 'text' : 'password';
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const searchCriteria = new Criteria();
            searchCriteria.setIds([this.userId]);
            searchCriteria.addAssociation('accessKeys');

            this.repository = this.repositoryFactory.create('user');
            this.userId = this.$route.params.id;
            this.repository.search(searchCriteria, this.context).then((searchResult) => {
                const user = searchResult.get(this.userId);
                this.user = user;
                if (this.user.avatarId) {
                    this.mediaItem = this.user.avatarMedia;
                }

                this.keyRepository = this.repositoryFactory.create(user.accessKeys.entity, this.user.accessKeys.source);
                this.loadKeys();
            });

            this.userService.getUser().then((response) => {
                this.currentUser = response.data;
            });
        },

        loadKeys() {
            this.keyRepository.search(new Criteria(), this.context).then((accessKeys) => {
                this.integrations = accessKeys.items;
            });
        },

        addAccessKey() {
            const newKey = this.keyRepository.create(this.context);

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
            this.mediaRepository.get(targetId, this.context).then((media) => {
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
            this.checkEmail().then(() => {
                if (!this.isEmailUsed) {
                    const userName = this.username;
                    const titleSaveSuccess = this.$tc('sw-settings-user.user-detail.notification.saveSuccess.title');
                    const messageSaveSuccess = this.$tc('sw-settings-user.user-detail.notification.saveSuccess.message',
                        0,
                        { name: userName });
                    const titleSaveError = this.$tc('sw-settings-user.user-detail.notification.saveError.title');
                    const messageSaveError = this.$tc(
                        'sw-settings-user.user-detail.notification.saveError.message', 0, { name: userName }
                    );

                    return this.repository.save(this.user, this.context).then(() => {
                        this.createNotificationSuccess({
                            title: titleSaveSuccess,
                            message: messageSaveSuccess
                        });
                    }).catch((exception) => {
                        this.createNotificationError({
                            title: titleSaveError,
                            message: messageSaveError
                        });
                        warn(this._name, exception.message, exception.response);
                        throw exception;
                    });
                }
                return Promise.resolve();
            });
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

            this.keyRepository.get(id, this.context).then((entity) => {
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
            this.keyRepository.save(this.currentIntegration, this.context).then(this.loadKeys);
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
            return this.keyRepository.delete(id, this.context).then(this.loadKeys);
        }
    }
});
