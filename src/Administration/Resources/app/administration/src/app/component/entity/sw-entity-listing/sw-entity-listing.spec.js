/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

async function createWrapper(propsData = {}, options = {}) {
    // mock entity functions
    const items = [
        { name: 'Apple' },
        { name: 'Shopware' },
        { name: 'Google' },
        { name: 'Microsoft' },
    ];
    items.total = 4;
    items.criteria = {
        page: null,
        limit: null,
    };

    // Suppress console warnings by default unless explicitly testing them
    const consoleWarnSpy = options.suppressWarnings !== false ? jest.spyOn(console, 'warn').mockImplementation() : null;

    const wrapper = mount(await wrapTestComponent('sw-entity-listing', { sync: true }), {
        props: {
            columns: [
                { property: 'name', label: 'Name' },
            ],
            items: new EntityCollection(null, null, null, new Criteria(1, 25), [
                { id: 'id1', name: 'item1' },
                { id: 'id2', name: 'item2' },
            ]),
            repository: {
                search: () => {},
            },
            detailRoute: 'sw.manufacturer.detail',
            ...propsData,
        },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-data-grid-settings': await wrapTestComponent('sw-data-grid-settings'),
                'sw-context-button': true,
                'sw-field': true,

                'sw-context-menu-divider': true,
                'sw-pagination': true,
                'sw-checkbox-field': true,
                'sw-context-menu-item': true,
                'sw-data-grid-skeleton': true,
                'sw-bulk-edit-modal': true,
                'sw-data-grid-column-boolean': true,
                'sw-data-grid-inline-edit': true,
                'router-link': true,
                'sw-button-group': true,
                'sw-provide': true,
            },
        },
    });

    if (consoleWarnSpy) {
        consoleWarnSpy.mockRestore();
    }

    return wrapper;
}

