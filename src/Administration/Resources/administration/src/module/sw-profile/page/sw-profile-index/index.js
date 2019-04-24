import { Component, State, Mixin } from 'src/core/shopware';
import { email } from 'src/core/service/validation.service';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import types from 'src/core/service/utils/types.utils';
import template from './sw-profile-index.html.twig';

Component.register('sw-profile-index', {
    template,

    inject: ['userService', 'loginService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            userProfile: {},
            user: null,
            isUserLoading: true,
            imageSize: 140,
            oldPassword: null,
            newPassword: null,
            newPasswordConfirm: null,
            avatarMediaItem: null,
            uploadTag: 'sw-profile-upload-tag'
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        isDisabled() {
            return true; // TODO use ACL here with NEXT-1653
        },

        userStore() {
            return State.getStore('user');
        },

        mediaStore() {
            return State.getStore('media');
        },

        userMediaCriteria() {
            if (this.user.id) {
                return CriteriaFactory.equals('userId', this.user.id);
            }

            return null;
        }
    },

    watch: {
        'user.avatarMedia'() {
            if (this.user.avatarMedia.id) {
                this.setMediaItem({ targetId: this.user.avatarMedia.id });
            }
        }
    },

    created() {
        this.createdComponent();
    },

    beforeMount() {
        this.userPromise.then((user) => {
            this.user = user;
            this.isUserLoading = false;
        });
    },

    methods: {
        createdComponent() {
            this.isUserLoading = true;
            if (this.$route.params.user) {
                this.userPromise = this.setUserData(this.$route.params.user);
            } else {
                this.userPromise = this.userService.getUser().then((response) => {
                    return this.setUserData(response.data);
                });
            }
        },

        setUserData(userProfile) {
            this.userProfile = userProfile;
            return this.userStore.getByIdAsync(this.userProfile.id);
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

                    if (this.oldPassword === this.newPassword) {
                        this.createErrorMessage(this.$tc('sw-profile.index.notificationNewPasswordIsSameAsOldErrorMessage'));
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
                this.$refs.mediaSidebarItem.getList();
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
        },

        setMediaItem({ targetId }) {
            this.mediaStore.getByIdAsync(targetId).then((updatedMedia) => {
                this.avatarMediaItem = updatedMedia;
            });
            this.user.avatarId = targetId;
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
        }
    }
});
