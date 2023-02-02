/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import 'src/app/component/entity/sw-category-tree-field';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/tree/sw-tree';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';

const categoryData = [{
    id: 'categoryId-0',
    attributes: {
        id: 'categoryId-0',
    },
    translated: {
        name: 'categoryName-0'
    },
    relationships: {},
}, {
    id: 'categoryId-1',
    attributes: {
        id: 'categoryId-1',
    },
    translated: {
        name: 'categoryName-1'
    },
    relationships: {},
}, {
    id: 'categoryId-2',
    attributes: {
        id: 'categoryId-2',
    },
    translated: {
        name: 'categoryName-2'
    },
    relationships: {},
}];

function createCategoryCollection() {
    return new EntityCollection(
        '/category',
        'category',
        null,
        { isShopwareContext: true },
        [],
        2,
        null
    );
}

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/category',
    status: 200,
    response: { data: categoryData }
});

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-category-tree-field'), {
        stubs: {
            'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': true,
            'sw-label': true,
            'sw-icon': true,
            'sw-popover': await Shopware.Component.build('sw-popover'),
            'sw-tree': await Shopware.Component.build('sw-tree')
        },
        propsData: {
            placeholder: 'some-placeholder',
            categoriesCollection: createCategoryCollection()
        },
        attachTo: document.body,
    });
}


describe('src/app/component/entity/sw-category-tree-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should close the dropdown when selecting in the single select mode', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            singleSelect: true
        });

        expect(wrapper.find('.sw-category-tree-field__results_base').exists()).toBe(false);

        wrapper.vm.term = 'some-search-term';
        await wrapper.find('.sw-category-tree__input-field').trigger('focus');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-category-tree-field__results_base').exists()).toBe(true);

        wrapper.vm.onCheckItem({ id: 'categoryId-0', checked: true, data: { translated: { name: 'some-data' } } });
        await flushPromises();

        expect(wrapper.find('.sw-category-tree-field__results_base').exists()).toBe(false);
    });
});