describe('src/app/component/entity/sw-entity-listing', () => {
    it('should enable the context menu edit item', async () => {
        const wrapper = await createWrapper();

        const firstRow = wrapper.find('.sw-data-grid__row--1');
        const firstRowActions = firstRow.find('.sw-data-grid__cell--actions');
        const firstRowActionEdit = firstRowActions.find('.sw-entity-listing__context-menu-edit-action');

        expect(firstRowActionEdit.exists()).toBeTruthy();
        expect(firstRowActionEdit.attributes().disabled).toBeFalsy();
    });

    it('should disable the context menu edit item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            allowEdit: false,
        });

        const firstRow = wrapper.find('.sw-data-grid__row--1');
        const firstRowActions = firstRow.find('.sw-data-grid__cell--actions');
        const firstRowActionEdit = firstRowActions.find('.sw-entity-listing__context-menu-edit-action');

        expect(firstRowActionEdit.exists()).toBeTruthy();
        expect(firstRowActionEdit.attributes().disabled).toBeTruthy();
    });

    it('should enable the context menu delete item', async () => {
        const wrapper = await createWrapper();

        const firstRow = wrapper.find('.sw-data-grid__row--1');
        const firstRowActions = firstRow.find('.sw-data-grid__cell--actions');
        const firstRowActionDelete = firstRowActions.find('.sw-entity-listing__context-menu-edit-delete');

        expect(firstRowActionDelete.exists()).toBeTruthy();
        expect(firstRowActionDelete.attributes().disabled).toBeFalsy();
    });

    it('should disable the context menu delete item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            allowDelete: false,
        });

        const firstRow = wrapper.find('.sw-data-grid__row--1');
        const firstRowActions = firstRow.find('.sw-data-grid__cell--actions');
        const firstRowActionDelete = firstRowActions.find('.sw-entity-listing__context-menu-edit-delete');

        expect(firstRowActionDelete.exists()).toBeTruthy();
        expect(firstRowActionDelete.attributes().disabled).toBeTruthy();
    });

    it('should have context menu with edit entry', async () => {
        const wrapper = await createWrapper({
            allowEdit: true,
            items: new EntityCollection(null, null, null, new Criteria(1, 25), [
                { id: 'id1', name: 'item1' },
                { id: 'id2', name: 'item2' },
                { id: 'id3', name: 'item3' },
            ]),
        });

        const elements = wrapper.findAll('.sw-entity-listing__context-menu-edit-action');

        elements.forEach((el) => expect(el.text()).toBe('global.default.edit'));
        expect(elements).toHaveLength(3);
    });

    it('should have context menu with view entry', async () => {
        const wrapper = await createWrapper({
            allowEdit: false,
            allowView: true,
            items: new EntityCollection(null, null, null, new Criteria(1, 25), [
                { id: 'id1', name: 'item1' },
                { id: 'id2', name: 'item2' },
                { id: 'id3', name: 'item3' },
            ]),
        });

        const elements = wrapper.findAll('.sw-entity-listing__context-menu-edit-action');

        elements.forEach((el) => expect(el.text()).toBe('global.default.view'));
        expect(elements).toHaveLength(3);
    });

    it('should have context menu with disabled edit entry', async () => {
        const wrapper = await createWrapper({
            allowEdit: false,
            allowView: false,
            items: new EntityCollection(null, null, null, new Criteria(1, 25), [
                { id: 'id1', name: 'item1' },
                { id: 'id2', name: 'item2' },
                { id: 'id3', name: 'item3' },
            ]),
        });
        await flushPromises();

        const elements = wrapper.findAll('.sw-entity-listing__context-menu-edit-action');

        expect(elements).toHaveLength(3);
        elements.forEach((el) => expect(el.text()).toBe('global.default.edit'));
        elements.forEach((el) => expect(el.attributes().disabled).toBe('true'));
    });

    it('should show delete id', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.deleteId).toBeNull();
        wrapper.vm.showDelete('123');
        expect(wrapper.vm.deleteId).toBe('123');
    });

    it('should refresh delete id when close delete modal', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.showDelete('123');
        expect(wrapper.vm.deleteId).toBe('123');
        wrapper.vm.closeModal();
        expect(wrapper.vm.deleteId).toBeNull();
    });

    it('should able to apply result when items prop has been changed', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.applyResult = jest.fn();
        await wrapper.setProps({
            items: new EntityCollection(null, null, null, new Criteria(1, 25), [
                { id: 'id1', name: 'item1' },
                { id: 'id2', name: 'item2' },
                { id: 'id3', name: 'item3' },
            ]),
        });

        await flushPromises();
        expect(wrapper.vm.applyResult).toHaveBeenCalled();
    });

    it('should call emit when user click bulk edit button', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.onClickBulkEdit();

        await flushPromises();
        expect(wrapper.emitted('bulk-edit-modal-open')).toStrictEqual([[]]);
    });

    it('should call emit when user close bulk edit modal', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.onCloseBulkEditModal();

        await flushPromises();
        expect(wrapper.emitted('bulk-edit-modal-close')).toStrictEqual([[]]);
    });

    it('should work with dataSource prop instead of items', async () => {
        const dataSource = new EntityCollection(null, null, null, new Criteria(1, 25), [
            { id: 'id1', name: 'item1' },
            { id: 'id2', name: 'item2' },
        ]);

        const wrapper = await createWrapper({
            dataSource,
            items: null,
        });

        // Check that internalDataSource returns the correct data
        expect(wrapper.vm.internalDataSource).toHaveLength(2);
        expect(wrapper.vm.internalDataSource[0].id).toBe('id1');
        expect(wrapper.vm.internalDataSource[1].id).toBe('id2');
        expect(wrapper.vm.records).toHaveLength(2);
    });

    it('should show deprecation warning when items prop is used', async () => {
        const consoleWarnSpy = jest.spyOn(console, 'warn').mockImplementation();

        await createWrapper(
            {
                items: new EntityCollection(null, null, null, new Criteria(1, 25), [
                    { id: 'id1', name: 'item1' },
                ]),
                dataSource: null,
            },
            { suppressWarnings: false },
        );

        expect(consoleWarnSpy).toHaveBeenCalledWith(
            expect.stringContaining('[Deprecation] sw-entity-listing: The "items" prop is deprecated'),
            expect.anything(),
        );

        consoleWarnSpy.mockRestore();
    });

    it('should prefer dataSource over items when both are provided', async () => {
        const itemsData = new EntityCollection(null, null, null, new Criteria(1, 25), [
            { id: 'id1', name: 'item1' },
        ]);

        const dataSourceData = new EntityCollection(null, null, null, new Criteria(1, 25), [
            { id: 'id2', name: 'item2' },
            { id: 'id3', name: 'item3' },
        ]);

        const wrapper = await createWrapper({
            items: itemsData,
            dataSource: dataSourceData,
        });

        // Check that internalDataSource prefers dataSource over items
        // It should use dataSourceData (with ids id2 and id3) not itemsData (with id id1)
        expect(wrapper.vm.internalDataSource).toHaveLength(2);
        expect(wrapper.vm.internalDataSource[0].id).toBe('id2');
        expect(wrapper.vm.internalDataSource[1].id).toBe('id3');
        expect(wrapper.vm.records).toHaveLength(2);
    });

    it('should not show deprecation warning when dataSource is used', async () => {
        const consoleWarnSpy = jest.spyOn(console, 'warn').mockImplementation();

        await createWrapper(
            {
                dataSource: new EntityCollection(null, null, null, new Criteria(1, 25), [
                    { id: 'id1', name: 'item1' },
                ]),
                items: null,
            },
            { suppressWarnings: false },
        );

        // Check that no deprecation warning for items prop was shown
        const deprecationCalls = consoleWarnSpy.mock.calls.filter(
            (call) => call[0] && call[0].includes('[Deprecation] sw-entity-listing'),
        );
        expect(deprecationCalls).toHaveLength(0);

        consoleWarnSpy.mockRestore();
    });

    it('should apply result when dataSource prop has been changed', async () => {
        const wrapper = await createWrapper({
            dataSource: new EntityCollection(null, null, null, new Criteria(1, 25), [
                { id: 'id1', name: 'item1' },
            ]),
            items: null,
        });

        wrapper.vm.applyResult = jest.fn();

        await wrapper.setProps({
            dataSource: new EntityCollection(null, null, null, new Criteria(1, 25), [
                { id: 'id2', name: 'item2' },
                { id: 'id3', name: 'item3' },
            ]),
        });

        await flushPromises();
        expect(wrapper.vm.applyResult).toHaveBeenCalled();
    });

    it('should use internalDataSource for operations', async () => {
        const dataSource = new EntityCollection(null, null, null, new Criteria(1, 25), [
            { id: 'id1', name: 'item1' },
        ]);
        dataSource.context = { apiContext: true };
        dataSource.criteria = new Criteria(1, 25);

        const mockSearchResult = new EntityCollection(null, null, null, new Criteria(1, 25), [
            { id: 'id1', name: 'item1' },
        ]);

        const wrapper = await createWrapper({
            dataSource,
            items: null,
            repository: {
                search: jest.fn(() => Promise.resolve(mockSearchResult)),
                delete: jest.fn(() => Promise.resolve()),
                save: jest.fn(() => Promise.resolve()),
            },
        });

        // Test that doSearch uses internalDataSource
        await wrapper.vm.doSearch();
        expect(wrapper.vm.repository.search).toHaveBeenCalledWith(dataSource.criteria, dataSource.context);
    });
});
