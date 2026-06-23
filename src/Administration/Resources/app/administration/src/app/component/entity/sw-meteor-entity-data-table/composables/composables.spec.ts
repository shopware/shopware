/**
 * @sw-package framework
 */

import type { MeteorEntityTableRecord } from '../sw-meteor-entity-data-table.types';
import { useMeteorEntityTableColumns } from './use-meteor-entity-table-columns';
import { useMeteorEntityTableSelection } from './use-meteor-entity-table-selection';

describe('sw-meteor-entity-data-table composables', () => {
    it('normalizes legacy column definitions for mt-data-table', () => {
        const { resolvedColumns } = useMeteorEntityTableColumns(
            () => [
                {
                    property: 'name',
                    label: 'sw-manufacturer.list.columnName',
                    primary: true,
                    renderer: 'badge',
                    width: 240,
                    allowResize: false,
                    sortable: true,
                },
            ],
            (key) => `translated.${key}`,
        );

        expect(resolvedColumns.value).toEqual([
            {
                property: 'name',
                label: 'translated.sw-manufacturer.list.columnName',
                position: 100,
                renderer: 'badge',
                clickable: true,
                allowResize: false,
                width: 240,
                sortable: true,
            },
        ]);
    });

    it('keeps legacy columns sortable unless sorting is explicitly disabled', () => {
        const { resolvedColumns } = useMeteorEntityTableColumns(
            () => [
                {
                    property: 'name',
                    label: 'Name',
                },
                {
                    property: 'link',
                    label: 'Link',
                    sortable: false,
                },
            ],
            (key) => key,
        );

        expect(resolvedColumns.value).toEqual([
            expect.objectContaining({
                property: 'name',
                sortable: true,
            }),
            expect.objectContaining({
                property: 'link',
                sortable: false,
            }),
        ]);
    });

    it('keeps legacy selection payloads in sync with the current records', () => {
        let records: MeteorEntityTableRecord[] = [
            { id: 'manufacturer-1', name: 'Shopware' },
            { id: 'manufacturer-2', name: 'Meteor' },
        ];

        const selection = useMeteorEntityTableSelection(() => records);
        selection.setSelectedIds([
            'manufacturer-1',
            'manufacturer-1',
            'missing',
        ]);

        expect(selection.selectedIds.value).toEqual([
            'manufacturer-1',
            'missing',
        ]);
        expect(selection.selection.value).toEqual({
            'manufacturer-1': records[0],
        });

        records = [{ id: 'manufacturer-2', name: 'Meteor' }];
        selection.pruneSelection();

        expect(selection.selectedIds.value).toEqual([]);
        expect(selection.selection.value).toEqual({});
    });
});
