/**
 * @package admin
 */

import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/data-grid/sw-data-grid-settings';
import 'src/app/component/base/sw-button';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/context-menu/sw-context-menu';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/utils/sw-popover';
import Entity from 'src/core/data/entity.data';
import EntityCollection from 'src/core/data/entity-collection.data';

const localVue = createLocalVue();
localVue.directive('popover', {});
localVue.directive('tooltip', {});

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
            'sw-switch-field': await Shopware.Component.build('sw-switch-field'),
            'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
            'sw-data-grid-settings': await Shopware.Component.build('sw-data-grid-settings'),
            'sw-icon': true,
            'sw-context-button': await Shopware.Component.build('sw-context-button'),
            'sw-context-menu': await Shopware.Component.build('sw-context-menu'),
            'sw-context-menu-item': await Shopware.Component.build('sw-context-menu-item'),
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-popover': await Shopware.Component.build('sw-popover'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': true,
            'sw-context-menu-divider': true,
            'sw-button-group': true,
        };

        return shallowMount(await Shopware.Component.build('sw-data-grid'), {
            localVue,
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
            propsData: props ?? defaultProps,
        });
    }

    beforeAll(async () => {
        stubs = {
            'sw-switch-field': await Shopware.Component.build('sw-switch-field'),
            'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
            'sw-data-grid-settings': await Shopware.Component.build('sw-data-grid-settings'),
            'sw-icon': true,
            'sw-context-button': await Shopware.Component.build('sw-context-button'),
            'sw-context-menu': await Shopware.Component.build('sw-context-menu'),
            'sw-context-menu-item': await Shopware.Component.build('sw-context-menu-item'),
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-popover': await Shopware.Component.build('sw-popover'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
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

        await wrapper.vm.$nextTick();

        // find and click button setting
        const contextButtonSetting = wrapper.find('.sw-data-grid-settings__trigger');
        await contextButtonSetting.trigger('click');

        await wrapper.vm.$nextTick();

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

        await wrapper.vm.$nextTick();

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

        await wrapper.vm.$nextTick();

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

        await wrapper.vm.$nextTick();

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

        await wrapper.vm.$nextTick();

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

    Object.entries(cases).forEach(([key, testCase]) => {
        it(`should render columns with ${key}`, async () => {
            const warningSpy = jest.spyOn(console, 'warn').mockImplementation();
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

            warningSpy.mockClear();

            const column = { property: testCase.accessor };
            const result = grid.renderColumn(entity, column);

            if (typeof testCase.errorMsg === 'string') {
                // eslint-disable-next-line jest/no-conditional-expect
                expect(warningSpy).toHaveBeenCalledWith(testCase.errorMsg);
            } else {
                // eslint-disable-next-line jest/no-conditional-expect
                expect(warningSpy).not.toHaveBeenCalled();
            }
            expect(result).toBe(testCase.expected);
        });

        it(`should render different columns dynamically with ${key}`, async () => {
            const warningSpy = jest.spyOn(console, 'warn').mockImplementation();
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

            if (typeof testCase.errorMsg === 'string') {
                // eslint-disable-next-line jest/no-conditional-expect
                expect(warningSpy).toHaveBeenCalledWith(testCase.errorMsg);
            } else {
                // eslint-disable-next-line jest/no-conditional-expect
                expect(warningSpy).not.toHaveBeenCalled();
            }
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
        const deselectAll = bulkActions.findAll('.bulk-deselect-all');

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
        const deselectAll = bulkActions.findAll('.bulk-deselect-all');

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

        const bulkActions = wrapper.find('.sw-data-grid__bulk');
        let maximumHint = bulkActions.findAll('.sw-data-grid__bulk-max-selection');

        expect(maximumHint.exists()).toBe(false);

        await wrapper.setData({
            selection: {
                uuid1: { id: 'uuid1', company: 'Quartz1', name: 'Tinto' },
                uuid2: { id: 'uuid2', company: 'Quartz2', name: 'Tinto' },
                uuid3: { id: 'uuid3', company: 'Quartz3', name: 'Tinto' },
            },
        });

        await wrapper.vm.$nextTick();

        const newBulkActions = wrapper.find('.sw-data-grid__bulk');
        maximumHint = newBulkActions.findAll('.sw-data-grid__bulk-max-selection');

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

        expect(uncheckedBox.attributes().disabled).toBe('disabled');

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

        newRows.wrappers.forEach(row => {
            const checkbox = row.find('.sw-field__checkbox input');
            expect(checkbox.attributes().disabled).toBe('disabled');
        });

        const header = wrapper.find('.sw-data-grid__header');
        const selectionAll = header.find('.sw-data-grid__header .sw-field--checkbox.sw-data-grid__select-all input');

        expect(selectionAll.attributes().disabled).toBe('disabled');
    });
});
