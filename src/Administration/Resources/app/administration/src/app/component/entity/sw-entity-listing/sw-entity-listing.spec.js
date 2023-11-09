/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/data-grid/sw-data-grid-settings';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/entity/sw-entity-listing';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

async function createWrapper(propsData = {}) {
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

    return shallowMount(await Shopware.Component.build('sw-entity-listing'), {
        stubs: {
            'sw-data-grid-settings': await Shopware.Component.build('sw-data-grid-settings'),
            'sw-button': true,
            'sw-context-button': true,
            'sw-icon': true,
            'sw-field': true,
            'sw-switch-field': true,
            'sw-context-menu-divider': true,
            'sw-pagination': true,
            'sw-checkbox-field': true,
            'sw-context-menu-item': true,
            'sw-data-grid-skeleton': true,
        },
        provide: {},
        propsData: {
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
    });
}

describe('src/app/component/entity/sw-entity-listing', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

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

        expect(elements.exists()).toBeTruthy();
        elements.wrappers.forEach(el => expect(el.text()).toBe('global.default.edit'));
        expect(elements.wrappers).toHaveLength(3);
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

        expect(elements.exists()).toBeTruthy();
        elements.wrappers.forEach(el => expect(el.text()).toBe('global.default.view'));
        expect(elements.wrappers).toHaveLength(3);
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

        const elements = wrapper.findAll('.sw-entity-listing__context-menu-edit-action');

        expect(elements.exists()).toBeTruthy();
        expect(elements.wrappers).toHaveLength(3);
        elements.wrappers.forEach(el => expect(el.text()).toBe('global.default.edit'));
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBe('true'));
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
        wrapper.vm.$emit = jest.fn();
        wrapper.vm.onClickBulkEdit();

        await flushPromises();
        expect(wrapper.vm.$emit).toHaveBeenCalledWith('bulk-edit-modal-open');
    });

    it('should call emit when user close bulk edit modal', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.$emit = jest.fn();
        wrapper.vm.onCloseBulkEditModal();

        await flushPromises();
        expect(wrapper.vm.$emit).toHaveBeenCalledWith('bulk-edit-modal-close');
    });
});
