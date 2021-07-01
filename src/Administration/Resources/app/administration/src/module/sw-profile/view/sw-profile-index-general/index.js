import template from './sw-profile-index-general.html.twig';

const { Component } = Shopware;

Component.register('sw-profile-index-general', {
    template,

    inject: ['acl'],

    props: {
        user: {
            type: Object,
            required: true,
        },
        languages: {
            type: Array,
            required: true,
        },
        newPassword: {
            type: String,
            required: false,
            default: null,
        },
        newPasswordConfirm: {
            type: String,
            required: false,
            default: null,
        },
        avatarMediaItem: {
            type: Object,
            required: false,
            default: null,
        },
        isUserLoading: {
            type: Boolean,
            required: true,
        },
        languageId: {
            type: String,
            required: false,
            default: null,
        },
        isDisabled: {
            type: Boolean,
            required: true,
        },
        userRepository: {
            type: Object,
            required: true,
        },
    },

    computed: {
        computedNewPassword: {
            get() {
                return this.newPassword;
            },
            set(newPassword) {
                this.$emit('new-password-change', newPassword);
            },
        },

        computedNewPasswordConfirm: {
            get() {
                return this.newPasswordConfirm;
            },
            set(newPasswordConfirm) {
                this.$emit('new-password-confirm-change', newPasswordConfirm);
            },
        },
    },

    methods: {
        onUploadMedia(media) {
            this.$emit('media-upload', { targetId: media.targetId });
        },

        onDropMedia(media) {
            this.$emit('media-upload', { targetId: media.id });
        },

        onRemoveMedia() {
            this.$emit('media-remove');
        },

        onOpenMedia() {
            this.$emit('media-open');
        },
    },
});
