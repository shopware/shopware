import { createLocalVue, shallowMount } from '@vue/test-utils';
import EntityCollection from 'src/core/data-new/entity-collection.data';
import Criteria from 'src/core/data-new/criteria.data';
import 'src/app/component/entity/sw-entity-listing';
import 'src/app/component/data-grid/sw-data-grid';

function createWrapper(propsData = {}) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-entity-listing'), {
        localVue,
        stubs: {
            'sw-data-grid': Shopware.Component.build('sw-data-grid'),
            'sw-pagination': true,
            'sw-data-grid-settings': true,
            'sw-checkbox-field': true,
            'sw-context-button': '<div class="sw-context-button"><slot></slot></div>',
            'sw-context-menu-item': true
        },
        provide: {
        },
        mocks: {
            $tc: v => v,
            $te: () => true,
            $device: { onResize: () => {} }
        },
        propsData: {
            columns: [
                { property: 'name', label: 'Name' }
            ],
            items: new EntityCollection(null, null, null, new Criteria(), [
                { id: 'id1', name: 'item1' },
                { id: 'id2', name: 'item2' }
            ]),
            repository: {
                search: () => {}
            },
            detailRoute: 'detail.route',
            ...propsData
        }
    });
}

describe('src/app/component/entity/sw-entity-listing', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should have context menu with edit entry', () => {
        const wrapper = createWrapper({
            allowEdit: true,
            items: new EntityCollection(null, null, null, new Criteria(), [
                { id: 'id1', name: 'item1' },
                { id: 'id2', name: 'item2' },
                { id: 'id3', name: 'item3' }
            ])
        });

        const elements = wrapper.findAll('.sw-entity-listing__context-menu-edit-action');

        expect(elements.exists()).toBeTruthy();
        expect(elements.wrappers.length).toBe(3);
    });

    it('should have context menu with view entry', () => {
        const wrapper = createWrapper({
            allowEdit: false,
            allowView: true,
            items: new EntityCollection(null, null, null, new Criteria(), [
                { id: 'id1', name: 'item1' },
                { id: 'id2', name: 'item2' },
                { id: 'id3', name: 'item3' }
            ])
        });

        const elements = wrapper.findAll('.sw-entity-listing__context-menu-view-action');

        expect(elements.exists()).toBeTruthy();
        expect(elements.wrappers.length).toBe(3);
    });

    it('should have context menu with disabled edit entry', () => {
        const wrapper = createWrapper({
            allowEdit: false,
            allowView: false,
            items: new EntityCollection(null, null, null, new Criteria(), [
                { id: 'id1', name: 'item1' },
                { id: 'id2', name: 'item2' },
                { id: 'id3', name: 'item3' }
            ])
        });

        const elements = wrapper.findAll('.sw-entity-listing__context-menu-edit-action');

        expect(elements.exists()).toBeTruthy();
        expect(elements.wrappers.length).toBe(3);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBe('true'));
    });
});
