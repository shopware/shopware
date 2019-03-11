import { Component, Mixin, State } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import template from './sw-settings-user-detail.html.twig';
import './sw-settings-user-detail.scss';

Component.register('sw-settings-user-detail', {
    template,

    inject: ['userService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {

    },

    data() {
        return {
            currentUser: null,
            userId: '',
            user: null,
            mediaItem: null,
            changePasswordModal: false,
            newPassword: ''
        };
    },

    computed: {
        userStore() {
            return State.getStore('user');
        },

        mediaStore() {
            return State.getStore('media');
        },

        username() {
            if (this.user) {
                return `${this.user.firstName} ${this.user.lastName} `;
            }

            return '';
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

        disableConfirm() {
            return this.newPassword === '' || this.newPassword === null;
        },

        isCurrentUser() {
            if (!this.user || !this.currentUser) {
                return true;
            }
            return this.userId === this.currentUser.id;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.userId = this.$route.params.id;
            this.userStore.getByIdAsync(this.userId).then((user) => {
                this.user = user;
                if (this.user.avatarId) {
                    this.mediaItem = this.user.avatarMedia;
                }
            });

            this.userService.getUser().then((response) => {
                this.currentUser = response.data;
            });
        },

        setMediaItem({ targetId }) {
            this.mediaStore.getByIdAsync(targetId).then((updatedMedia) => {
                this.mediaItem = updatedMedia;
                this.user.avatarId = targetId;
            });
        },

        onUnlinkLogo() {
            this.mediaItem = null;
            this.user.avatarId = null;
        },

        onSearch(value) {
            this.term = value;
            this.clearSelection();
        },

        onSave() {
            const userName = this.username;
            const titleSaveSuccess = this.$tc('sw-settings-user.user-detail.notification.saveSuccess.title');
            const messageSaveSuccess = this.$tc('sw-settings-user.user-detail.notification.saveSuccess.message',
                0,
                { name: userName });
            const titleSaveError = this.$tc('sw-settings-user.user-detail.notification.saveError.title');
            const messageSaveError = this.$tc(
                'sw-settings-user.user-detail.notification.saveError.message', 0, { name: userName }
            );

            return this.user.save().then(() => {
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
        }
    }
});
