/**
 * @sw-package framework
 */

import SwMeteorEntityDataTableBulkDeleteModal from './sw-meteor-entity-data-table-bulk-delete-modal';
import SwMeteorEntityDataTableDeleteModal from './sw-meteor-entity-data-table-delete-modal';

describe('sw-meteor-entity-data-table components', () => {
    it('exposes local table subcomponents for Vite and Jest resolution', () => {
        expect(SwMeteorEntityDataTableBulkDeleteModal.name).toBe('SwMeteorEntityDataTableBulkDeleteModal');
        expect(SwMeteorEntityDataTableDeleteModal.name).toBe('SwMeteorEntityDataTableDeleteModal');
    });
});
