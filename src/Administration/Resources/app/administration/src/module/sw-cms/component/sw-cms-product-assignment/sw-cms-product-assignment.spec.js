/**
 * @package content
 */
import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/component/sw-cms-product-assignment';
import 'src/app/component/entity/sw-many-to-many-assignment-card';

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

async function createWrapper(customPropsData = {}) {
    const entityCollection = createEntityCollection();

    return shallowMount(await Shopware.Component.build('sw-cms-product-assignment'), {
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
        }
    });
}

describe('module/sw-cms/component/sw-cms-product-assignment', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an enabled sw-select-base', async () => {
        const wrapper = await createWrapper();

        const selectBase = wrapper.find('.sw-select-base');

        expect(selectBase.attributes().disabled).not.toBeDefined();
    });

    it('should have an disabled sw-select-base', async () => {
        const wrapper = await createWrapper({ disabled: true });

        const selectBase = wrapper.find('.sw-select-base');

        expect(selectBase.attributes().disabled).toBeDefined();
    });

    it('should have an sw-data-grid', async () => {
        const wrapper = await createWrapper();

        const dataGrid = wrapper.find('.sw-cms-product-assignment__grid');

        expect(dataGrid.attributes().columns).toBeDefined();
    });

    it('should have an enabled context menu item', async () => {
        const wrapper = await createWrapper();

        const selectBase = wrapper.find('sw-context-menu-item-stub');

        expect(selectBase.attributes().disabled).not.toBeDefined();
    });

    it('should have an disabled context menu item', async () => {
        const wrapper = await createWrapper({ disabled: true });

        const selectBase = wrapper.find('sw-context-menu-item-stub');

        expect(selectBase.attributes().disabled).toBeDefined();
    });
});
