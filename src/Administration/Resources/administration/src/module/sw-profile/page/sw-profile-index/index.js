import { Component, State, Mixin } from 'src/core/shopware';
import { md5 } from 'src/core/service/utils/format.utils';
import template from './sw-profile-index.html.twig';
import './sw-profile-index.less';

Component.register('sw-profile-index', {
    template,

    inject: ['userService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            userProfile: {},
            user: {
                isLoading: true
            },
            imageSize: 140,
            newPassword: null,
            newPasswordConfirm: null
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        userStore() {
            return State.getStore('user');
        },

        md5mail() {
            return (this.user.email) ? md5(this.user.email) : '';
        },

        gravatarImage() {
            return `https://www.gravatar.com/avatar/${this.md5mail}?s=${this.imageSize}`;
        }
    },

    methods: {
        createdComponent() {
            if (this.$route.params.user) {
                this.userProfile = this.$route.params.user;
                this.user = this.userStore.getById(this.userProfile.id);
            } else {
                this.userService.getUser().then((response) => {
                    this.userProfile = response.data;
                    this.user = this.userStore.getById(this.userProfile.id);
                });
            }
        },

        onSave() {
            if (this.newPassword && this.newPassword.length > 0) {
                if (this.newPassword !== this.newPasswordConfirm) {
                    const errorTitle = this.$tc('sw-profile.index.notificationPasswordErrorTitle');
                    const errorMessage = this.$tc('sw-profile.index.notificationPasswordErrorMessage');

                    this.createNotificationError({
                        title: errorTitle,
                        message: errorMessage
                    });

                    return;
                }

                this.user.password = this.newPassword;
            }

            this.user.save().then(() => {
                const successTitle = this.$tc('sw-profile.index.notificationSaveSuccessTitle');
                const successMessage = this.$tc('sw-profile.index.notificationSaveSuccessMessage');

                this.newPassword = '';
                this.newPasswordConfirm = '';

                this.createNotificationSuccess({
                    title: successTitle,
                    message: successMessage
                });
            });
        }
    }
});
