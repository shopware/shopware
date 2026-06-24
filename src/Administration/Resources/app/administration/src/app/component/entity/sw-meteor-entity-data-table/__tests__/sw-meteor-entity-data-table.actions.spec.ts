/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { _overridesMap, overrideComponentSetup } from 'src/app/adapter/composition-extension-system';
import {
    SwMeteorEntityDataTable,
    createSearchResult,
    createWrapper,
    mountedTable,
    type TestWrapper,
} from './sw-meteor-entity-data-table.test-utils';

describe('src/app/component/entity/sw-meteor-entity-data-table actions', () => {
    afterEach(() => {
        _overridesMap['sw-meteor-entity-data-table']?.splice(0);
    });

    it('selection-change from mt-data-table updates selected ids and emits wrapper selection events', async () => {
        const wrapper = await createWrapper();

        await mountedTable(wrapper).vm.$emit('selection-change', {
            id: 'manufacturer-1',
            value: true,
        });
        await flushPromises();

        expect(wrapper.vm.selectedIds).toEqual(['manufacturer-1']);
        expect(wrapper.emitted('selected-ids-change')?.at(-1)?.[0]).toEqual(['manufacturer-1']);
        expect(wrapper.emitted('selection-change')?.at(-1)).toEqual([
            {
                'manufacturer-1': {
                    id: 'manufacturer-1',
                    name: 'Shopware',
                },
            },
            1,
        ]);
        expect(wrapper.emitted('select-item')?.at(-1)).toEqual([
            {
                'manufacturer-1': {
                    id: 'manufacturer-1',
                    name: 'Shopware',
                },
            },
            {
                id: 'manufacturer-1',
                name: 'Shopware',
            },
            true,
        ]);
    });

    it('handles multiple-selection-change and emits wrapper selection events', async () => {
        const wrapper = await createWrapper();

        await mountedTable(wrapper).vm.$emit('multiple-selection-change', {
            selections: [
                'manufacturer-1',
                'manufacturer-2',
            ],
            value: true,
        });
        await flushPromises();

        expect(wrapper.vm.selectedIds).toEqual([
            'manufacturer-1',
            'manufacturer-2',
        ]);
        expect(wrapper.emitted('selected-ids-change')?.at(-1)?.[0]).toEqual([
            'manufacturer-1',
            'manufacturer-2',
        ]);
        expect(wrapper.emitted('selection-change')?.at(-1)).toEqual([
            {
                'manufacturer-1': {
                    id: 'manufacturer-1',
                    name: 'Shopware',
                },
                'manufacturer-2': {
                    id: 'manufacturer-2',
                    name: 'Meteor',
                },
            },
            2,
        ]);
        expect(wrapper.emitted('select-all-items')?.at(-1)?.[0]).toEqual({
            'manufacturer-1': {
                id: 'manufacturer-1',
                name: 'Shopware',
            },
            'manufacturer-2': {
                id: 'manufacturer-2',
                name: 'Meteor',
            },
        });
    });

    it('removes no-longer-loaded ids after reload', async () => {
        const repository = {
            search: jest
                .fn()
                .mockResolvedValueOnce(
                    createSearchResult([
                        { id: 'manufacturer-1', name: 'Shopware' },
                        { id: 'manufacturer-2', name: 'Meteor' },
                    ]),
                )
                .mockResolvedValueOnce(
                    createSearchResult([
                        { id: 'manufacturer-2', name: 'Meteor' },
                    ]),
                ),
        };
        const wrapper = await createWrapper({ repository });

        wrapper.vm.setSelectedIds([
            'manufacturer-1',
            'manufacturer-2',
        ]);
        await wrapper.vm.reload();
        await flushPromises();

        expect(wrapper.vm.selectedIds).toEqual(['manufacturer-2']);
        expect(wrapper.vm.selection).toEqual({
            'manufacturer-2': {
                id: 'manufacturer-2',
                name: 'Meteor',
            },
        });
    });

    it('open-details pushes to detailRoute and emits open-detail', async () => {
        const router = {
            push: jest.fn(),
        };
        const wrapper = await createWrapper(
            {
                detailRoute: 'sw.manufacturer.detail',
            },
            {
                mocks: {
                    $router: router,
                },
            },
        );

        await mountedTable(wrapper).vm.$emit('open-details', {
            id: 'manufacturer-1',
            name: 'Shopware',
        });

        expect(router.push).toHaveBeenCalledWith({
            name: 'sw.manufacturer.detail',
            params: {
                id: 'manufacturer-1',
            },
        });
        expect(wrapper.emitted('open-detail')?.at(-1)?.[0]).toEqual({
            id: 'manufacturer-1',
            record: {
                id: 'manufacturer-1',
                name: 'Shopware',
            },
        });
    });

    it('opens detail from a clickable inline-edit cell value', async () => {
        const router = {
            push: jest.fn(),
        };
        const wrapper = await createWrapper(
            {
                detailRoute: 'sw.manufacturer.detail',
                allowInlineEdit: true,
                columns: [
                    {
                        property: 'name',
                        label: 'Name',
                        inlineEdit: 'string',
                        primary: true,
                    },
                ],
            },
            {
                mocks: {
                    $router: router,
                },
            },
        );

        await wrapper.get('.sw-meteor-entity-data-table-inline-edit-cell__value-link').trigger('click');

        expect(router.push).toHaveBeenCalledWith({
            name: 'sw.manufacturer.detail',
            params: {
                id: 'manufacturer-1',
            },
        });
        expect(wrapper.emitted('open-detail')?.at(-1)?.[0]).toEqual({
            id: 'manufacturer-1',
            record: {
                id: 'manufacturer-1',
                name: 'Shopware',
            },
        });
    });

    it('opens detail from a clickable internal preview cell value', async () => {
        const router = {
            push: jest.fn(),
        };
        const wrapper = await createWrapper(
            {
                detailRoute: 'sw.manufacturer.detail',
                columns: [
                    {
                        property: 'name',
                        label: 'Name',
                        primary: true,
                    },
                ],
            },
            {
                mocks: {
                    $router: router,
                },
                slots: {
                    'preview-name': '<span class="preview-slot">Preview</span>',
                },
            },
        );

        await wrapper.get('.sw-meteor-entity-data-table__column-value-link').trigger('click');

        expect(router.push).toHaveBeenCalledWith({
            name: 'sw.manufacturer.detail',
            params: {
                id: 'manufacturer-1',
            },
        });
        expect(wrapper.emitted('open-detail')?.at(-1)?.[0]).toEqual({
            id: 'manufacturer-1',
            record: {
                id: 'manufacturer-1',
                name: 'Shopware',
            },
        });
    });

    it('passes edit permission through so mt-data-table renders the visible edit link', async () => {
        const wrapper = await createWrapper({
            allowEdit: true,
        });
        const dataTable = mountedTable(wrapper);

        expect(dataTable.props('disableEdit')).toBe(false);
        expect(dataTable.props('additionalContextButtons')).toEqual([]);
    });

    it('emits context-select for additional context buttons', async () => {
        const wrapper = await createWrapper({
            additionalContextButtons: [
                {
                    key: 'duplicate',
                    label: 'Duplicate',
                },
            ],
        });

        await mountedTable(wrapper).vm.$emit('context-select', {
            key: 'duplicate',
            data: {
                id: 'manufacturer-1',
            },
        });

        expect(wrapper.emitted('context-select')?.at(-1)?.[0]).toEqual({
            key: 'duplicate',
            data: {
                id: 'manufacturer-1',
            },
        });
    });

    it('passes disable edit and delete props to mt-data-table', async () => {
        const wrapper = await createWrapper({
            allowEdit: false,
            allowDelete: false,
        });
        const dataTable = mountedTable(wrapper);

        expect(dataTable.props('disableEdit')).toBe(true);
        expect(dataTable.props('disableDelete')).toBe(true);
        expect(dataTable.props('additionalContextButtons')).toEqual([]);
    });

    it('enables mt-data-table bulk delete only when selections and delete are allowed', async () => {
        const enabledWrapper = await createWrapper({
            allowDelete: true,
            showSelections: true,
        });
        const disabledWrapper = await createWrapper({
            allowDelete: true,
            showSelections: false,
        });

        expect(mountedTable(enabledWrapper).props('allowBulkDelete')).toBe(true);
        expect(mountedTable(disabledWrapper).props('allowBulkDelete')).toBe(false);
    });

    it('item-delete opens a delete modal', async () => {
        const wrapper = await createWrapper();

        await mountedTable(wrapper).vm.$emit('item-delete', {
            id: 'manufacturer-1',
            name: 'Shopware',
        });
        await flushPromises();

        expect(wrapper.find('.sw-meteor-entity-data-table-delete-modal').exists()).toBe(true);
        expect(wrapper.get('.sw-modal-stub').attributes('data-title')).toBe('global.default.warning');
        expect(wrapper.get('.sw-modal-stub').attributes('data-variant')).toBe('small');
    });

    it('confirming single delete calls repository.delete, emits delete-item-finish, and reloads', async () => {
        const repository = {
            search: jest.fn(() =>
                Promise.resolve(
                    createSearchResult([
                        { id: 'manufacturer-1', name: 'Shopware' },
                        { id: 'manufacturer-2', name: 'Meteor' },
                    ]),
                ),
            ),
            delete: jest.fn(() => Promise.resolve()),
        };
        const wrapper = await createWrapper({ repository });

        wrapper.vm.setSelectedIds(['manufacturer-1']);
        await mountedTable(wrapper).vm.$emit('item-delete', {
            id: 'manufacturer-1',
            name: 'Shopware',
        });
        await flushPromises();
        await wrapper.get('.sw-meteor-entity-data-table-delete-modal__confirm').trigger('click');
        await flushPromises();

        expect(repository.delete).toHaveBeenCalledWith('manufacturer-1', undefined);
        expect(wrapper.emitted('delete-item-finish')?.at(-1)?.[0]).toBe('manufacturer-1');
        expect(repository.search).toHaveBeenCalledTimes(2);
        expect(wrapper.vm.selectedIds).toEqual([]);
        expect(wrapper.find('.sw-meteor-entity-data-table-delete-modal').exists()).toBe(false);
    });

    it('delete modal uses wrapper buttons', async () => {
        const wrapper = mount(SwMeteorEntityDataTable, {
            props: {
                repository: {
                    search: jest.fn(() =>
                        Promise.resolve(
                            createSearchResult([
                                { id: 'manufacturer-1', name: 'Shopware' },
                            ]),
                        ),
                    ),
                },
                columns: [
                    {
                        property: 'name',
                        label: 'Name',
                    },
                ],
            },
            global: {
                stubs: {
                    'mt-data-table': {
                        template: '<div class="mt-data-table-stub"></div>',
                    },
                    'sw-modal': {
                        props: [
                            'title',
                            'variant',
                        ],
                        template:
                            '<div class="sw-modal-stub" :data-title="title" :data-variant="variant"><slot /><slot name="modal-footer" /></div>',
                    },
                    'mt-button': {
                        props: [
                            'size',
                            'variant',
                            'isLoading',
                        ],
                        template:
                            '<button class="mt-button-stub" :class="`mt-button-stub--${variant}`" type="button"><slot /></button>',
                    },
                },
                mocks: {
                    $t: (key: string) => key,
                },
            },
        }) as TestWrapper;

        await flushPromises();
        await mountedTable(wrapper).vm.$emit('item-delete', {
            id: 'manufacturer-1',
            name: 'Shopware',
        });
        await flushPromises();

        expect(wrapper.find('.sw-meteor-entity-data-table-delete-modal__cancel').exists()).toBe(true);
        expect(wrapper.find('.sw-meteor-entity-data-table-delete-modal__confirm').exists()).toBe(true);
    });

    it('failed single delete emits delete-item-failed and keeps loading consistent', async () => {
        const error = new Error('Delete failed');
        const repository = {
            search: jest.fn(() =>
                Promise.resolve(
                    createSearchResult([
                        { id: 'manufacturer-1', name: 'Shopware' },
                    ]),
                ),
            ),
            delete: jest.fn(() => Promise.reject(error)),
        };
        const wrapper = await createWrapper({ repository });

        await mountedTable(wrapper).vm.$emit('item-delete', {
            id: 'manufacturer-1',
            name: 'Shopware',
        });
        await flushPromises();
        await wrapper.get('.sw-meteor-entity-data-table-delete-modal__confirm').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('delete-item-failed')?.at(-1)?.[0]).toEqual({
            id: 'manufacturer-1',
            errorResponse: error,
        });
        expect(wrapper.vm.loading).toBe(false);
        expect(wrapper.find('.sw-meteor-entity-data-table-delete-modal').exists()).toBe(true);
    });

    it('delete modal renders default confirm text', async () => {
        const wrapper = mount(SwMeteorEntityDataTable, {
            props: {
                repository: {
                    search: jest.fn(() =>
                        Promise.resolve(
                            createSearchResult([
                                { id: 'manufacturer-1', name: 'Shopware' },
                            ]),
                        ),
                    ),
                },
                columns: [
                    {
                        property: 'name',
                        label: 'Name',
                    },
                ],
            },
            global: {
                stubs: {
                    'mt-data-table': {
                        template: '<div class="mt-data-table-stub"></div>',
                    },
                },
                mocks: {
                    $t: (key: string) => key,
                },
            },
        }) as TestWrapper;

        await flushPromises();
        await mountedTable(wrapper).vm.$emit('item-delete', {
            id: 'manufacturer-1',
            name: 'Shopware',
        });
        await flushPromises();

        expect(wrapper.get('.sw-meteor-entity-data-table-delete-modal__text').text()).toBe(
            'global.entity-components.deleteMessage',
        );
    });

    it('bulk delete opens a confirmation modal for selected ids', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.setSelectedIds([
            'manufacturer-1',
            'manufacturer-2',
        ]);
        await mountedTable(wrapper).vm.$emit('bulk-delete');
        await flushPromises();

        expect(wrapper.get('.sw-meteor-entity-data-table-bulk-delete-modal').text()).toContain('2');
        expect(wrapper.get('.sw-modal-stub').attributes('data-title')).toBe('global.default.warning');
        expect(wrapper.get('.sw-modal-stub').attributes('data-variant')).toBe('small');
    });

    it('confirming bulk delete calls repository.syncDeleted, emits items-delete-finish, clears selection, and reloads', async () => {
        const repository = {
            search: jest.fn(() =>
                Promise.resolve(
                    createSearchResult([
                        { id: 'manufacturer-1', name: 'Shopware' },
                        { id: 'manufacturer-2', name: 'Meteor' },
                    ]),
                ),
            ),
            syncDeleted: jest.fn(() => Promise.resolve()),
        };
        const wrapper = await createWrapper({ repository });

        wrapper.vm.setSelectedIds([
            'manufacturer-1',
            'manufacturer-2',
        ]);
        await mountedTable(wrapper).vm.$emit('bulk-delete');
        await flushPromises();
        await wrapper.get('.sw-meteor-entity-data-table-bulk-delete-modal__confirm').trigger('click');
        await flushPromises();

        expect(repository.syncDeleted).toHaveBeenCalledWith(
            [
                'manufacturer-1',
                'manufacturer-2',
            ],
            undefined,
        );
        expect(wrapper.emitted('items-delete-finish')).toHaveLength(1);
        expect(wrapper.vm.selectedIds).toEqual([]);
        expect(repository.search).toHaveBeenCalledTimes(2);
    });

    it('failed bulk delete emits delete-items-failed', async () => {
        const error = new Error('Bulk delete failed');
        const repository = {
            search: jest.fn(() =>
                Promise.resolve(
                    createSearchResult([
                        { id: 'manufacturer-1', name: 'Shopware' },
                    ]),
                ),
            ),
            syncDeleted: jest.fn(() => Promise.reject(error)),
        };
        const wrapper = await createWrapper({ repository });

        wrapper.vm.setSelectedIds(['manufacturer-1']);
        await mountedTable(wrapper).vm.$emit('bulk-delete');
        await flushPromises();
        await wrapper.get('.sw-meteor-entity-data-table-bulk-delete-modal__confirm').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('delete-items-failed')?.at(-1)?.[0]).toEqual({
            selectedIds: ['manufacturer-1'],
            errorResponse: error,
        });
    });

    it('bulk delete modal uses wrapper buttons', async () => {
        const wrapper = mount(SwMeteorEntityDataTable, {
            props: {
                repository: {
                    search: jest.fn(() =>
                        Promise.resolve(
                            createSearchResult([
                                { id: 'manufacturer-1', name: 'Shopware' },
                            ]),
                        ),
                    ),
                },
                columns: [
                    {
                        property: 'name',
                        label: 'Name',
                    },
                ],
                showSelections: true,
            },
            global: {
                stubs: {
                    'mt-data-table': {
                        template: '<div class="mt-data-table-stub"></div>',
                    },
                    'sw-modal': {
                        props: [
                            'title',
                            'variant',
                        ],
                        template:
                            '<div class="sw-modal-stub" :data-title="title" :data-variant="variant"><slot /><slot name="modal-footer" /></div>',
                    },
                    'mt-button': {
                        props: [
                            'size',
                            'variant',
                            'isLoading',
                        ],
                        template:
                            '<button class="mt-button-stub" :class="`mt-button-stub--${variant}`" type="button"><slot /></button>',
                    },
                },
                mocks: {
                    $t: (key: string) => key,
                },
            },
        }) as TestWrapper;

        await flushPromises();
        wrapper.vm.setSelectedIds(['manufacturer-1']);
        await mountedTable(wrapper).vm.$emit('bulk-delete');
        await flushPromises();

        expect(wrapper.find('.sw-meteor-entity-data-table-bulk-delete-modal__cancel').exists()).toBe(true);
        expect(wrapper.find('.sw-meteor-entity-data-table-bulk-delete-modal__confirm').exists()).toBe(true);
    });

    it('marks a row as inline editing', async () => {
        const wrapper = await createWrapper({
            allowInlineEdit: true,
            columns: [
                {
                    property: 'name',
                    label: 'Name',
                    inlineEdit: 'string',
                },
            ],
        });

        await wrapper.get('.sw-meteor-entity-data-table-inline-edit-cell__start').trigger('click');
        await flushPromises();

        expect(wrapper.get('.sw-meteor-entity-data-table-inline-edit-cell__input').element).toBeDefined();
    });

    it('updates a record field locally and saves inline edit through the repository', async () => {
        const repository = {
            search: jest.fn(() =>
                Promise.resolve(
                    createSearchResult([
                        { id: 'manufacturer-1', name: 'Shopware' },
                    ]),
                ),
            ),
            save: jest.fn(() => Promise.resolve()),
        };
        const wrapper = await createWrapper({
            repository,
            allowInlineEdit: true,
            columns: [
                {
                    property: 'name',
                    label: 'Name',
                    inlineEdit: 'string',
                },
            ],
        });

        await wrapper.get('.sw-meteor-entity-data-table-inline-edit-cell__start').trigger('click');
        await wrapper.get('.sw-meteor-entity-data-table-inline-edit-cell__input').setValue('Updated manufacturer');
        await wrapper.get('.sw-meteor-entity-data-table-inline-edit-cell__save').trigger('click');
        await flushPromises();

        expect(repository.save).toHaveBeenCalledWith(
            {
                id: 'manufacturer-1',
                name: 'Updated manufacturer',
            },
            undefined,
        );

        const inlineEditSaveEvent = wrapper.emitted('inline-edit-save')?.[0];
        expect(inlineEditSaveEvent?.[0]).toBeInstanceOf(Promise);
        expect(inlineEditSaveEvent?.[1]).toEqual({
            id: 'manufacturer-1',
            name: 'Updated manufacturer',
        });
        expect(repository.search).toHaveBeenCalledTimes(2);
    });

    it('cancels inline edit and reloads', async () => {
        const repository = {
            search: jest.fn(() =>
                Promise.resolve(
                    createSearchResult([
                        { id: 'manufacturer-1', name: 'Shopware' },
                    ]),
                ),
            ),
            save: jest.fn(() => Promise.resolve()),
        };
        const wrapper = await createWrapper({
            repository,
            allowInlineEdit: true,
            columns: [
                {
                    property: 'name',
                    label: 'Name',
                    inlineEdit: 'string',
                },
            ],
        });

        await wrapper.get('.sw-meteor-entity-data-table-inline-edit-cell__start').trigger('click');
        await wrapper.get('.sw-meteor-entity-data-table-inline-edit-cell__cancel').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-meteor-entity-data-table-inline-edit-cell__input').exists()).toBe(false);
        expect(wrapper.emitted('inline-edit-cancel')?.[0]?.[0]).toBeInstanceOf(Promise);
        expect(repository.search).toHaveBeenCalledTimes(2);
    });

    it('does not add wrapper-only inline edit state to external column slots', async () => {
        const wrapper = mount(SwMeteorEntityDataTable, {
            props: {
                repository: {
                    search: jest.fn(() =>
                        Promise.resolve(
                            createSearchResult([
                                { id: 'manufacturer-1', name: 'Shopware' },
                            ]),
                        ),
                    ),
                },
                columns: [
                    {
                        property: 'name',
                        label: 'Name',
                        inlineEdit: 'string',
                    },
                ],
            },
            slots: {
                'column-name':
                    '<template #default="{ isInlineEdit }"><span class="inline-slot">{{ isInlineEdit === undefined ? "no-alias" : "alias" }}</span></template>',
            },
            global: {
                stubs: {
                    'mt-data-table': {
                        props: [
                            'dataSource',
                            'columns',
                        ],
                        template: `
                            <div class="mt-data-table-stub">
                                <slot
                                    name="column-name"
                                    :data="dataSource[0]"
                                    :column-definition="columns[0]"
                                />
                            </div>
                        `,
                    },
                },
                mocks: {
                    $t: (key: string) => key,
                },
            },
        }) as TestWrapper;

        await flushPromises();
        expect(wrapper.get('.inline-slot').text()).toBe('no-alias');

        wrapper.vm.startInlineEdit(wrapper.vm.records[0]);
        await flushPromises();

        expect(wrapper.get('.inline-slot').text()).toBe('no-alias');
    });

    it('exposes the public createExtendableSetup API keys on the component instance', async () => {
        const wrapper = await createWrapper();

        [
            'records',
            'total',
            'loading',
            'state',
            'selectedIds',
            'selection',
            'resolvedColumns',
            'load',
            'reload',
            'buildCriteria',
            'setPage',
            'setLimit',
            'setSearchTerm',
            'setSort',
            'setSelectedIds',
            'openDetail',
        ].forEach((key) => {
            expect(wrapper.vm[key]).toBeDefined();
        });
    });

    it('keeps template handlers in the private createExtendableSetup state', async () => {
        const wrapper = await createWrapper();
        let privateStateKeys: string[] = [];

        overrideComponentSetup()('sw-meteor-entity-data-table', (previousState) => {
            const privateState = (previousState as unknown as { _private: Record<string, unknown> })._private;
            privateStateKeys = Object.keys(privateState);

            return {
                load: previousState.load,
            };
        });
        await flushPromises();

        expect(privateStateKeys).toEqual(
            expect.arrayContaining([
                'handlePaginationCurrentPageChange',
                'handlePaginationLimitChange',
                'handleSortChange',
                'handleSearchValueChange',
                'handleSelectionChange',
                'handleMultipleSelectionChange',
                'handleContextSelect',
            ]),
        );
        expect(wrapper.vm.handlePaginationCurrentPageChange).toBeDefined();
    });

    it('overrideComponentSetup can override a public command', async () => {
        const wrapper = await createWrapper();
        const loadOverride = jest.fn(() => Promise.resolve([]));

        overrideComponentSetup()('sw-meteor-entity-data-table', () => {
            return {
                load: loadOverride,
            };
        });
        await flushPromises();

        await wrapper.vm.load();

        expect(loadOverride).toHaveBeenCalledTimes(1);
    });

    it('does not expose private helpers to override callbacks', async () => {
        const wrapper = await createWrapper();
        let previousStateKeys: string[] = [];
        let observedRecords: unknown = null;

        overrideComponentSetup()('sw-meteor-entity-data-table', (previousState) => {
            previousStateKeys = Object.keys(previousState);
            observedRecords = previousState.records.value;

            return {
                load: previousState.load,
            };
        });
        await flushPromises();

        expect(wrapper.vm.confirmDelete).toBeDefined();
        expect(previousStateKeys).toContain('records');
        expect(previousStateKeys).toContain('load');
        expect(previousStateKeys).not.toContain('confirmDelete');
        expect(observedRecords).toEqual([
            { id: 'manufacturer-1', name: 'Shopware' },
            { id: 'manufacturer-2', name: 'Meteor' },
        ]);
    });
});
