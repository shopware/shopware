/**
 * @sw-package framework
 */

import { useMeteorTableColumns } from './use-meteor-table-columns';
import type { SwMeteorEntityDataTableColumn } from '../sw-meteor-entity-data-table.types';

describe('src/app/component/entity/sw-meteor-entity-data-table/composables/use-meteor-table-columns', () => {
    it('resolves declaration order and strips wrapper-only sorting metadata', () => {
        const columns: SwMeteorEntityDataTableColumn[] = [
            {
                property: 'name',
                label: 'Name',
                sortField: 'translated.name',
                naturalSorting: true,
                inlineEdit: 'string',
            },
            {
                property: 'link',
                label: 'Link',
                renderer: 'number',
            },
        ];

        const { resolvedColumns, inlineEditableColumns } = useMeteorTableColumns({
            columns: () => columns,
        });

        expect(resolvedColumns.value).toEqual([
            expect.objectContaining({
                property: 'name',
                renderer: 'text',
                position: 0,
            }),
            expect.objectContaining({
                property: 'link',
                renderer: 'number',
                position: 100,
            }),
        ]);
        expect(resolvedColumns.value[0]).not.toHaveProperty('sortField');
        expect(resolvedColumns.value[0]).not.toHaveProperty('naturalSorting');
        expect(inlineEditableColumns.value).toEqual([
            expect.objectContaining({ property: 'name' }),
        ]);
    });
});
