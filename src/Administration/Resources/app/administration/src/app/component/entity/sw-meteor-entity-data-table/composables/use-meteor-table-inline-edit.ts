/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import type { Entity } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/Entity';
import { get as objectGet, set as objectSet } from 'src/core/service/utils/object.utils';
import type {
    SwMeteorEntityDataTableEntityName,
    SwMeteorEntityDataTableProps,
    SwMeteorEntityDataTableRecord,
    SwMeteorEntityDataTableResolvedColumn,
} from '../sw-meteor-entity-data-table.internal-types';

type UseMeteorTableInlineEditOptions = {
    repository: () => SwMeteorEntityDataTableProps['repository'];
    context: () => SwMeteorEntityDataTableProps['context'];
    allowInlineEdit: () => boolean | undefined;
    inlineEditableColumns: ComputedRef<SwMeteorEntityDataTableResolvedColumn[]>;
    load: () => Promise<void>;
    emitInlineEditSave: (savePromise: Promise<void>, record: SwMeteorEntityDataTableRecord) => void;
    emitInlineEditCancel: (reloadPromise: Promise<void>) => void;
};

export function useMeteorTableInlineEdit(options: UseMeteorTableInlineEditOptions): {
    currentInlineEditId: Ref<string | null>;
    savingInlineEdit: Ref<boolean>;
    getRecordValue: (record: SwMeteorEntityDataTableRecord | null, property: string) => unknown;
    updateRecordValue: (record: SwMeteorEntityDataTableRecord | null, property: string, value: unknown) => void;
    renderRecordValue: (record: SwMeteorEntityDataTableRecord | null, property: string) => string;
    renderNumberRecordValue: (record: SwMeteorEntityDataTableRecord | null, property: string) => string;
    isInlineEditing: (record: SwMeteorEntityDataTableRecord | null) => boolean;
    startInlineEdit: (record: SwMeteorEntityDataTableRecord | null) => void;
    saveInlineEdit: (record: SwMeteorEntityDataTableRecord | null) => Promise<void>;
    cancelInlineEdit: () => Promise<void>;
    isLastInlineEditableColumn: (column: SwMeteorEntityDataTableResolvedColumn) => boolean;
    resetInlineEdit: () => void;
} {
    const currentInlineEditId = ref<string | null>(null);
    const savingInlineEdit = ref(false);

    function getRecordValue(record: SwMeteorEntityDataTableRecord | null, property: string): unknown {
        if (!record) {
            return '';
        }

        return objectGet(record, property, '');
    }

    function updateRecordValue(record: SwMeteorEntityDataTableRecord | null, property: string, value: unknown): void {
        if (!record) {
            return;
        }

        objectSet(record as Record<string, unknown>, property, value);
    }

    function renderRecordValue(record: SwMeteorEntityDataTableRecord | null, property: string): string {
        const value = getRecordValue(record, property);

        if (value === null || value === undefined) {
            return '';
        }

        return String(value as string | number | boolean | bigint | symbol);
    }

    function renderNumberRecordValue(record: SwMeteorEntityDataTableRecord | null, property: string): string {
        return String(Number(getRecordValue(record, property)));
    }

    function isInlineEditing(record: SwMeteorEntityDataTableRecord | null): boolean {
        return currentInlineEditId.value !== null && currentInlineEditId.value === record?.id;
    }

    function startInlineEdit(record: SwMeteorEntityDataTableRecord | null): void {
        if (!options.allowInlineEdit() || !record || options.inlineEditableColumns.value.length <= 0) {
            return;
        }

        if (currentInlineEditId.value !== null && currentInlineEditId.value !== record.id) {
            return;
        }

        currentInlineEditId.value = record.id;
    }

    function saveInlineEdit(record: SwMeteorEntityDataTableRecord | null): Promise<void> {
        if (!record || !isInlineEditing(record)) {
            return Promise.resolve();
        }

        const saveContext = (options.context() ?? Shopware.Context.api) as typeof Shopware.Context.api;
        const savePromise = options
            .repository()
            .save(record as Entity<SwMeteorEntityDataTableEntityName>, saveContext)
            .then(() => options.load());

        savingInlineEdit.value = true;
        options.emitInlineEditSave(savePromise, record);

        void savePromise
            .then(() => {
                currentInlineEditId.value = null;
            })
            .catch(() => {
                // Keep inline edit active so the user can correct and retry the failed save.
            })
            .finally(() => {
                savingInlineEdit.value = false;
            });

        return savePromise.catch(() => {
            return undefined;
        });
    }

    function cancelInlineEdit(): Promise<void> {
        if (currentInlineEditId.value === null) {
            return Promise.resolve();
        }

        const reloadPromise = options.load();

        currentInlineEditId.value = null;
        options.emitInlineEditCancel(reloadPromise);

        return reloadPromise;
    }

    function isLastInlineEditableColumn(column: SwMeteorEntityDataTableResolvedColumn): boolean {
        return (
            options.inlineEditableColumns.value[options.inlineEditableColumns.value.length - 1]?.property === column.property
        );
    }

    function resetInlineEdit(): void {
        currentInlineEditId.value = null;
    }

    return {
        currentInlineEditId,
        savingInlineEdit,
        getRecordValue,
        updateRecordValue,
        renderRecordValue,
        renderNumberRecordValue,
        isInlineEditing,
        startInlineEdit,
        saveInlineEdit,
        cancelInlineEdit,
        isLastInlineEditableColumn,
        resetInlineEdit,
    };
}
