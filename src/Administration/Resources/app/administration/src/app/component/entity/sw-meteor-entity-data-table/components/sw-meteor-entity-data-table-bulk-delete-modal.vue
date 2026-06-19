<template>
    <sw-modal
        class="sw-meteor-entity-data-table__bulk-delete-modal"
        variant="small"
        :title="$t('global.default.warning')"
        @modal-close="$emit('close')"
    >
        <p class="sw-meteor-entity-data-table__confirm-bulk-delete-text">
            <slot
                name="confirm-text"
                :selection-count="selectionCount"
            >
                {{
                    $t(
                        'global.entity-components.deleteMessage',
                        { count: selectionCount },
                        selectionCount,
                    )
                }}
            </slot>
        </p>

        <template #modal-footer>
            <slot
                name="modal-footer"
                :delete-items="deleteItems"
                :is-loading="isLoading"
                :selection-count="selectionCount"
            >
                <mt-button
                    class="sw-meteor-entity-data-table__bulk-delete-cancel"
                    size="small"
                    variant="secondary"
                    @click="$emit('close')"
                >
                    {{ $t('global.default.cancel') }}
                </mt-button>

                <mt-button
                    class="sw-meteor-entity-data-table__bulk-delete-confirm"
                    variant="critical"
                    size="small"
                    :is-loading="isLoading"
                    @click="deleteItems"
                >
                    {{ $t('global.default.delete') }}
                </mt-button>
            </slot>
        </template>
    </sw-modal>
</template>

<script lang="ts">
/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { defineComponent } from 'vue';

export default defineComponent({
    name: 'SwMeteorEntityDataTableBulkDeleteModal',

    props: {
        selectionCount: {
            type: Number,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    emits: [
        'close',
        'delete',
    ],

    methods: {
        deleteItems(): void {
            this.$emit('delete');
        },
    },
});
</script>
