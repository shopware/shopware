/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { h } from 'vue';
import type { SetupContext } from 'vue';

type MtDataTableStubColumn = {
    property: string;
    [key: string]: unknown;
};

type MtDataTableStubRecord = {
    id: string;
    [key: string]: unknown;
};

type MtDataTableStubProps = {
    columns?: MtDataTableStubColumn[];
    dataSource?: MtDataTableStubRecord[];
    columnChanges?: Record<
        string,
        {
            position?: number;
            width?: number;
            visible?: boolean;
        }
    >;
};

const fallbackColumns: MtDataTableStubColumn[] = [
    {
        label: 'Name',
        property: 'name',
    },
];

const fallbackRecords: MtDataTableStubRecord[] = [
    {
        id: 'record-1',
        name: 'First record',
    },
    {
        id: 'record-2',
        name: 'Second record',
    },
];

export const MtDataTableStub = {
    name: 'mt-data-table',
    emits: [
        'sort-change',
        'pagination-current-page-change',
        'pagination-limit-change',
        'search-value-change',
        'selection-change',
        'multiple-selection-change',
        'reload',
        'open-details',
        'bulk-delete',
        'item-delete',
        'context-select',
        'change-show-outlines',
        'change-show-stripes',
        'change-outline-framing',
        'change-enable-row-numbering',
    ],
    props: [
        'dataSource',
        'columns',
        'currentPage',
        'paginationLimit',
        'paginationOptions',
        'paginationTotalItems',
        'sortBy',
        'sortDirection',
        'searchValue',
        'isLoading',
        'layout',
        'allowRowSelection',
        'allowBulkDelete',
        'selectedRows',
        'disableSearch',
        'enableReload',
        'disableEdit',
        'disableDelete',
        'disableSettingsTable',
        'columnChanges',
        'showOutlines',
        'showStripes',
        'enableOutlineFraming',
        'enableRowNumbering',
        'additionalContextButtons',
    ],
    setup(rawProps: Record<string, unknown>, setupContext: SetupContext) {
        const props = rawProps as MtDataTableStubProps;
        const { emit, slots } = setupContext;
        const getCurrentRecords = () => {
            return props.dataSource && props.dataSource.length > 0 ? props.dataSource : fallbackRecords;
        };
        const getNameColumn = () => {
            return props.columns?.find((column) => column.property === 'name') ?? fallbackColumns[0];
        };

        return () =>
            h('div', { class: 'mt-data-table-stub' }, [
                slots.toolbar ? h('div', { class: 'mt-data-table-stub__toolbar' }, slots.toolbar()) : null,
                slots['empty-state'] ? h('div', { class: 'mt-data-table-stub__empty-state' }, slots['empty-state']()) : null,
                slots['column-name']
                    ? h(
                          'div',
                          { class: 'mt-data-table-stub__column-name' },
                          slots['column-name']({
                              data: getCurrentRecords()[0],
                              columnDefinition: getNameColumn(),
                          }),
                      )
                    : null,
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__sort',
                        type: 'button',
                        onClick: () => emit('sort-change', props.columns?.[0]?.property ?? 'name', 'DESC'),
                    },
                    'Sort',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__page',
                        type: 'button',
                        onClick: () => emit('pagination-current-page-change', 3),
                    },
                    'Page',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__limit',
                        type: 'button',
                        onClick: () => emit('pagination-limit-change', 50),
                    },
                    'Limit',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__search',
                        type: 'button',
                        onClick: () => emit('search-value-change', 'needle'),
                    },
                    'Search',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__select-row',
                        type: 'button',
                        onClick: () => emit('selection-change', { id: 'record-1', value: true }),
                    },
                    'Select row',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__deselect-row',
                        type: 'button',
                        onClick: () => emit('selection-change', { id: 'record-1', value: false }),
                    },
                    'Deselect row',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__select-all',
                        type: 'button',
                        onClick: () =>
                            emit('multiple-selection-change', {
                                selections: [
                                    'record-1',
                                    'record-2',
                                ],
                                value: true,
                            }),
                    },
                    'Select all',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__change-column-settings',
                        type: 'button',
                        onClick: () => {
                            if (!props.columnChanges) {
                                return;
                            }

                            props.columnChanges.link = {
                                position: 0,
                                width: 320,
                                visible: true,
                            };
                            props.columnChanges.name = {
                                position: 100,
                                width: 280,
                                visible: false,
                            };
                        },
                    },
                    'Change column settings',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__deselect-all',
                        type: 'button',
                        onClick: () =>
                            emit('multiple-selection-change', {
                                selections: [
                                    'record-1',
                                    'record-2',
                                ],
                                value: false,
                            }),
                    },
                    'Deselect all',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__reload',
                        type: 'button',
                        onClick: () => emit('reload'),
                    },
                    'Reload',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__details',
                        type: 'button',
                        onClick: () => emit('open-details', getCurrentRecords()[0]),
                    },
                    'Details',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__bulk-delete',
                        type: 'button',
                        onClick: () => emit('bulk-delete'),
                    },
                    'Bulk delete',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__delete',
                        type: 'button',
                        onClick: () => emit('item-delete', getCurrentRecords()[0]),
                    },
                    'Delete',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__context-select',
                        type: 'button',
                        onClick: () =>
                            emit('context-select', {
                                key: 'set-price',
                                data: getCurrentRecords()[0],
                            }),
                    },
                    'Context select',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__show-outlines',
                        type: 'button',
                        onClick: () => emit('change-show-outlines', false),
                    },
                    'Show outlines',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__show-stripes',
                        type: 'button',
                        onClick: () => emit('change-show-stripes', false),
                    },
                    'Show stripes',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__outline-framing',
                        type: 'button',
                        onClick: () => emit('change-outline-framing', true),
                    },
                    'Outline framing',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__row-numbering',
                        type: 'button',
                        onClick: () => emit('change-enable-row-numbering', true),
                    },
                    'Row numbering',
                ),
            ]);
    },
};

export const globalStubs = {
    'mt-data-table': MtDataTableStub,
    'sw-modal': {
        template: `
            <div class="sw-modal">
                <slot></slot>
                <slot name="modal-footer"></slot>
                <button
                    class="sw-modal__close"
                    type="button"
                    @click="$emit('modal-close')"
                >
                    Close
                </button>
            </div>
        `,
        emits: ['modal-close'],
    },
    'mt-button': {
        props: [
            'isLoading',
        ],
        template: `
            <button
                class="mt-button"
                type="button"
                :data-loading="isLoading"
                @click="$emit('click')"
            >
                <slot></slot>
            </button>
        `,
        emits: ['click'],
    },
    'mt-icon': true,
    'sw-data-grid-inline-edit': {
        props: [
            'value',
            'column',
            'compact',
        ],
        template: `
            <input
                class="sw-data-grid-inline-edit-stub"
                :value="value"
                @input="$emit('update:value', $event.target.value)"
            />
        `,
        emits: ['update:value'],
    },
};
