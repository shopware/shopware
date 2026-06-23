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

        deleteItem() {
            this.$emit('confirm');
        },
    },

    template: `
        <sw-modal
            class="sw-meteor-entity-data-table-delete-modal"
            :title="titleText"
            variant="small"
            @modal-close="closeModal"
        >
            <p class="sw-listing__confirm-delete-text sw-meteor-entity-data-table-delete-modal__text">
                <slot name="delete-confirm-text" :item="item">
                    {{ confirmText }}
                </slot>
            </p>

            <template #modal-footer>
                <slot
                    name="delete-modal-footer"
                    :item="item"
                    :close-modal="closeModal"
                    :delete-item="deleteItem"
                    :is-deleting="isDeleting"
                >
                    <slot
                        name="delete-modal-cancel"
                        :item="item"
                        :close-modal="closeModal"
                    >
                        <mt-button
                            class="sw-meteor-entity-data-table-delete-modal__cancel"
                            size="small"
                            variant="secondary"
                            @click="closeModal"
                        >
                            {{ cancelText }}
                        </mt-button>
                    </slot>

                    <slot
                        name="delete-modal-delete-item"
                        :item="item"
                        :delete-item="deleteItem"
                        :is-deleting="isDeleting"
                    >
                        <mt-button
                            class="sw-meteor-entity-data-table-delete-modal__confirm"
                            variant="critical"
                            size="small"
                            :is-loading="isDeleting"
                            @click="deleteItem"
                        >
                            {{ deleteText }}
                        </mt-button>
                    </slot>
                </slot>
            </template>
        </sw-modal>
    `,
};
