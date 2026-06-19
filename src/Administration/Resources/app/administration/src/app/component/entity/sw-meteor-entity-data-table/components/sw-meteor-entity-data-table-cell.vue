<template>
    <div
        class="sw-meteor-entity-data-table__inline-edit-cell"
        :class="{ 'is--inline-editing': isInlineEditing }"
        @dblclick="$emit('start-inline-edit')"
    >
        <template v-if="isInlineEditing">
            <sw-data-grid-inline-edit
                class="sw-meteor-entity-data-table__inline-edit-field"
                :value="value"
                :column="column"
                compact
                @update:value="$emit('update-record-value', $event)"
            />

            <div
                v-if="isLastInlineEditableColumn"
                class="sw-meteor-entity-data-table__inline-edit-actions"
            >
                <mt-button
                    class="sw-meteor-entity-data-table__inline-edit-cancel"
                    size="x-small"
                    square
                    variant="secondary"
                    :title="$t('global.default.cancel')"
                    :aria-label="$t('global.default.cancel')"
                    @click="$emit('cancel-inline-edit')"
                >
                    <mt-icon
                        name="regular-times-xs"
                        size="10px"
                    />
                </mt-button>

                <mt-button
                    class="sw-meteor-entity-data-table__inline-edit-save"
                    size="x-small"
                    square
                    variant="primary"
                    :is-loading="savingInlineEdit"
                    :title="$t('global.default.save')"
                    :aria-label="$t('global.default.save')"
                    @click="$emit('save-inline-edit')"
                >
                    <mt-icon
                        name="regular-checkmark-xxs"
                        size="10px"
                    />
                </mt-button>
            </div>
        </template>

        <template v-else>
            <slot
                v-if="hasLegacyPreviewSlot"
                name="legacy-preview"
            />

            <slot
                v-if="hasColumnSlot"
                name="column"
            />

            <div
                v-else-if="column.renderer === 'text'"
                class="sw-meteor-entity-data-table__text-renderer-cell"
            >
                <div
                    v-if="column.previewImage && !hasLegacyPreviewSlot"
                    class="sw-meteor-entity-data-table__preview-image-renderer"
                >
                    <img
                        class="sw-meteor-entity-data-table__preview-image-renderer-item"
                        :src="previewImageValue"
                        :alt="textValue"
                    />
                </div>

                <a
                    v-if="column.clickable"
                    class="sw-meteor-entity-data-table__text-renderer"
                    href="#"
                    @click.prevent="$emit('open-detail')"
                >
                    {{ textValue }}
                </a>

                <p
                    v-else
                    class="sw-meteor-entity-data-table__text-renderer"
                >
                    {{ textValue }}
                </p>
            </div>

            <a
                v-else-if="column.renderer === 'number' && column.clickable"
                class="sw-meteor-entity-data-table__number-renderer"
                href="#"
                @click.prevent="$emit('open-detail')"
            >
                {{ numberValue }}
            </a>

            <p
                v-else-if="column.renderer === 'number'"
                class="sw-meteor-entity-data-table__number-renderer"
            >
                {{ numberValue }}
            </p>

            <span
                v-else
                class="sw-meteor-entity-data-table__text-renderer"
            >
                {{ textValue }}
            </span>
        </template>
    </div>
</template>

<script setup lang="ts">
/**
 * @sw-package framework
 */

import type { SwMeteorEntityDataTableResolvedColumn } from '../sw-meteor-entity-data-table.internal-types';

defineOptions({
    name: 'SwMeteorEntityDataTableCell',
});

defineProps<{
    column: SwMeteorEntityDataTableResolvedColumn;
    value?: unknown;
    textValue: string;
    numberValue: string;
    previewImageValue: string;
    isInlineEditing: boolean;
    isLastInlineEditableColumn: boolean;
    savingInlineEdit: boolean;
    hasLegacyPreviewSlot: boolean;
    hasColumnSlot: boolean;
}>();

defineEmits<{
    (event: 'start-inline-edit'): void;
    (event: 'update-record-value', value: unknown): void;
    (event: 'cancel-inline-edit'): void;
    (event: 'save-inline-edit'): void;
    (event: 'open-detail'): void;
}>();
</script>
