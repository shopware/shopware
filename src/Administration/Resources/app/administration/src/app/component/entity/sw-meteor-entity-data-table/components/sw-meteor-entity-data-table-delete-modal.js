/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

export default {
    name: 'SwMeteorEntityDataTableDeleteModal',

    props: {
        item: {
            type: Object,
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
        <div class="sw-meteor-entity-data-table-delete-modal">
            <p class="sw-meteor-entity-data-table-delete-modal__text">
                <slot name="delete-confirm-text" :item="item">
                    {{ confirmText }}
                </slot>
            </p>

            <button
                class="sw-meteor-entity-data-table-delete-modal__cancel"
                type="button"
                @click="$emit('close')"
            >
                {{ cancelText }}
            </button>

            <button
                class="sw-meteor-entity-data-table-delete-modal__confirm"
                type="button"
                :disabled="isDeleting || undefined"
                @click="$emit('confirm')"
            >
                {{ deleteText }}
            </button>
        </div>
    `,
};
