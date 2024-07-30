/**
 * @package admin
 * @group disabledCompat
 */

import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

const categoryData = [{
    id: 'categoryId-2',
    attributes: {
        id: 'categoryId-2',
    },
    translated: {
        name: 'categoryName-2',
    },
    relationships: {},
}, {
    id: 'categoryId-3',
    attributes: {
        id: 'categoryId3',
    },
    translated: {
        name: 'categoryName-3',
    },
    relationships: {},
}, {
    id: 'categoryId-4',
    attributes: {
        id: 'categoryId-4',
    },
    translated: {
        name: 'categoryName-4',
    },
    relationships: {},
}];

function createCategoryCollection(items = []) {
    return new EntityCollection(
        '/category',
        'category',
        null,
        { isShopwareContext: true },
        items,
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

responses.addResponse({
    method: 'Post',
    url: '/search-ids/category',
    status: 200,
    response: { data: ['categoryId-0', 'categoryId-1', 'categoryId-2', 'categoryId-3', 'categoryId-4'] },
});

async function createWrapper() {
    return mount(await wrapTestComponent('sw-category-tree-field', { sync: true }), {
        attachTo: document.body,
        props: {
            placeholder: 'some-placeholder',
            categoriesCollection: createCategoryCollection(),
            pageId: '123',
        },
        global: {
            stubs: {
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': true,
                'sw-label': await wrapTestComponent('sw-label', { sync: true }),
                'sw-icon': true,
                'sw-checkbox-field': true,
                'sw-highlight-text': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated'),
                'sw-tree': await wrapTestComponent('sw-tree'),
                'sw-tree-item': await wrapTestComponent('sw-tree-item'),
                'sw-loader': true,
                'sw-color-badge': true,
                'mt-floating-ui': true,
            },
            provide: {
                globalCategoryRepository: {
                    create: () => ({
                        searchIds: () => {
                            return Promise.resolve();
                        },
                        search: () => {
                            return Promise.resolve();
                        },
                    }),
                },
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
        await flushPromises();

        expect(wrapper.find('.sw-category-tree-field__results_base').exists()).toBe(true);

        wrapper.vm.onCheckItem({ id: 'categoryId-0', checked: true, data: { translated: { name: 'some-data' } } });
        await flushPromises();

        expect(wrapper.find('.sw-category-tree-field__results_base').exists()).toBe(false);
    });

    it('should remove the category item', async () => {
        const intitalCategories = [{
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
                id: 'categoryId1',
            },
            translated: {
                name: 'categoryName-1',
            },
            relationships: {},
        }];
        const wrapper = await createWrapper();

        await wrapper.setProps({
            categoriesCollection: createCategoryCollection(intitalCategories),
        });
        await flushPromises();

        expect(wrapper.vm.categoriesCollection).toHaveLength(2);

        wrapper.vm.removeItem(intitalCategories[0]);

        expect(wrapper.vm.categoriesCollection).toHaveLength(1);
    });

    it('should display more the category items', async () => {
        const intitalCategories = [{
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
                id: 'categoryId1',
            },
            translated: {
                name: 'categoryName-1',
            },
            relationships: {},
        }];
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            singleSelect: true,
            categoriesCollection: createCategoryCollection(intitalCategories),
        });
        await wrapper.setData({
            selectedCategoriesTotal: 5,
        });
        await flushPromises();

        await wrapper.find('.sw-category-tree-field__label-more').trigger('click');
        await wrapper.vm.$nextTick();
        await flushPromises();

        wrapper.vm.$emit('load-more-categories');
        expect(wrapper.emitted('load-more-categories')).toBeTruthy();
    });
});
