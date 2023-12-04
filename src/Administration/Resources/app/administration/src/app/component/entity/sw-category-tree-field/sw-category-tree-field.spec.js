/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

const categoryData = [{
    id: 'categoryId-0',
    attributes: {
        id: 'categoryId-0',
    },
    translated: {
        name: 'categoryName-0',
    },
    relationships: {},
}, {
    id: 'categoryId-1',
    attributes: {
        id: 'categoryId-1',
    },
    translated: {
        name: 'categoryName-1',
    },
    relationships: {},
}, {
    id: 'categoryId-2',
    attributes: {
        id: 'categoryId-2',
    },
    translated: {
        name: 'categoryName-2',
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
        null,
    );
}

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/category',
    status: 200,
    response: { data: categoryData },
});

async function createWrapper() {
    return mount(await wrapTestComponent('sw-category-tree-field', { sync: true }), {
        attachTo: document.body,
        props: {
            placeholder: 'some-placeholder',
            categoriesCollection: createCategoryCollection(),
        },
        global: {
            stubs: {
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': true,
                'sw-label': true,
                'sw-icon': true,
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-tree': await wrapTestComponent('sw-tree'),
            },
        },
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
            singleSelect: true,
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
