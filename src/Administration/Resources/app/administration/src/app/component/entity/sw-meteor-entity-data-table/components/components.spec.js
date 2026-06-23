/**
 * @sw-package framework
 */

import SwMeteorEntityDataTableBulkDeleteModal from './sw-meteor-entity-data-table-bulk-delete-modal';
import SwMeteorEntityDataTableDeleteModal from './sw-meteor-entity-data-table-delete-modal';
import SwMeteorEntityDataTableInlineEditCell from './sw-meteor-entity-data-table-inline-edit-cell';

describe('sw-meteor-entity-data-table components', () => {
    it('exposes local table subcomponents for Vite and Jest resolution', () => {
        expect(SwMeteorEntityDataTableBulkDeleteModal.name).toBe('SwMeteorEntityDataTableBulkDeleteModal');
        expect(SwMeteorEntityDataTableDeleteModal.name).toBe('SwMeteorEntityDataTableDeleteModal');
        expect(SwMeteorEntityDataTableInlineEditCell.name).toBe('SwMeteorEntityDataTableInlineEditCell');
    });
});
