/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

export default {
    name: 'SwMeteorEntityDataTableBulkDeleteModal',

    props: {
        selectionCount: {
            type: Number,
            required: true,
        },
        isDeleting: {
            type: Boolean,
            required: true,
        },
        titleText: {
            type: String,
            required: true,
        },
        confirmText: {
            type: String,
            required: true,
        },
        cancelText: {
            type: String,
            required: true,
        },
        deleteText: {
            type: String,
            required: true,
        },
    },

    emits: [
        'close',
        'confirm',
    ],

    methods: {
        closeModal() {
            this.$emit('close');
        },

        deleteItems() {
            this.$emit('confirm');
        },
    },

    template: `
        <sw-modal
            class="sw-meteor-entity-data-table-bulk-delete-modal"
            :title="titleText"
            variant="small"
            @modal-close="closeModal"
        >
            <p class="sw-data-grid__confirm-bulk-delete-text sw-meteor-entity-data-table-bulk-delete-modal__text">
                <slot name="bulk-modal-delete-confirm-text" :selection-count="selectionCount">
                    {{ confirmText }}
                </slot>
            </p>

            <template #modal-footer>
                <slot
                    name="bulk-modal-cancel"
                    :close-modal="closeModal"
                >
                    <mt-button
                        class="sw-meteor-entity-data-table-bulk-delete-modal__cancel"
                        size="small"
                        variant="secondary"
                        @click="closeModal"
                    >
                        {{ cancelText }}
                    </mt-button>
                </slot>

                <slot
                    name="bulk-modal-delete-items"
                    :is-bulk-loading="isDeleting"
                    :delete-items="deleteItems"
                >
                    <mt-button
                        class="sw-meteor-entity-data-table-bulk-delete-modal__confirm"
                        variant="critical"
                        size="small"
                        :is-loading="isDeleting"
                        @click="deleteItems"
                    >
                        {{ deleteText }}
                    </mt-button>
                </slot>
            </template>
        </sw-modal>
    `,
};
