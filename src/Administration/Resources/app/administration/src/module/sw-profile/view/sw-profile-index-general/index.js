/**
 * @package system-settings
 */
import template from './sw-profile-index-general.html.twig';

const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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
        timezoneOptions: {
            type: Array,
            required: true,
        },
    },

    computed: {
        ...mapPropertyErrors('user', [
            'password',
        ]),

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
};
