/**
 * @sw-package framework
 */

import type {
    SwMeteorEntityDataTablePrivateApi,
    SwMeteorEntityDataTablePublicApi,
} from './sw-meteor-entity-data-table.internal-types';

describe('src/app/component/entity/sw-meteor-entity-data-table/sw-meteor-entity-data-table.internal-types', () => {
    it('keeps the first-refactor setup API key contracts explicit', () => {
        const publicApiKeys = [
            'records',
            'total',
            'loading',
            'state',
            'selectedIds',
            'resolvedColumns',
            'buildCriteria',
            'load',
            'reload',
            'setPage',
            'setLimit',
            'setSearchTerm',
            'setSort',
            'setSelectedIds',
        ] satisfies Array<keyof SwMeteorEntityDataTablePublicApi>;

        const privateApiKeys = [
            'onSelectionChange',
            'onMultipleSelectionChange',
            'openDetail',
            'openBulkDeleteModal',
            'closeBulkDeleteModal',
            'deleteSelectedRecords',
            'openDeleteModal',
            'closeDeleteModal',
            'deleteRecord',
            'onContextSelect',
            'inlineEditableColumns',
            'forwardedSlotNames',
            'currentInlineEditId',
            'savingInlineEdit',
            'getLegacyPreviewSlotName',
            'hasLegacyPreviewSlot',
            'getSlotRecord',
            'getRecordValue',
            'updateRecordValue',
            'renderRecordValue',
            'renderNumberRecordValue',
            'isInlineEditing',
            'startInlineEdit',
            'saveInlineEdit',
            'cancelInlineEdit',
            'isLastInlineEditableColumn',
            'normalizeInlineEditSlotScope',
            'normalizeLegacyPreviewSlotScope',
            'openDetailFromSlotScope',
            'itemToDelete',
            'deleting',
            'showBulkDeleteModal',
            'bulkDeleting',
            'tableColumnChanges',
            'showOutlines',
            'showStripes',
            'enableOutlineFraming',
            'enableRowNumbering',
            'setShowOutlines',
            'setShowStripes',
            'setEnableOutlineFraming',
            'setEnableRowNumbering',
            'normalizeForwardedSlotScope',
        ] satisfies Array<keyof SwMeteorEntityDataTablePrivateApi>;

        expect(publicApiKeys).toHaveLength(14);
        expect(privateApiKeys).toHaveLength(43);
    });
});
