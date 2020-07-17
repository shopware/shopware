import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/data-grid/sw-data-grid-settings';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/entity/sw-entity-listing';

function createWrapper() {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    // mock entity functions
    const items = [
        { name: 'Apple' },
        { name: 'Shopware' },
        { name: 'Google' },
        { name: 'Microsoft' }
    ];
    items.total = 4;
    items.criteria = {
        page: 1,
        limit: 25
    };

    return shallowMount(Shopware.Component.build('sw-entity-listing'), {
        localVue,
        stubs: {
            'sw-data-grid-settings': Shopware.Component.build('sw-data-grid-settings'),
            'sw-button': true,
            'sw-context-button': true,
            'sw-icon': true,
            'sw-field': true,
            'sw-context-menu-divider': true,
            'sw-pagination': true,
            'sw-checkbox-field': true,
            'sw-context-menu-item': true
        },
        provide: {},
        mocks: {
            $tc: t => t,
            $te: () => true,
            $device: {
                onResize: () => {}
            }
        },
        propsData: {
            repository: {},
            columns: [{
                property: 'name'
            }],
            detailRoute: 'sw.manufacturer.detail',
            items: items
        }
    });
}

describe('src/app/component/entity/sw-entity-listing', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should enable the context menu edit item', () => {
        const wrapper = createWrapper();

        const firstRow = wrapper.find('.sw-data-grid__row--1');
        const firstRowActions = firstRow.find('.sw-data-grid__cell--actions');
        const firstRowActionEdit = firstRowActions.find('.sw-entity-listing__context-menu-edit-action');

        expect(firstRowActionEdit.exists()).toBeTruthy();
        expect(firstRowActionEdit.attributes().disabled).toBeFalsy();
    });

    it('should disable the context menu edit item', () => {
        const wrapper = createWrapper();

        wrapper.setProps({
            allowEdit: false
        });

        const firstRow = wrapper.find('.sw-data-grid__row--1');
        const firstRowActions = firstRow.find('.sw-data-grid__cell--actions');
        const firstRowActionEdit = firstRowActions.find('.sw-entity-listing__context-menu-edit-action');

        expect(firstRowActionEdit.exists()).toBeTruthy();
        expect(firstRowActionEdit.attributes().disabled).toBeTruthy();
    });

    it('should enable the context menu delete item', () => {
        const wrapper = createWrapper();

        const firstRow = wrapper.find('.sw-data-grid__row--1');
        const firstRowActions = firstRow.find('.sw-data-grid__cell--actions');
        const firstRowActionDelete = firstRowActions.find('.sw-entity-listing__context-menu-edit-delete');

        expect(firstRowActionDelete.exists()).toBeTruthy();
        expect(firstRowActionDelete.attributes().disabled).toBeFalsy();
    });

    it('should disable the context menu delete item', () => {
        const wrapper = createWrapper();

        wrapper.setProps({
            allowDelete: false
        });

        const firstRow = wrapper.find('.sw-data-grid__row--1');
        const firstRowActions = firstRow.find('.sw-data-grid__cell--actions');
        const firstRowActionDelete = firstRowActions.find('.sw-entity-listing__context-menu-edit-delete');

        expect(firstRowActionDelete.exists()).toBeTruthy();
        expect(firstRowActionDelete.attributes().disabled).toBeTruthy();
    });
});
