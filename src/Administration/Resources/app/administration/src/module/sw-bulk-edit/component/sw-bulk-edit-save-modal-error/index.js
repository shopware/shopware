/**
 * @sw-package framework
 */
import template from './sw-bulk-edit-save-modal-error.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        failedItems: {
            type: Array,
            required: false,
            default: () => [],
        },
    },

    emits: [
        'title-set',
        'buttons-update',
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        getFailureReason(failedItem) {
            const snippets = {
                'not-found': 'sw-bulk-edit.modal.error.orderNotFound',
                load: 'sw-bulk-edit.modal.error.orderLoadFailed',
                transition: 'sw-bulk-edit.modal.error.transitionFailed',
            };

            return this.$t(snippets[failedItem.reason] ?? snippets.transition);
        },

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
                    label: this.$t('global.default.close'),
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
