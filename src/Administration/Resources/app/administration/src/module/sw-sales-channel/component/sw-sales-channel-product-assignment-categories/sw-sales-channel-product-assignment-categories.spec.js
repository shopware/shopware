/**
 * @package buyers-experience
 */

import { mount } from '@vue/test-utils';

const categories = [
    {
        name: 'Home',
        translated: {
            name: 'Home',
        },
        id: '1',
        active: true,
        path: null,
        childCount: 1,
        breadcrumb: ['Home'],
    },
    {
        name: 'Kitchen',
        translated: {
            name: 'Kitchen',
        },
        id: '2',
        active: true,
        path: '|1|',
        parentId: '1',
        childCount: 0,
        breadcrumb: [
            'Home',
            'Kitchen',
        ],
    },
    {
        name: 'Fashion',
        translated: {
            name: 'Fashion',
        },
        id: '3',
        active: true,
        path: null,
        childCount: 2,
        breadcrumb: ['Fashion'],
    },
    {
        name: 'Skirt',
        translated: {
            name: 'Skirt',
        },
        id: '4',
        active: true,
        path: '|3|',
        parentId: '3',
        childCount: 0,
        breadcrumb: [
            'Fashion',
            'Skirt',
        ],
    },
    {
        name: 'Dress',
        translated: {
            name: 'Dress',
        },
        id: '5',
        active: true,
        path: '|3|',
        parentId: '3',
        childCount: 0,
        breadcrumb: [
            'Fashion',
            'Dress',
        ],
    },
];

const products = [
    {
        name: 'Product 1',
        id: '1111',
        categoryIds: ['1'],
    },
    {
        name: 'Product 2',
        id: '2222',
        categoryIds: [
            '3',
            '1',
        ],
    },
];

async function createWrapper() {
    return mount(await wrapTestComponent('sw-sales-channel-product-assignment-categories', { sync: true }), {
        global: {
            stubs: {
                'sw-card': {
                    template: '<div><slot></slot></div>',
                },
                'sw-card-section': {
                    template: '<div><slot></slot></div>',
                },
                'sw-container': true,
                'sw-alert': true,
                'sw-icon': true,
                'sw-tree': {
                    props: ['items'],
                    template: `
                    <div class="sw-tree">
                      <slot name="items" :treeItems="items" :checkItem="() => {}"></slot>
                    </div>
                `,
                },
                'sw-tree-item': {
                    props: [
                        'item',
                        'activeItemIds',
                        'activeParentIds',
                    ],
                    data() {
                        return {
                            checked: false,
                        };
                    },
                    template: `
                    <div class="sw-tree-item">
                      <input class="sw-tree-item__selection"
                             type="checkbox"
                             value="checked"
                             @change="toggleItemCheck($event, item)">
                      <slot name="content" v-bind="{ item }">
                          <span class="sw-tree-item__label">
                              {{ item.name }}
                          </span>
                      </slot>
                    </div>
                `,
                    methods: {
                        toggleItemCheck(event, item) {
                            this.checked = event;
                            this.item.checked = event;

                            this.$emit('check-item', item);
                        },
                    },
                },
                'sw-simple-search-field': {
                    model: {
                        prop: 'searchTerm',
                        event: 'search-term-change',
                    },
                    props: ['searchTerm'],
                    template: `
                    <div class="sw-simple-search-field">
                        <input type="text" :value="searchTerm" @input="onInput">
                    </div>`,
                    methods: {
                        onInput(e) {
                            this.$emit('search-term-change', e.target.value);
                        },
                    },
                },
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field', { sync: true }),
                'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field', {
                    sync: true,
                }),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field', { sync: true }),
                'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                'sw-field-error': true,
                'sw-highlight-text': true,
                'sw-empty-state': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
            provide: {
                repositoryFactory: {
                    create: (repositoryName) => {
                        if (repositoryName === 'category') {
                            return {
                                search: () => Promise.resolve(categories),
                            };
                        }

                        return {
                            search: () => Promise.resolve(products),
                        };
                    },
                },
                validationService: {},
            },
        },
        props: {
            salesChannel: {
                id: '1234',
                name: 'Storefront',
            },
            containerStyle: {},
        },
    });
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-product-assignment-categories', () => {
    it('should show category tree correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const tree = wrapper.find('.sw-tree');
        const treeItems = wrapper.findAll('.sw-tree-item');
        const searchList = wrapper.find('.sw-sales-channel-product-assignment-categories__search-results');

        expect(tree.exists()).toBeTruthy();
        expect(searchList.exists()).toBeFalsy();
        expect(treeItems).toHaveLength(5);
    });

    it('should show search list when user type in search field', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('.sw-simple-search-field input').setValue('Ho');
        await flushPromises();

        const tree = wrapper.find('.sw-tree');
        const searchList = wrapper.find('.sw-sales-channel-product-assignment-categories__search-results');
        const searchItems = wrapper.findAll('.sw-sales-channel-product-assignment-categories__search-result');

        expect(tree.exists()).toBeFalsy();
        expect(searchList.exists()).toBeTruthy();
        expect(searchItems).toHaveLength(5);
    });

    it('should emit selection-change when toggling tree item checkbox', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const treeItemSelections = wrapper.findAll('.sw-tree-item__selection');

        // Check 1st item
        await treeItemSelections.at(0).setChecked();
        await flushPromises();
        expect(Array.from(wrapper.vm.selectedCategoriesItemsIds)).toEqual([
            '1',
        ]);
        expect(wrapper.emitted('selection-change')[1]).toEqual([
            products,
            'categoryProducts',
        ]);

        // Check 3rd item
        await treeItemSelections.at(2).setChecked();
        expect(Array.from(wrapper.vm.selectedCategoriesItemsIds)).toEqual([
            '1',
            '3',
        ]);
        expect(wrapper.emitted('selection-change')[2]).toEqual([
            products,
            'categoryProducts',
        ]);
    });

    it('should emit selection-change when toggling search item checkbox', async () => {
        const wrapper = await createWrapper();

        const searchInput = wrapper.get('.sw-simple-search-field input');
        await searchInput.setValue('Ho');
        await flushPromises();

        const searchItemSelections = wrapper.findAll('.sw-sales-channel-product-assignment-categories__search-result');

        // Check 2nd item
        await searchItemSelections.at(1).trigger('click');
        expect(Array.from(wrapper.vm.selectedCategoriesItemsIds)).toEqual([
            '2',
        ]);
        expect(wrapper.emitted('selection-change')[1]).toEqual([
            products,
            'categoryProducts',
        ]);

        // Check 5th item
        await searchItemSelections.at(4).trigger('click');
        expect(Array.from(wrapper.vm.selectedCategoriesItemsIds)).toEqual([
            '2',
            '5',
        ]);
        expect(wrapper.emitted('selection-change')[2]).toEqual([
            products,
            'categoryProducts',
        ]);

        // Uncheck 2nd item
        await searchItemSelections.at(1).trigger('click');
        expect(Array.from(wrapper.vm.selectedCategoriesItemsIds)).toEqual([
            '5',
        ]);
        expect(wrapper.emitted('selection-change')[3]).toEqual([
            products,
            'categoryProducts',
        ]);
    });
});
