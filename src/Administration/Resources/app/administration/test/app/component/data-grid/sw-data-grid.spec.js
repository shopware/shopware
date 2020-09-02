import { shallowMount } from '@vue/test-utils';
import 'src/app/component/data-grid/sw-data-grid';

describe('components/data-grid/sw-data-grid', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-data-grid'), {
            stubs: {
                'sw-checkbox-field': true,
                'sw-context-button': true
            },
            propsData: {
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
            },
            mocks: {
                $tc: key => key,
                $te: key => key,
                $device: { onResize: () => {} }
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should be in compact mode by default', () => {
        expect(wrapper.classes()).toContain('is--compact');
    });

    it('should render grid header with correct columns', () => {
        const nameColumn = wrapper.find('.sw-data-grid__header .sw-data-grid__cell--0 .sw-data-grid__cell-content');
        const companyColumn = wrapper.find('.sw-data-grid__header .sw-data-grid__cell--1 .sw-data-grid__cell-content');
        const selectionColumn = wrapper.find('.sw-data-grid__header .sw-data-grid__cell--selection');
        const actionColumn = wrapper.find('.sw-data-grid__header .sw-data-grid__cell--actions');

        expect(selectionColumn.exists()).toBeTruthy();
        expect(actionColumn.exists()).toBeTruthy();

        expect(nameColumn.text()).toBe('Name');
        expect(companyColumn.text()).toBe('Company');
    });

    it('should hide selection column, action column and header based on prop', () => {
        wrapper.setProps({
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

    it('should render a row for each item in dataSource prop', () => {
        const rows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        expect(rows.length).toBe(5);
    });

    it('should change appearance class based on prop', () => {
        wrapper.setProps({
            plainAppearance: true
        });

        expect(wrapper.classes()).toContain('sw-data-grid--plain-appearance');
    });
});
