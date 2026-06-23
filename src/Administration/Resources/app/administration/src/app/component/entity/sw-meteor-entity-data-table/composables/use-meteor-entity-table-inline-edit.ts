/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { ref } from 'vue';
import type { MeteorEntityTableRecord, MeteorEntityTableRepository } from '../sw-meteor-entity-data-table.types';

type UseMeteorEntityTableInlineEditOptions = {
    repository: MeteorEntityTableRepository;
    context?: unknown;
    reload: () => Promise<MeteorEntityTableRecord[]>;
    emit: {
        (event: 'inline-edit-save', promise: Promise<unknown>, record: MeteorEntityTableRecord): void;
        (event: 'inline-edit-cancel', promise: Promise<MeteorEntityTableRecord[]>): void;
    };
};

function getRecordValue(record: MeteorEntityTableRecord, property: string): unknown {
    return property.split('.').reduce<unknown>((value, path) => {
        if (value && typeof value === 'object' && path in value) {
            return (value as Record<string, unknown>)[path];
        }

        return '';
    }, record);
}

function setRecordValue(record: MeteorEntityTableRecord, property: string, value: unknown) {
    const path = property.split('.');
    const targetPath = path.slice(0, -1);
    const propertyName = path.at(-1);

    if (!propertyName) {
        return;
    }

    const target = targetPath.reduce<Record<string, unknown>>((currentTarget, pathPart) => {
        if (!currentTarget[pathPart] || typeof currentTarget[pathPart] !== 'object') {
            currentTarget[pathPart] = {};
        }

        return currentTarget[pathPart] as Record<string, unknown>;
    }, record);

    target[propertyName] = value;
}

export function useMeteorEntityTableInlineEdit(options: UseMeteorEntityTableInlineEditOptions) {
    const editingId = ref<string | null>(null);
    const draftValues = ref<Record<string, Record<string, unknown>>>({});

    const isInlineEditing = (record?: MeteorEntityTableRecord) => {
        return !!record?.id && editingId.value === record.id;
    };

    const startInlineEdit = (record: MeteorEntityTableRecord) => {
        editingId.value = record.id;
    };

    const getInlineEditValue = (record: MeteorEntityTableRecord, property: string) => {
        if (Object.prototype.hasOwnProperty.call(draftValues.value[record.id] ?? {}, property)) {
            return draftValues.value[record.id][property];
        }

        return getRecordValue(record, property);
    };

    const updateInlineEditValue = (record: MeteorEntityTableRecord, property: string, value: unknown) => {
        draftValues.value = {
            ...draftValues.value,
            [record.id]: {
                ...(draftValues.value[record.id] ?? {}),
                [property]: value,
            },
        };
    };

    const saveInlineEdit = async (record: MeteorEntityTableRecord, property: string) => {
        if (!options.repository.save) {
            return;
        }

        const value = getInlineEditValue(record, property);
        setRecordValue(record, property, value);

        const promise = options.repository.save(record, options.context).then(async () => {
            editingId.value = null;
            draftValues.value = {};

            return options.reload();
        });

        options.emit('inline-edit-save', promise, record);

        await promise;
    };

    const cancelInlineEdit = async () => {
        editingId.value = null;
        draftValues.value = {};

        const promise = options.reload();
        options.emit('inline-edit-cancel', promise);

        await promise;
    };

    return {
        editingId,
        isInlineEditing,
        startInlineEdit,
        getInlineEditValue,
        updateInlineEditValue,
        saveInlineEdit,
        cancelInlineEdit,
    };
}
