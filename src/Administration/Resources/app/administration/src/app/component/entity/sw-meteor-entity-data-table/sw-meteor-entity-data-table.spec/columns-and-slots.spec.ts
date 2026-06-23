/**
 * @sw-package framework
 */

import {
    columns,
    createRepositoryMock,
    createSearchResult,
    createWrapper,
    flushPromises,
    getTable,
    h,
    inlineEditColumns,
    records,
    registerSwMeteorEntityDataTableHooks,
} from './fixtures';
import type { TestRecord } from './fixtures';

describe('src/app/component/entity/sw-meteor-entity-data-table/columns-and-slots', () => {
    registerSwMeteorEntityDataTableHooks();

    it('resolves columns by declaration order and keeps renderer options while stripping sorting metadata', () => {
        const renderItemBadge = () => ({
            label: 'Active',
            variant: 'positive' as const,
        });
        const wrapper = createWrapper({
            props: {
                columns: [
                    {
                        label: 'Name',
                        property: 'name',
                        inlineEdit: 'string',
                    },
                    {
                        label: 'Customer name',
                        property: 'customerName',
                        renderer: 'badge',
                        rendererOptions: {
                            renderItemBadge,
                        },
                        sortField: [
                            'firstName',
                            'lastName',
                        ],
                        naturalSorting: true,
                        width: 180,
                    },
                ],
            },
        });
        const tableColumns = getTable(wrapper).props('columns') as Record<string, unknown>[];

        expect(tableColumns).toEqual([
            {
                label: 'Name',
                property: 'name',
                renderer: 'text',
                inlineEdit: 'string',
                position: 0,
            },
            {
                label: 'Customer name',
                property: 'customerName',
                renderer: 'badge',
                rendererOptions: {
                    renderItemBadge,
                },
                position: 100,
                width: 180,
            },
        ]);
        expect(tableColumns[1]).not.toHaveProperty('sortField');
        expect(tableColumns[1]).not.toHaveProperty('naturalSorting');
    });

    it('renders custom slots for editable columns while the row is not being edited', async () => {
        const forwardedSlotScopes: Record<string, unknown>[] = [];
        const wrapper = createWrapper({
            props: {
                columns: inlineEditColumns,
                allowInlineEdit: true,
            },
            slots: {
                'column-name': (slotProps: Record<string, unknown>) => {
                    forwardedSlotScopes.push(slotProps);

                    return h('span', { class: 'inline-column-name-slot' }, (slotProps.item as TestRecord).name);
                },
            },
        });

        await flushPromises();

        expect(wrapper.find('.inline-column-name-slot').text()).toBe('First record');
        expect(forwardedSlotScopes[0]?.data).toEqual(records[0]);
        expect(forwardedSlotScopes[0]?.item).toEqual(records[0]);
        expect(forwardedSlotScopes[0]?.columnDefinition).toEqual(expect.objectContaining(inlineEditColumns[0]));
        expect(forwardedSlotScopes[0]?.column).toEqual(expect.objectContaining(inlineEditColumns[0]));
        expect(forwardedSlotScopes[0]?.isInlineEdit).toBe(false);
    });

    it('renders legacy preview slots before editable column content', async () => {
        let forwardedSlotScope: Record<string, unknown> | undefined;
        const repository = createRepositoryMock(
            createSearchResult([
                {
                    id: 'record-1',
                    name: 'First record',
                    previewUrl: '/preview.png',
                },
            ]),
        );
        const wrapper = createWrapper({
            props: {
                repository,
                columns: [
                    {
                        label: 'Name',
                        property: 'name',
                        previewImage: 'previewUrl',
                        inlineEdit: 'string',
                    },
                ],
                allowInlineEdit: true,
            },
            slots: {
                'preview-name': (slotProps: Record<string, unknown>) => {
                    forwardedSlotScope = slotProps;

                    return h(
                        'span',
                        { class: 'legacy-preview-name-slot' },
                        `${(slotProps.item as TestRecord).name}:${String(slotProps.compact)}`,
                    );
                },
            },
        });

        await flushPromises();

        expect(wrapper.find('.legacy-preview-name-slot').text()).toBe('First record:false');
        expect(wrapper.find('.sw-meteor-entity-data-table__text-renderer').text()).toBe('First record');
        expect(wrapper.find('.sw-meteor-entity-data-table__preview-image-renderer').exists()).toBe(false);
        expect(forwardedSlotScope?.data).toEqual(expect.objectContaining({ id: 'record-1' }));
        expect(forwardedSlotScope?.item).toEqual(expect.objectContaining({ id: 'record-1' }));
        expect(forwardedSlotScope?.columnDefinition).toEqual(expect.objectContaining({ property: 'name' }));
        expect(forwardedSlotScope?.column).toEqual(expect.objectContaining({ property: 'name' }));
        expect(forwardedSlotScope?.compact).toBe(false);
    });

    it('forwards toolbar and empty-state slots only when present', () => {
        const wrapperWithoutSlots = createWrapper();

        expect(wrapperWithoutSlots.find('.mt-data-table-stub__toolbar').exists()).toBe(false);
        expect(wrapperWithoutSlots.find('.mt-data-table-stub__empty-state').exists()).toBe(false);

        const wrapperWithSlots = createWrapper({
            slots: {
                toolbar: '<span class="toolbar-slot">Toolbar</span>',
                'empty-state': '<span class="empty-state-slot">Empty</span>',
            },
        });

        expect(wrapperWithSlots.find('.toolbar-slot').text()).toBe('Toolbar');
        expect(wrapperWithSlots.find('.empty-state-slot').text()).toBe('Empty');
    });

    it('forwards custom table column slots with Meteor scope and compatibility aliases', () => {
        const forwardedSlotScopes: Record<string, unknown>[] = [];
        const wrapper = createWrapper({
            slots: {
                'column-name': (slotProps: Record<string, unknown>) => {
                    forwardedSlotScopes.push(slotProps);

                    return h('span', { class: 'column-name-slot' }, (slotProps.item as TestRecord).name);
                },
            },
        });

        expect(wrapper.find('.column-name-slot').text()).toBe('First record');
        expect(forwardedSlotScopes[0]?.data).toEqual(records[0]);
        expect(forwardedSlotScopes[0]?.item).toEqual(records[0]);
        expect(forwardedSlotScopes[0]?.columnDefinition).toEqual(expect.objectContaining(columns[0]));
        expect(forwardedSlotScopes[0]?.column).toEqual(expect.objectContaining(columns[0]));
    });

    it('renders empty number cells for empty or non-numeric values in editable number columns', async () => {
        const repository = createRepositoryMock(
            createSearchResult([
                {
                    id: 'record-1',
                    name: 'Zero record',
                    quantity: 0,
                },
                {
                    id: 'record-2',
                    name: 'Null record',
                    quantity: null,
                },
                {
                    id: 'record-3',
                    name: 'Undefined record',
                    quantity: undefined,
                },
                {
                    id: 'record-4',
                    name: 'Empty record',
                    quantity: '',
                },
                {
                    id: 'record-5',
                    name: 'Whitespace record',
                    quantity: '   ',
                },
                {
                    id: 'record-6',
                    name: 'Non-numeric record',
                    quantity: 'not-a-number',
                },
            ]),
        );
        const wrapper = createWrapper({
            props: {
                repository,
                columns: [
                    {
                        label: 'Quantity',
                        property: 'quantity',
                        renderer: 'number',
                        inlineEdit: 'number',
                    },
                ],
                allowInlineEdit: true,
            },
        });

        await flushPromises();

        const numberValues = wrapper
            .findAll('.sw-meteor-entity-data-table__number-renderer')
            .map((numberRenderer) => numberRenderer.text());

        expect(numberValues).toEqual([
            '0',
            '',
            '',
            '',
            '',
            '',
        ]);
    });
});
