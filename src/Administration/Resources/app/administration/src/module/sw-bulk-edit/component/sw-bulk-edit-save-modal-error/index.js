/**
 * @sw-package framework
 */
import template from './sw-bulk-edit-save-modal-error.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: [
        'title-set',
        'buttons-update',
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateButtons();
            this.setTitle();
        },

        setTitle() {
            this.$emit('title-set', this.$t('sw-bulk-edit.modal.error.title'));
        },

        updateButtons() {
            const buttonConfig = [
                {
                    key: 'close',
                    label: this.$t('global.sw-modal.labelClose'),
                    position: 'right',
                    variant: 'primary',
                    action: '',
                    disabled: false,
                },
            ];

            this.$emit('buttons-update', buttonConfig);
        },
    },
};
