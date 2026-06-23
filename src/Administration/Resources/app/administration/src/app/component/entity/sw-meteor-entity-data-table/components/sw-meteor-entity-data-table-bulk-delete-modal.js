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

    template: `
        <div class="sw-meteor-entity-data-table-bulk-delete-modal">
            <p class="sw-meteor-entity-data-table-bulk-delete-modal__text">
                <slot name="bulk-modal-delete-confirm-text" :selection-count="selectionCount">
                    {{ confirmText }}
                </slot>
            </p>

            <button
                class="sw-meteor-entity-data-table-bulk-delete-modal__cancel"
                type="button"
                @click="$emit('close')"
            >
                {{ cancelText }}
            </button>

            <button
                class="sw-meteor-entity-data-table-bulk-delete-modal__confirm"
                type="button"
                :disabled="isDeleting || undefined"
                @click="$emit('confirm')"
            >
                {{ deleteText }}
            </button>
        </div>
    `,
};
