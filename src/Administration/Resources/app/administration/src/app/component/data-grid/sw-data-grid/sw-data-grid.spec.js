/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import Entity from 'src/core/data/entity.data';
import EntityCollection from 'src/core/data/entity-collection.data';

/* const localVue = createLocalVue();
localVue.directive('popover', {});
localVue.directive('tooltip', {
    bind(el, binding) {
        el.setAttribute('data-tooltip-message', binding.value);
    },
}); */

const defaultUserConfig = {
    createdAt: '2021-01-21T06:52:41.857+00:00',
    id: '021150d043ee49e18642daef58e92c96',
    key: 'grid.setting.sw-customer-list',
    updatedAt: '2021-01-21T06:54:00.252+00:00',
    userId: 'd9a43905b72e43b7b669c6b005a3cf15',
    value: {
        columns: [
            {
                dataIndex: 'name',
                label: 'Name',
                property: 'name',
                visible: false,
            },
            {
                dataIndex: 'company',
                label: 'Company',
                property: 'company',
                visible: false,
            },
        ],
        compact: true,
        previews: false,
    },
};

const defaultProps = {
    identifier: 'sw-customer-list',
    columns: [
        { property: 'name', label: 'Name' },
        { property: 'company', label: 'Company' },
    ],
    dataSource: [
        { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
        { id: 'uuid2', company: 'Twitternation', name: 'Baxy Eardley' },
        { id: 'uuid3', company: 'Skidoo', name: 'Arturo Staker' },
        { id: 'uuid4', company: 'Meetz', name: 'Dalston Top' },
        { id: 'uuid5', company: 'Photojam', name: 'Neddy Jensen' },
    ],
};

describe('components/data-grid/sw-data-grid', () => {
    let stubs;

    async function createWrapper(props, userConfig, overrideProps) {
        if (!overrideProps) {
            props = { ...defaultProps, ...props };
        }

        stubs = {
            'sw-switch-field': await wrapTestComponent('sw-switch-field', { sync: true }),
            'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
            'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field', { sync: true }),
            'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
            'sw-data-grid-settings': await wrapTestComponent('sw-data-grid-settings', { sync: true }),
            'sw-icon': await wrapTestComponent('sw-icon', { sync: true }),
            'sw-icon-deprecated': await wrapTestComponent('sw-icon-deprecated', { sync: true }),
            'sw-context-button': await wrapTestComponent('sw-context-button', { sync: true }),
            'sw-context-menu': await wrapTestComponent('sw-context-menu', { sync: true }),
            'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item', { sync: true }),
            'sw-button': await wrapTestComponent('sw-button', { sync: true }),
            'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
            'sw-popover': await wrapTestComponent('sw-popover'),
            'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
            'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
            'sw-field-error': true,
            'sw-context-menu-divider': true,
            'sw-button-group': true,
        };

        return mount(await wrapTestComponent('sw-data-grid', { sync: true }), {
            global: {
                directives: {
                    popover: {},
                    tooltip: {
                        bind(el, binding) {
                            el.setAttribute('data-tooltip-message', binding.value);
                        },
                    },
                },
                stubs,
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => {
                                return Promise.resolve([userConfig ?? defaultUserConfig]);
                            },
                            save: () => {
                                return Promise.resolve();
                            },
                            get: () => Promise.resolve({}),
                        }),
                    },
                    acl: { can: () => true },
                },
            },
            props: props ?? defaultProps,
        });
    }

    beforeAll(async () => {
        stubs = {
            'sw-switch-field': await wrapTestComponent('sw-switch-field', { sync: true }),
            'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
            'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field', { sync: true }),
            'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
            'sw-data-grid-settings': await wrapTestComponent('sw-data-grid-settings', { sync: true }),
            'sw-icon': true,
            'sw-context-button': await wrapTestComponent('sw-context-button', { sync: true }),
            'sw-context-menu': await wrapTestComponent('sw-context-menu', { sync: true }),
            'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item', { sync: true }),
            'sw-button': await wrapTestComponent('sw-button', { sync: true }),
            'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
            'sw-popover': await wrapTestComponent('sw-popover'),
            'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
            'sw-field-error': true,
            'sw-context-menu-divider': true,
            'sw-button-group': true,
        };
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be in compact mode by default', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.classes()).toContain('is--compact');
    });

    it('should render grid header with correct columns', async () => {
        const wrapper = await createWrapper();

        const nameColumn = wrapper.find('.sw-data-grid__header .sw-data-grid__cell--0 .sw-data-grid__cell-content');
        const companyColumn = wrapper.find('.sw-data-grid__header .sw-data-grid__cell--1 .sw-data-grid__cell-content');
        const selectionColumn = wrapper.find('.sw-data-grid__header .sw-data-grid__cell--selection');
        const actionColumn = wrapper.find('.sw-data-grid__header .sw-data-grid__cell--actions');

        expect(selectionColumn.exists()).toBeTruthy();
        expect(actionColumn.exists()).toBeTruthy();

        expect(nameColumn.text()).toBe('Name');
        expect(companyColumn.text()).toBe('Company');
    });

    it('should hide selection column, action column and header based on prop', async () => {
        const wrapper = await createWrapper({
            showSelection: false,
            showActions: false,
            showHeader: false,
        });

        const header = wrapper.find('.sw-data-grid__header');
        const selectionColumn = wrapper.find('.sw-data-grid__header .sw-data-grid__cell--selection');
        const actionColumn = wrapper.find('.sw-data-grid__header .sw-data-grid__cell--actions');

        expect(header.exists()).toBeFalsy();
        expect(selectionColumn.exists()).toBeFalsy();
        expect(actionColumn.exists()).toBeFalsy();
    });

    it('should render a row for each item in dataSource prop', async () => {
        const wrapper = await createWrapper();

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        expect(rows).toHaveLength(5);
    });

    it('should change appearance class based on prop', async () => {
        const wrapper = await createWrapper({
            plainAppearance: true,
        });

        expect(wrapper.classes()).toContain('sw-data-grid--plain-appearance');
    });

    it('should load and apply user configuration', async () => {
        const wrapper = await createWrapper({
            showSettings: true,
        });

        expect(wrapper.vm.showSettings).toBe(true);
        expect(wrapper.findComponent(stubs['sw-context-menu']).exists()).toBe(false);

        await flushPromises();

        // find and click button setting
        const contextButtonSetting = wrapper.find('.sw-data-grid-settings__trigger');
        await contextButtonSetting.trigger('click');

        await flushPromises();

        // show popover
        const popover = wrapper.findComponent(stubs['sw-context-menu']);
        expect(popover.exists()).toBe(true);
        expect(popover.findAll('.sw-data-grid__settings-column-item')).toHaveLength(2);

        // check default columns
        expect(wrapper.vm.currentColumns[0].visible).toBe(defaultUserConfig.value.columns[0].visible);
        expect(wrapper.vm.currentColumns[1].visible).toBe(defaultUserConfig.value.columns[1].visible);

        expect(wrapper.vm.compact).toBe(defaultUserConfig.value.compact);
        expect(wrapper.vm.previews).toBe(defaultUserConfig.value.previews);

        const valueChecked = !defaultUserConfig.value.columns[0].visible;

        const name = wrapper.find('.sw-data-grid__settings-item--0 input');
        await name.setChecked(valueChecked);

        expect(wrapper.vm.currentColumns[0].visible).toBe(valueChecked);
    });

    it('remove property in client', async () => {
        const wrapper = await createWrapper({
            showSettings: true,
            identifier: 'sw-customer-list',
            columns: [
                { property: 'name', label: 'Name' },
            ],
            dataSource: [
                { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
                { id: 'uuid2', company: 'Twitternation', name: 'Baxy Eardley' },
            ],
        }, {
            createdAt: '2021-01-21T06:52:41.857+00:00',
            id: '021150d043ee49e18642daef58e92c96',
            key: 'grid.setting.sw-customer-list',
            updatedAt: '2021-01-21T06:54:00.252+00:00',
            userId: 'd9a43905b72e43b7b669c6b005a3cf15',
            value: {
                columns: [
                    {
                        dataIndex: 'name',
                        label: 'Name',
                        property: 'name',
                        visible: false,
                    },
                    {
                        dataIndex: 'company',
                        label: 'Company',
                        property: 'company',
                        visible: false,
                    },
                ],
                compact: true,
                previews: true,
            },
        }, true);

        expect(wrapper.vm.showSettings).toBe(true);
        expect(wrapper.findComponent(stubs['sw-context-menu']).exists()).toBe(false);

        await wrapper.vm.$nextTick();

        // find and click button setting
        const contextButtonSetting = wrapper.find('.sw-data-grid-settings__trigger');
        await contextButtonSetting.trigger('click');

        await flushPromises();

        // show popover
        const popover = wrapper.findComponent(stubs['sw-context-menu']);
        expect(popover.exists()).toBe(true);
        expect(popover.findAll('.sw-data-grid__settings-column-item')).toHaveLength(1);


        // check default columns
        expect(wrapper.vm.currentColumns[0].visible).toBe(false);
        expect(wrapper.vm.currentColumns[1]).toBeUndefined();

        expect(wrapper.vm.compact).toBe(true);
        expect(wrapper.vm.previews).toBe(true);
    });

    it('add property in client', async () => {
        const wrapper = await createWrapper({
            showSettings: true,
            identifier: 'sw-customer-list',
            columns: [
                { property: 'name', label: 'Name' },
                { property: 'company', label: 'Company' },
            ],
            dataSource: [
                { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
                { id: 'uuid2', company: 'Twitternation', name: 'Baxy Eardley' },
            ],
        }, {
            createdAt: '2021-01-21T06:52:41.857+00:00',
            id: '021150d043ee49e18642daef58e92c96',
            key: 'grid.setting.sw-customer-list',
            updatedAt: '2021-01-21T06:54:00.252+00:00',
            userId: 'd9a43905b72e43b7b669c6b005a3cf15',
            value: {
                columns: [
                    {
                        dataIndex: 'name',
                        label: 'Name',
                        property: 'name',
                        visible: false,
                    },
                ],
                compact: true,
                previews: true,
            },
        }, true);

        expect(wrapper.vm.showSettings).toBe(true);
        expect(wrapper.findComponent(stubs['sw-context-menu']).exists()).toBe(false);

        await wrapper.vm.$nextTick();

        // find and click button setting
        const contextButtonSetting = wrapper.find('.sw-data-grid-settings__trigger');
        await contextButtonSetting.trigger('click');

        await flushPromises();

        // show popover
        const popover = wrapper.findComponent(stubs['sw-context-menu']);
        expect(popover.exists()).toBe(true);
        expect(popover.findAll('.sw-data-grid__settings-column-item')).toHaveLength(2);


        // check default columns
        expect(wrapper.vm.currentColumns[0].visible).toBe(false);
        expect(wrapper.vm.currentColumns[1].visible).toBe(true);

        expect(wrapper.vm.compact).toBe(true);
        expect(wrapper.vm.previews).toBe(true);
    });

    it('add property value in client', async () => {
        const wrapper = await createWrapper({
            showSettings: true,
            identifier: 'sw-customer-list',
            columns: [
                { property: 'name', label: 'Name', mockProperty: true },
            ],
            dataSource: [
                { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
            ],
        }, {
            createdAt: '2021-01-21T06:52:41.857+00:00',
            id: '021150d043ee49e18642daef58e92c96',
            key: 'grid.setting.sw-customer-list',
            updatedAt: '2021-01-21T06:54:00.252+00:00',
            userId: 'd9a43905b72e43b7b669c6b005a3cf15',
            value: {
                columns: [
                    {
                        dataIndex: 'name',
                        label: 'Name',
                        property: 'name',
                        visible: false,
                    },
                    {
                        dataIndex: 'company',
                        label: 'Company',
                        property: 'company',
                        visible: false,
                    },
                ],
                compact: true,
                previews: true,
            },
        }, true);

        expect(wrapper.vm.showSettings).toBe(true);
        expect(wrapper.findComponent(stubs['sw-context-menu']).exists()).toBe(false);

        await wrapper.vm.$nextTick();

        // find and click button setting
        const contextButtonSetting = wrapper.find('.sw-data-grid-settings__trigger');
        await contextButtonSetting.trigger('click');

        await flushPromises();

        // show popover
        const popover = wrapper.findComponent(stubs['sw-context-menu']);
        expect(popover.exists()).toBe(true);
        expect(popover.findAll('.sw-data-grid__settings-column-item')).toHaveLength(1);


        // check default columns
        expect(wrapper.vm.currentColumns[0].visible).toBe(false);
        expect(wrapper.vm.currentColumns[0].mockProperty).toBe(true);

        expect(wrapper.vm.compact).toBe(true);
        expect(wrapper.vm.previews).toBe(true);
    });

    it('remove property value in client', async () => {
        const wrapper = await createWrapper({
            showSettings: true,
            identifier: 'sw-customer-list',
            columns: [
                { property: 'name', label: 'Name' },
            ],
            dataSource: [
                { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
                { id: 'uuid2', company: 'Twitternation', name: 'Baxy Eardley' },
            ],
        }, {
            createdAt: '2021-01-21T06:52:41.857+00:00',
            id: '021150d043ee49e18642daef58e92c96',
            key: 'grid.setting.sw-customer-list',
            updatedAt: '2021-01-21T06:54:00.252+00:00',
            userId: 'd9a43905b72e43b7b669c6b005a3cf15',
            value: {
                columns: [
                    {
                        dataIndex: 'name',
                        label: 'Name',
                        property: 'name',
                        visible: false,
                        mockProperty: true,
                    },
                ],
                compact: true,
                previews: true,
            },
        }, true);

        expect(wrapper.vm.showSettings).toBe(true);
        expect(wrapper.findComponent(stubs['sw-context-menu']).exists()).toBe(false);

        await wrapper.vm.$nextTick();

        // find and click button setting
        const contextButtonSetting = wrapper.find('.sw-data-grid-settings__trigger');
        await contextButtonSetting.trigger('click');

        await flushPromises();

        // show popover
        const popover = wrapper.findComponent(stubs['sw-context-menu']);
        expect(popover.exists()).toBe(true);
        expect(popover.findAll('.sw-data-grid__settings-column-item')).toHaveLength(1);

        // check default columns
        expect(wrapper.vm.currentColumns[0].visible).toBe(false);
        expect(wrapper.vm.currentColumns[0].mockProperty).toBeUndefined();


        expect(wrapper.vm.compact).toBe(true);
        expect(wrapper.vm.previews).toBe(true);
    });

    const cases = {
        'simple field': { accessor: 'id', expected: '123' },
        'translated field': { accessor: 'name', expected: 'translated' },
        'translated field with accessor': { accessor: 'translated.name', expected: 'translated' },
        'nested object with simple field': {
            accessor: 'manufacturer.description',
            expected: 'manufacturer-description',
        },
        'nested object with translated field': {
            accessor: 'manufacturer.name',
            expected: 'manufacturer-translated',
        },
        'nested object with translated field with accessor': {
            accessor: 'manufacturer.translated.name',
            expected: 'manufacturer-translated',
        },
        'unknown field': { accessor: 'unknown', expected: undefined },
        'nested unknown field': { accessor: 'manufacturer.unknown', expected: undefined },
        'unknown nested object': {
            accessor: 'unknown.unknown',
            expected: undefined,
            errorMsg: '[[sw-data-grid] Can not resolve accessor: unknown.unknown]',
        },

        'test last function': { accessor: 'transactions.last().name', expected: 'last' },
        'test first function': { accessor: 'transactions.first().name', expected: 'first' },
        'test array access on collection': { accessor: 'transactions[1].name', expected: 'second' },

        'test array element 1': { accessor: 'arrayField[0]', expected: 1 },
        'test array element 2': { accessor: 'arrayField[1]', expected: 2 },
        'test array element 3': { accessor: 'arrayField[2]', expected: 3 },

        'test null object': { accessor: 'payload.customerId',
            expected: null,
            errorMsg: '[[sw-data-grid] Can not resolve accessor: payload.customerId]' },
        'test nested null object': {
            accessor: 'customer.type.name',
            expected: null,
            errorMsg: '[[sw-data-grid] Can not resolve accessor: customer.type.name]',
        },
    };

    // This test cases previously tested for console.warn calls. This was removed because vue compat emits too many warnings
    Object.entries(cases).forEach(([key, testCase]) => {
        it(`should render columns with ${key}`, async () => {
            const wrapper = await createWrapper();
            const grid = wrapper.vm;

            const data = {
                name: 'original',
                translated: {
                    name: 'translated',
                },
                manufacturer: new Entity('test', 'product_manufacturer', {
                    description: 'manufacturer-description',
                    name: 'manufacturer',
                    translated: { name: 'manufacturer-translated' },
                }),
                plainObject: {
                    name: 'object',
                },
                transactions: new EntityCollection('', 'order_transaction', {}, {}, [
                    { name: 'first' },
                    { name: 'second' },
                    { name: 'last' },
                ], 1, null),
                arrayField: [1, 2, 3],
                payload: null,
                customer: { type: null },
            };

            const entity = new Entity('123', 'test', data);

            const column = { property: testCase.accessor };
            const result = grid.renderColumn(entity, column);

            expect(result).toBe(testCase.expected);
        });

        it(`should render different columns dynamically with ${key}`, async () => {
            const wrapper = await createWrapper();
            const grid = wrapper.vm;

            const data = {
                name: 'original',
                translated: {
                    name: 'translated',
                },
                manufacturer: new Entity('test', 'product_manufacturer', {
                    description: 'manufacturer-description',
                    name: 'manufacturer',
                    translated: { name: 'manufacturer-translated' },
                }),
                plainObject: {
                    name: 'object',
                },
                transactions: new EntityCollection('', 'order_transaction', { }, { }, [
                    { name: 'first' },
                    { name: 'second' },
                    { name: 'last' },
                ], 1, null),
                arrayField: [1, 2, 3],
                payload: null,
                customer: { type: null },
            };

            const entity = new Entity('123', 'test', data);

            const column = { property: testCase.accessor };

            const result = grid.renderColumn(entity, column);

            expect(result).toBe(testCase.expected);
        });
    });

    it('should pre select grid using preSelection prop', async () => {
        const preSelection = {
            uuid1: { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
        };

        const wrapper = await createWrapper({
            identifier: 'sw-customer-list-identifier',
            preSelection,
        });

        expect(wrapper.vm.selection).toEqual(preSelection);

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        const checkbox = rows.at(0).find('.sw-field__checkbox input');

        expect(checkbox.element.checked).toBe(true);
    });

    it('should checked a item in grid if the grid state include that item', async () => {
        const wrapper = await createWrapper({
            identifier: 'sw-customer-list',
            preSelection: {
                uuid1: { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
            },
        });

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        const checkbox = rows.at(0).find('.sw-field__checkbox input');

        expect(checkbox.element.checked).toBe(true);
    });

    it('should add a selection to grid state when selected an item', async () => {
        const wrapper = await createWrapper();

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        const checkbox = rows.at(0).find('.sw-field__checkbox input');

        await checkbox.setChecked(true);
        await wrapper.vm.$nextTick();

        const firstRow = defaultProps.dataSource[0];

        expect(wrapper.vm.selection).toEqual({ [firstRow.id]: firstRow });
    });

    it('should remove a selection from selection when deselected an item', async () => {
        const wrapper = await createWrapper({
            identifier: 'sw-customer-list',
            preSelection: {
                uuid1: { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
            },
        });

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        const checkbox = rows.at(0).find('.sw-field__checkbox input');

        expect(checkbox.element.checked).toBe(true);

        await checkbox.setChecked(false);

        expect(wrapper.vm.selection).toEqual({});
    });

    it('should add all records to grid selection when clicking select all', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            identifier: 'sw-customer-list',
        });

        const header = wrapper.find('.sw-data-grid__header');
        const selectionAll = header.find('.sw-data-grid__header .sw-field--checkbox.sw-data-grid__select-all input');

        expect(selectionAll.element.checked).toBe(false);
        await selectionAll.setChecked(true);

        const expectedState = {};

        defaultProps.dataSource.forEach(item => {
            expectedState[item.id] = item;
        });

        expect(wrapper.vm.selection).toEqual(expectedState);
    });

    it('should remove all records to grid state when deselected all items', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            identifier: 'sw-customer-list',
        });

        const curentGridState = {};

        defaultProps.dataSource.forEach(item => {
            curentGridState[item.id] = item;
        });

        await wrapper.setData({
            selection: curentGridState,
        });

        const header = wrapper.find('.sw-data-grid__header');
        const selectionAll = header.find('.sw-data-grid__header .sw-field--checkbox.sw-data-grid__select-all input');

        await selectionAll.setChecked(false);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.selection).toEqual({});
    });

    it('should selectionCount equals to grid state count', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            identifier: 'sw-customer-list',
        });

        expect(wrapper.vm.selectionCount).toBe(0);

        const curentGridState = {};

        defaultProps.dataSource.forEach(item => {
            curentGridState[item.id] = item;
        });

        await wrapper.setData({
            selection: curentGridState,
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.selectionCount).toBe(5);
    });

    it('should persist selected items when dataSource change', async () => {
        const wrapper = await createWrapper();

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(rows).toHaveLength(5);

        const checkbox = rows.at(0).find('.sw-field__checkbox input');

        await checkbox.setChecked(true);

        await wrapper.setProps({
            dataSource: [
                { id: 'uuid6', company: 'Woops', name: 'Portia Jobson' },
                { id: 'uuid7', company: 'Laprta', name: 'Baxy Eardley' },
                { id: 'uuid8', company: 'Manen', name: 'Arturo Staker' },
                { id: 'uuid9', company: 'Ginpo', name: 'Dalston Top' },
            ],
        });

        await wrapper.vm.$nextTick();

        const newRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(newRows).toHaveLength(4);

        const newCheckbox = newRows.at(0).find('.sw-field__checkbox input');

        await newCheckbox.setChecked(true);

        await wrapper.vm.$nextTick();

        await wrapper.setProps({
            dataSource: [
                { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
            ],
        });

        const previousRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(previousRows).toHaveLength(1);

        const previousCheckbox = newRows.at(0).find('.sw-field__checkbox input');
        expect(previousCheckbox.element.checked).toBe(true);
    });

    it('should not show deselect all action', async () => {
        const wrapper = await createWrapper({
            identifier: 'sw-customer-list',
            preSelection: {
                uuid1: { id: 'uuid1', company: 'Quartz1', name: 'Tinto' },
            },
        });
        const bulkActions = wrapper.find('.sw-data-grid__bulk');
        const deselectAll = bulkActions.find('.bulk-deselect-all');

        expect(deselectAll.exists()).toBe(false);
    });

    it('should show deselect all action', async () => {
        const wrapper = await createWrapper({
            identifier: 'sw-customer-list',
            preSelection: {
                uuid10: { id: 'uuid10', company: 'Quartz', name: 'Tinto' },
            },
        });

        const bulkActions = wrapper.find('.sw-data-grid__bulk');
        const deselectAll = bulkActions.find('.bulk-deselect-all');

        expect(deselectAll.exists()).toBe(true);
    });

    it('should show maximum selection exceed', async () => {
        const wrapper = await createWrapper({
            maximumSelectItems: 3,
            identifier: 'sw-customer-list',
            preSelection: {
                uuid1: { id: 'uuid1', company: 'Quartz1', name: 'Tinto' },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.reachMaximumSelectionExceed).toBe(false);

        await wrapper.setData({
            selection: {
                uuid1: { id: 'uuid1', company: 'Quartz1', name: 'Tinto' },
                uuid2: { id: 'uuid2', company: 'Quartz2', name: 'Tinto' },
                uuid3: { id: 'uuid3', company: 'Quartz3', name: 'Tinto' },
            },
        });

        await wrapper.vm.$nextTick();

        const newBulkActions = wrapper.find('.sw-data-grid__bulk');
        const maximumHint = newBulkActions.find('.sw-data-grid__bulk-max-selection');

        expect(maximumHint.exists()).toBe(true);
    });

    it('should disable checkboxes when maximum selection exceed', async () => {
        const wrapper = await createWrapper({
            maximumSelectItems: 3,
            preSelection: {
                uuid1: { id: 'uuid1', company: 'Quartz1', name: 'Tinto' },
                uuid2: { id: 'uuid2', company: 'Quartz2', name: 'Tinto' },
                uuid3: { id: 'uuid3', company: 'Quartz3', name: 'Tinto' },
            },
        });

        await wrapper.vm.$nextTick();

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        // selected items are de-selectable
        const checkedBox = rows.at(0).find('.sw-field__checkbox input');
        expect(checkedBox.attributes().disabled).toBeUndefined();

        // unselected items are selectable
        const uncheckedBox = rows.at(4).find('.sw-field__checkbox input');

        expect(uncheckedBox.attributes().disabled).toBe('');

        // Change data source, select all checkbox and all items checkboxes will be disabled
        await wrapper.setProps({
            dataSource: [
                { id: 'uuid4', company: 'Quartz4', name: 'Tinto' },
                { id: 'uuid5', company: 'Quartz5', name: 'Tinto' },
                { id: 'uuid6', company: 'Quartz6', name: 'Tinto' },
            ],
        });

        await wrapper.vm.$nextTick();

        const newRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        newRows.forEach(row => {
            const checkbox = row.find('.sw-field__checkbox input');
            expect(checkbox.attributes().disabled).toBe('');
        });

        const header = wrapper.find('.sw-data-grid__header');
        const selectionAll = header.find('.sw-data-grid__header .sw-field--checkbox.sw-data-grid__select-all input');

        expect(selectionAll.attributes().disabled).toBe('');
    });

    it('should render icon column header', async () => {
        const wrapper = await createWrapper({
            columns: [
                { property: 'name', label: 'Name', iconLabel: 'regular-file-text' },
                { property: 'company', label: 'Company' },
            ],
            dataSource: [
                { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
            ],
        });
        expect(wrapper.find('.sw-data-grid__cell--icon-label').exists()).toBe(true);
        expect(wrapper.find('.sw-data-grid__cell--icon-label .sw-icon').classes()).toContain('icon--regular-file-text');
        expect(wrapper.find('.sw-data-grid__cell--icon-label .sw-icon').attributes()).not.toContain('data-tooltip-message');
    });

    it('should render icon column header with tooltip', async () => {
        const wrapper = await createWrapper({
            columns: [
                { property: 'name', label: 'Name', iconLabel: 'regular-file-text', iconTooltip: 'tooltip message' },
                { property: 'company', label: 'Company' },
            ],
            dataSource: [
                { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
            ],
        });

        expect(wrapper.find('.sw-data-grid__cell--icon-label').exists()).toBe(true);
        expect(wrapper.find('.sw-data-grid__cell--icon-label .sw-icon').classes()).toContain('icon--regular-file-text');
        expect(wrapper.find('.sw-data-grid__cell--icon-label .sw-icon').attributes('data-tooltip-message')).toBe('tooltip message');
    });
});
