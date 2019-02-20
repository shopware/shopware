import { Component, State, Mixin } from 'src/core/shopware';
import { email } from 'src/core/service/validation.service';
import types from 'src/core/service/utils/types.utils';
import template from './sw-profile-index.html.twig';
import './sw-profile-index.scss';

Component.register('sw-profile-index', {
    template,

    inject: ['userService', 'loginService'],

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
            oldPassword: null,
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
            if (this.checkEmail() === false) {
                return;
            }

            const passwordCheck = this.checkPassword();
            if (passwordCheck === null) {
                this.saveUser();
            } else {
                passwordCheck.then((validNewPassword) => {
                    if (validNewPassword) {
                        this.saveUser();
                    }
                });
            }
        },

        checkEmail() {
            if (!email(this.user.email)) {
                this.createErrorMessage(this.$tc('sw-profile.index.notificationInvalidEmailErrorMessage'));

                return false;
            }

            return true;
        },

        checkPassword() {
            if (this.newPassword && this.newPassword.length > 0) {
                return this.validateOldPassword().then((oldPasswordIsValid) => {
                    if (oldPasswordIsValid === false) {
                        this.createErrorMessage(this.$tc('sw-profile.index.notificationOldPasswordErrorMessage'));
                        return false;
                    }

                    if (this.newPassword !== this.newPasswordConfirm) {
                        this.createErrorMessage(this.$tc('sw-profile.index.notificationPasswordErrorMessage'));
                        return false;
                    }

                    this.user.password = this.newPassword;

                    return true;
                });
            }

            return null;
        },

        validateOldPassword() {
            return this.loginService.loginByUsername(this.user.username, this.oldPassword).then((response) => {
                return types.isString(response.access);
            }).catch(() => {
                return false;
            });
        },

        createErrorMessage(errorMessage) {
            this.createNotificationError({
                title: this.$tc('sw-profile.index.notificationPasswordErrorTitle'),
                message: errorMessage
            });
        },

        saveUser() {
            this.user.save().then(() => {
                const successTitle = this.$tc('sw-profile.index.notificationSaveSuccessTitle');
                const successMessage = this.$tc('sw-profile.index.notificationSaveSuccessMessage');

                this.oldPassword = '';
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
