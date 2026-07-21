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
            <p class="sw-meteor-entity-data-table-delete-modal__text">
                {{ confirmText }}
            </p>

            <template #modal-footer>
                <mt-button
                    class="sw-meteor-entity-data-table-delete-modal__cancel"
                    size="small"
                    variant="secondary"
                    @click="closeModal"
                >
                    {{ cancelText }}
                </mt-button>

                <mt-button
                    class="sw-meteor-entity-data-table-delete-modal__confirm"
                    variant="critical"
                    size="small"
                    :is-loading="isDeleting"
                    @click="deleteItem"
                >
                    {{ deleteText }}
                </mt-button>
            </template>
        </sw-modal>
    `,
};
