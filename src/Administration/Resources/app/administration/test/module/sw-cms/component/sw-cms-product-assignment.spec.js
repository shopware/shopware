import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/component/sw-cms-product-assignment';
import 'src/app/component/entity/sw-many-to-many-assignment-card';

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

function createWrapper(customPropsData = {}) {
    const entityCollection = createEntityCollection();

    return shallowMount(Shopware.Component.build('sw-cms-product-assignment'), {
        stubs: {
            'sw-select-base': {
                template: '<div class="sw-select-base"></div>'
            },
            'sw-data-grid': {
                template: '<div><slot name="actions"></slot></div>'
            },
            'sw-context-menu-item': true
        },
        provide: {
            repositoryFactory: {}
        },
        propsData: {
            columns: [],
            entityCollection: entityCollection,
            localMode: true,
            ...customPropsData
        },
        mocks: {
            $tc: v => v
        }
    });
}

describe('module/sw-cms/component/sw-cms-product-assignment', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an enabled sw-select-base', () => {
        const wrapper = createWrapper();

        const selectBase = wrapper.find('.sw-select-base');

        expect(selectBase.attributes().disabled).not.toBeDefined();
    });

    it('should have an disabled sw-select-base', () => {
        const wrapper = createWrapper({ disabled: true });

        const selectBase = wrapper.find('.sw-select-base');

        expect(selectBase.attributes().disabled).toBeDefined();
    });

    it('should have an sw-data-grid', () => {
        const wrapper = createWrapper();

        const dataGrid = wrapper.find('.sw-cms-product-assignment__grid');

        expect(dataGrid.attributes().columns).toBeDefined();
    });

    it('should have an enabled context menu item', () => {
        const wrapper = createWrapper();

        const selectBase = wrapper.find('sw-context-menu-item-stub');

        expect(selectBase.attributes().disabled).not.toBeDefined();
    });

    it('should have an disabled context menu item', () => {
        const wrapper = createWrapper({ disabled: true });

        const selectBase = wrapper.find('sw-context-menu-item-stub');

        expect(selectBase.attributes().disabled).toBeDefined();
    });
});
