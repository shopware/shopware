/**
 * @sw-package framework
 */

import {
    SwMeteorEntityDataTable,
    createWrapper,
    getTable,
    nextTick,
    registerSwMeteorEntityDataTableHooks,
} from './fixtures';
import type { SwMeteorEntityDataTableColumn } from './fixtures';

describe('src/app/component/entity/sw-meteor-entity-data-table/api-and-rendering', () => {
    registerSwMeteorEntityDataTableHooks();

    it('declares only the new wrapper props and emits', () => {
        const componentOptions = SwMeteorEntityDataTable as unknown as {
            props: Record<string, unknown>;
            emits: string[];
        };

        expect(Object.keys(componentOptions.props)).toEqual(
            expect.arrayContaining([
                'repository',
                'columns',
                'identifier',
                'criteria',
                'context',
                'initialPage',
                'initialLimit',
                'initialSearchTerm',
                'initialSort',
                'paginationOptions',
                'layout',
                'searchable',
                'reloadable',
                'selectable',
                'detailRoute',
                'allowEdit',
                'allowInlineEdit',
                'allowDelete',
                'hideTableSettings',
                'additionalContextButtons',
            ]),
        );
        expect(Object.keys(componentOptions.props)).not.toEqual(
            expect.arrayContaining([
                'records',
                'total',
                'isLoading',
                'disableDataFetching',
                'allowView',
                'allowBulkDelete',
                'allowBulkEdit',
                'showActions',
                'showSettings',
                'columnChanges',
            ]),
        );
        expect(componentOptions.emits).toEqual([
            'state-change',
            'selection-change',
            'selected-ids-change',
            'load-success',
            'load-error',
            'open-detail',
            'delete-finish',
            'delete-failed',
            'bulk-delete-finish',
            'bulk-delete-failed',
            'context-select',
            'inline-edit-save',
            'inline-edit-cancel',
        ]);
    });

    it('renders mt-data-table and enables table settings by default', () => {
        const wrapper = createWrapper();
        const table = getTable(wrapper);

        expect(table.exists()).toBe(true);
        expect(table.props()).toEqual(
            expect.objectContaining({
                currentPage: 1,
                paginationLimit: 25,
                paginationOptions: [
                    5,
                    10,
                    25,
                    50,
                ],
                sortBy: '',
                sortDirection: 'ASC',
                searchValue: '',
                layout: 'default',
                allowRowSelection: false,
                allowBulkDelete: false,
                selectedRows: [],
                disableSearch: false,
                enableReload: false,
                disableEdit: true,
                disableDelete: true,
                disableSettingsTable: false,
                columnChanges: {},
                showOutlines: true,
                showStripes: true,
                enableOutlineFraming: false,
                enableRowNumbering: false,
                additionalContextButtons: [],
            }),
        );
    });

    it('allows table settings to be hidden', () => {
        const wrapper = createWrapper({
            props: {
                hideTableSettings: true,
            },
        });

        expect(getTable(wrapper).props('disableSettingsTable')).toBe(true);
    });

    it('forwards opt-in row action props to mt-data-table', () => {
        const additionalContextButtons = [
            {
                key: 'set-price',
                label: 'Set price',
                type: 'active' as const,
            },
        ];
        const wrapper = createWrapper({
            props: {
                allowEdit: true,
                allowDelete: true,
                additionalContextButtons,
            },
        });

        expect(getTable(wrapper).props()).toEqual(
            expect.objectContaining({
                disableEdit: false,
                disableDelete: false,
                disableSettingsTable: false,
                additionalContextButtons,
            }),
        );
    });

    it('updates in-session table setting props from mt-data-table events', async () => {
        const wrapper = createWrapper();

        await wrapper.find('.mt-data-table-stub__show-outlines').trigger('click');
        await wrapper.find('.mt-data-table-stub__show-stripes').trigger('click');
        await wrapper.find('.mt-data-table-stub__outline-framing').trigger('click');
        await wrapper.find('.mt-data-table-stub__row-numbering').trigger('click');
        await nextTick();

        expect(getTable(wrapper).props()).toEqual(
            expect.objectContaining({
                showOutlines: false,
                showStripes: false,
                enableOutlineFraming: true,
                enableRowNumbering: true,
            }),
        );
    });

    it('allows Meteor bulk delete only when row selection and deletion are enabled', () => {
        const wrapper = createWrapper({
            props: {
                selectable: true,
                allowDelete: true,
            },
        });

        expect(getTable(wrapper).props()).toEqual(
            expect.objectContaining({
                allowRowSelection: true,
                allowBulkDelete: true,
                disableDelete: false,
            }),
        );
    });

    it('forwards the full layout option to mt-data-table', () => {
        const wrapper = createWrapper({
            props: {
                layout: 'full',
            },
        });

        expect(getTable(wrapper).props('layout')).toBe('full');
    });

    it('enforces renderer-specific column options at the type level', () => {
        const badgeColumn: SwMeteorEntityDataTableColumn = {
            label: 'Status',
            property: 'status',
            renderer: 'badge',
            rendererOptions: {
                renderItemBadge: () => ({
                    label: 'Active',
                    variant: 'positive' as const,
                }),
            },
        };

        // @ts-expect-error - badge columns must declare rendererOptions
        const badgeColumnWithoutOptions: SwMeteorEntityDataTableColumn = {
            label: 'Status',
            property: 'status',
            renderer: 'badge',
        };

        // @ts-expect-error - price columns must declare rendererOptions
        const priceColumnWithoutOptions: SwMeteorEntityDataTableColumn = {
            label: 'Price',
            property: 'price',
            renderer: 'price',
        };

        expect(badgeColumn).toBeDefined();
        expect(badgeColumnWithoutOptions).toBeDefined();
        expect(priceColumnWithoutOptions).toBeDefined();
    });
});
