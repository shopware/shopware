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
        let forwardedSlotScope: Record<string, unknown> | undefined;
        const wrapper = createWrapper({
            props: {
                columns: inlineEditColumns,
                allowInlineEdit: true,
            },
            slots: {
                'column-name': (slotProps: Record<string, unknown>) => {
                    forwardedSlotScope = slotProps;

                    return h('span', { class: 'inline-column-name-slot' }, (slotProps.item as TestRecord).name);
                },
            },
        });

        await flushPromises();

        expect(wrapper.find('.inline-column-name-slot').text()).toBe('First record');
        expect(forwardedSlotScope?.data).toEqual(records[0]);
        expect(forwardedSlotScope?.item).toEqual(records[0]);
        expect(forwardedSlotScope?.columnDefinition).toEqual(expect.objectContaining(inlineEditColumns[0]));
        expect(forwardedSlotScope?.column).toEqual(expect.objectContaining(inlineEditColumns[0]));
        expect(forwardedSlotScope?.isInlineEdit).toBe(false);
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
        let forwardedSlotScope: Record<string, unknown> | undefined;
        const wrapper = createWrapper({
            slots: {
                'column-name': (slotProps: Record<string, unknown>) => {
                    forwardedSlotScope = slotProps;

                    return h('span', { class: 'column-name-slot' }, (slotProps.item as TestRecord).name);
                },
            },
        });

        expect(wrapper.find('.column-name-slot').text()).toBe('First record');
        expect(forwardedSlotScope?.data).toEqual(records[0]);
        expect(forwardedSlotScope?.item).toEqual(records[0]);
        expect(forwardedSlotScope?.columnDefinition).toEqual(expect.objectContaining(columns[0]));
        expect(forwardedSlotScope?.column).toEqual(expect.objectContaining(columns[0]));
    });
});
