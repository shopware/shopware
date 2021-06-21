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

const defaultProps = {
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
};

function createWrapper(props, userConfig, overrideProps) {
    if (!overrideProps) {
        props = { ...defaultProps, ...props };
    }


    return shallowMount(Shopware.Component.build('sw-data-grid'), {
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
                    }
                })
            }
        },
        propsData: props ?? defaultProps
    });
}


describe('components/data-grid/sw-data-grid', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be in compact mode by default', async () => {
        const wrapper = createWrapper();

        expect(wrapper.classes()).toContain('is--compact');
    });

    it('should render grid header with correct columns', async () => {
        const wrapper = createWrapper();

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
        const wrapper = createWrapper({
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
        const wrapper = createWrapper();

        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        expect(rows.length).toBe(5);
    });

    it('should change appearance class based on prop', async () => {
        const wrapper = createWrapper({
            plainAppearance: true
        });


        expect(wrapper.classes()).toContain('sw-data-grid--plain-appearance');
    });

    it('should load and apply user configuration', async () => {
        const wrapper = createWrapper({
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
        const popover = wrapper.findComponent(stubs['sw-context-menu']);
        expect(popover.exists()).toBe(true);
        expect(popover.findAll('.sw-data-grid__settings-column-item').length).toBe(2);


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
        const wrapper = createWrapper({
            showSettings: true,
            identifier: 'sw-customer-list',
            columns: [
                { property: 'name', label: 'Name' }
            ],
            dataSource: [
                { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
                { id: 'uuid2', company: 'Twitternation', name: 'Baxy Eardley' }
            ]
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
                previews: true
            }
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
        expect(popover.findAll('.sw-data-grid__settings-column-item').length).toBe(1);


        // check default columns
        expect(wrapper.vm.currentColumns[0].visible).toBe(false);
        expect(wrapper.vm.currentColumns[1]).toBe(undefined);

        expect(wrapper.vm.compact).toBe(true);
        expect(wrapper.vm.previews).toBe(true);
    });

    it('add property in client', async () => {
        const wrapper = createWrapper({
            showSettings: true,
            identifier: 'sw-customer-list',
            columns: [
                { property: 'name', label: 'Name' },
                { property: 'company', label: 'Company' }
            ],
            dataSource: [
                { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
                { id: 'uuid2', company: 'Twitternation', name: 'Baxy Eardley' }
            ]
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
                        visible: false
                    }
                ],
                compact: true,
                previews: true
            }
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
        expect(popover.findAll('.sw-data-grid__settings-column-item').length).toBe(2);


        // check default columns
        expect(wrapper.vm.currentColumns[0].visible).toBe(false);
        expect(wrapper.vm.currentColumns[1].visible).toBe(true);

        expect(wrapper.vm.compact).toBe(true);
        expect(wrapper.vm.previews).toBe(true);
    });

    it('add property value in client', async () => {
        const wrapper = createWrapper({
            showSettings: true,
            identifier: 'sw-customer-list',
            columns: [
                { property: 'name', label: 'Name', mockProperty: true }
            ],
            dataSource: [
                { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' }
            ]
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
                previews: true
            }
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
        expect(popover.findAll('.sw-data-grid__settings-column-item').length).toBe(1);


        // check default columns
        expect(wrapper.vm.currentColumns[0].visible).toBe(false);
        expect(wrapper.vm.currentColumns[0].mockProperty).toBe(true);

        expect(wrapper.vm.compact).toBe(true);
        expect(wrapper.vm.previews).toBe(true);
    });

    it('remove property value in client', async () => {
        const wrapper = createWrapper({
            showSettings: true,
            identifier: 'sw-customer-list',
            columns: [
                { property: 'name', label: 'Name' }
            ],
            dataSource: [
                { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
                { id: 'uuid2', company: 'Twitternation', name: 'Baxy Eardley' }
            ]
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
                        mockProperty: true
                    }
                ],
                compact: true,
                previews: true
            }
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
        expect(popover.findAll('.sw-data-grid__settings-column-item').length).toBe(1);


        // check default columns
        expect(wrapper.vm.currentColumns[0].visible).toBe(false);
        expect(wrapper.vm.currentColumns[0].mockProperty).toBe(undefined);


        expect(wrapper.vm.compact).toBe(true);
        expect(wrapper.vm.previews).toBe(true);
    });

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
        'unknown nested object': {
            accessor: 'unknown.unknown',
            expected: undefined,
            errorMsg: '[[sw-data-grid] Can not resolve accessor: unknown.unknown]'
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
            errorMsg: '[[sw-data-grid] Can not resolve accessor: customer.type.name]'
        }
    };

    Object.entries(cases).forEach(([key, testCase]) => {
        it(`should render columns with ${key}`, async () => {
            const warningSpy = jest.spyOn(console, 'warn').mockImplementation();
            const grid = createWrapper().vm;

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
                transactions: new EntityCollection('', 'order_transaction', {}, {}, [
                    { name: 'first' },
                    { name: 'second' },
                    { name: 'last' }
                ], 1, null),
                arrayField: [1, 2, 3],
                payload: null,
                customer: { type: null }
            };

            const entity = new Entity('123', 'test', data);

            warningSpy.mockClear();

            const column = { property: testCase.accessor };
            const result = grid.renderColumn(entity, column);

            if (typeof testCase.errorMsg === 'string') {
                expect(warningSpy).toHaveBeenCalledWith(testCase.errorMsg);
            } else {
                expect(warningSpy).not.toHaveBeenCalled();
            }
            expect(result).toBe(testCase.expected);
        });

        it(`should render different columns dynamically with ${key}`, async () => {
            const wrapper = createWrapper();
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

            const column = { property: testCase.accessor };

            const result = grid.renderColumn(entity, column);

            expect(result).toBe(testCase.expected);
        });
    });
});

