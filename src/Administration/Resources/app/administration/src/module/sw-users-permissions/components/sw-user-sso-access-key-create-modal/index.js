/**
 * @internal
 * @sw-package framework
 */
import template from './sw-user-sso-access-key-create-modal.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: [
        'access-key-modal-create:cancel',
        'access-key-modal-create:save',
        'access-key-modal-create:generate',
    ],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
        },

        isOpen: {
            type: Boolean,
            required: true,
        },

        accessKey: {
            type: String,
            required: true,
        },

        secretAccessKey: {
            type: String,
            required: true,
        },

        mode: {
            validator(value) {
                return [
                    'view',
                    'edit',
                    'create',
                ].includes(value);
            },
        },
    },

    methods: {
        onCancel() {
            this.$emit('access-key-modal-create:cancel');
        },

        onSave() {
            this.$emit('access-key-modal-create:save', {
                accessKey: this.accessKey,
                secretAccessKey: this.secretAccessKey,
            });
        },

        onGenerateNewAccessKey() {
            this.$emit('access-key-modal-create:generate');
        },
    },
};
