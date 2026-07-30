/**
 * @sw-package fundamentals@framework
 */
import template from './sw-profile-index-general.html.twig';

const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'ssoSettingsService',
    ],

    emits: [
        'new-password-change',
        'new-password-confirm-change',
        'user-theme-change',
        'media-upload',
        'media-remove',
        'media-open',
    ],

    created() {
        this.ssoSettingsService.isSso().then((isSso) => {
            this.showPasswordChangeCard = !isSso.isSso;
        });
    },

    data() {
        return {
            showPasswordChangeCard: true,
        };
    },

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
        userTheme: {
            type: String,
            required: false,
            default: 'system',
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

        localeOptions() {
            return this.languages.map((language) => {
                return {
                    id: language.locale.id,
                    value: language.locale.id,
                    label: language.customLabel,
                };
            });
        },

        computedUserTheme: {
            get() {
                return this.userTheme;
            },
            set(userTheme) {
                this.$emit('user-theme-change', userTheme);
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
