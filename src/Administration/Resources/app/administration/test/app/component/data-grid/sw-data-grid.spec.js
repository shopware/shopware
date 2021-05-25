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

const stubs = {
    'sw-switch-field': Shopware.Component.build('sw-switch-field'),
    'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
    'sw-data-grid-settings': Shopware.Component.build('sw-data-grid-settings'),
    'sw-icon': true,
    'sw-context-button': Shopware.Component.build('sw-context-button'),
    'sw-context-menu': Shopware.Component.build('sw-context-menu'),
    'sw-context-menu-item': Shopware.Component.build('sw-context-menu-item'),
    'sw-button': Shopware.Component.build('sw-button'),
    'sw-popover': Shopware.Component.build('sw-popover'),
    'sw-base-field': Shopware.Component.build('sw-base-field'),
    'sw-field-error': true,
    'sw-context-menu-divider': true,
    'sw-button-group': true
};

const userConfig = {
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
                visible: false
            },
            {
                dataIndex: 'company',
                label: 'Company',
                property: 'company',
                visible: false
            }
        ],
        compact: true,
        previews: false
    }
};

describe('components/data-grid/sw-data-grid', () => {
    let wrapper;
    const localVue = createLocalVue();
    localVue.directive('popover', {});
    localVue.directive('tooltip', {});

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-data-grid'), {
            localVue,
            stubs,
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => {
                            return Promise.resolve([userConfig]);
                        },
                        save: () => {
                            return Promise.resolve();
                        }
                    })
                }
            },
            propsData: {
                identifier: 'sw-customer-list',
                columns: [
                    { property: 'name', label: 'Name' },
                    { property: 'company', label: 'Company' }
                ],
                dataSource: [
                    { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
                    { id: 'uuid2', company: 'Twitternation', name: 'Baxy Eardley' },
                    { id: 'uuid3', company: 'Skidoo', name: 'Arturo Staker' },
                    { id: 'uuid4', company: 'Meetz', name: 'Dalston Top' },
                    { id: 'uuid5', company: 'Photojam', name: 'Neddy Jensen' }
                ]
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be in compact mode by default', async () => {
        expect(wrapper.classes()).toContain('is--compact');
    });

    it('should render grid header with correct columns', async () => {
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
        await wrapper.setProps({
            showSelection: false,
            showActions: false,
            showHeader: false
        });

        const header = wrapper.find('.sw-data-grid__header');
        const selectionColumn = wrapper.find('.sw-data-grid__header .sw-data-grid__cell--selection');
        const actionColumn = wrapper.find('.sw-data-grid__header .sw-data-grid__cell--actions');

        expect(header.exists()).toBeFalsy();
        expect(selectionColumn.exists()).toBeFalsy();
        expect(actionColumn.exists()).toBeFalsy();
    });

    it('should render a row for each item in dataSource prop', async () => {
        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        expect(rows.length).toBe(5);
    });

    it('should change appearance class based on prop', async () => {
        await wrapper.setProps({
            plainAppearance: true
        });

        expect(wrapper.classes()).toContain('sw-data-grid--plain-appearance');
    });

    it('should load and apply user configuration', async () => {
        expect(wrapper.vm.showSettings).toBe(false);

        await wrapper.setProps({
            showSettings: true
        });

        expect(wrapper.vm.showSettings).toBe(true);
        expect(wrapper.findComponent(stubs['sw-context-menu']).exists()).toBe(false);

        await wrapper.vm.$nextTick();

        // find and click button setting
        const contextButtonSetting = wrapper.find('.sw-data-grid-settings__trigger');
        await contextButtonSetting.trigger('click');

        await wrapper.vm.$nextTick();

        // show popover
        expect(wrapper.findComponent(stubs['sw-context-menu']).exists()).toBe(true);

        // check default columns
        expect(wrapper.vm.currentColumns[0].visible).toBe(userConfig.value.columns[0].visible);
        expect(wrapper.vm.currentColumns[1].visible).toBe(userConfig.value.columns[1].visible);

        expect(wrapper.vm.compact).toBe(userConfig.value.compact);
        expect(wrapper.vm.previews).toBe(userConfig.value.previews);

        const valueChecked = !userConfig.value.columns[0].visible;

        const name = wrapper.find('.sw-data-grid__settings-item--0 input');
        await name.setChecked(valueChecked);

        expect(wrapper.vm.currentColumns[0].visible).toBe(valueChecked);
    });

    it('should render different columns dynamically', async () => {
        const grid = wrapper.vm;

        const data = {
            name: 'original',
            translated: {
                name: 'translated'
            },
            manufacturer: new Entity('test', 'product_manufacturer', {
                description: 'manufacturer-description',
                name: 'manufacturer',
                translated: { name: 'manufacturer-translated' }
            }),
            plainObject: {
                name: 'object'
            },
            transactions: new EntityCollection('', 'order_transaction', { }, { }, [
                { name: 'first' },
                { name: 'second' },
                { name: 'last' }
            ], 1, null),
            arrayField: [1, 2, 3],
            payload: null,
            customer: { type: null }
        };

        const entity = new Entity('123', 'test', data);

        const cases = {
            'simple field': { accessor: 'id', expected: '123' },
            'translated field': { accessor: 'name', expected: 'translated' },
            'translated field with accessor': { accessor: 'translated.name', expected: 'translated' },
            'nested object with simple field': {
                accessor: 'manufacturer.description',
                expected: 'manufacturer-description'
            },
            'nested object with translated field': {
                accessor: 'manufacturer.name',
                expected: 'manufacturer-translated'
            },
            'nested object with translated field with accessor': {
                accessor: 'manufacturer.translated.name',
                expected: 'manufacturer-translated'
            },
            'unknown field': { accessor: 'unknown', expected: undefined },
            'nested unknown field': { accessor: 'manufacturer.unknown', expected: undefined },
            'unknown nested object': { accessor: 'unknown.unknown', expected: undefined },

            'test last function': { accessor: 'transactions.last().name', expected: 'last' },
            'test first function': { accessor: 'transactions.first.name', expected: 'first' },
            'test array access on collection': { accessor: 'transactions[1].name', expected: 'second' },

            'test array element 1': { accessor: 'arrayField[0]', expected: 1 },
            'test array element 2': { accessor: 'arrayField[1]', expected: 2 },
            'test array element 3': { accessor: 'arrayField[2]', expected: 3 },

            'test null object': { accessor: 'payload.customerId', expected: null },
            'test nested null object': { accessor: 'customer.type.name', expected: null }
        };

        Object.values(cases).forEach((testCase) => {
            const column = { property: testCase.accessor };

            const result = grid.renderColumn(entity, column);

            expect(result).toBe(testCase.expected);
        });
    });
});
