<template>
    <sw-modal
        class="sw-meteor-entity-data-table__delete-modal"
        variant="small"
        :title="$t('global.default.warning')"
        @modal-close="$emit('close')"
    >
        <p class="sw-meteor-entity-data-table__confirm-delete-text">
            <slot
                name="confirm-text"
                :item="item"
            >
                {{ $t('global.entity-components.deleteMessage') }}
            </slot>
        </p>

        <template #modal-footer>
            <slot
                name="modal-footer"
                :item="item"
                :delete-item="deleteItem"
                :is-loading="isLoading"
            >
                <mt-button
                    class="sw-meteor-entity-data-table__delete-cancel"
                    size="small"
                    variant="secondary"
                    @click="$emit('close')"
                >
                    {{ $t('global.default.cancel') }}
                </mt-button>

                <mt-button
                    class="sw-meteor-entity-data-table__delete-confirm"
                    variant="critical"
                    size="small"
                    :is-loading="isLoading"
                    @click="deleteItem"
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
import type { PropType } from 'vue';
import type { SwMeteorEntityDataTableRecord } from '../sw-meteor-entity-data-table.internal-types';

export default defineComponent({
    name: 'SwMeteorEntityDataTableDeleteModal',

    props: {
        item: {
            type: Object as PropType<SwMeteorEntityDataTableRecord>,
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
        deleteItem(): void {
            this.$emit('delete');
        },
    },
});
</script>
