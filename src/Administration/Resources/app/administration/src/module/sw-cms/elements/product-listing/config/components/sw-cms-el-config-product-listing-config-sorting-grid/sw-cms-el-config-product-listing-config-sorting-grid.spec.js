/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

const customFields = [
    {
        name: 'custom_field_0',
        config: {
            label: 'custom field 0',
        },
    },
    {
        name: 'custom_field_1',
        config: {
            label: 'custom field 1',
        },
    },
    {
        name: 'custom_field_2',
        config: {
            label: 'custom field 2',
        },
    },
    {
        name: 'custom_field_3',
        config: {
            label: 'custom field 3',
        },
    },
];

const snippets = {
    'sw-settings-listing.general.productSortingCriteriaGrid.options.label.product.name': 'Product name',
    'sw-settings-listing.general.productSortingCriteriaGrid.options.label.product.price': 'Product price',
};

async function createWrapper(productSortings = [], defaultSorting = {}) {
    return mount(await wrapTestComponent('sw-cms-el-config-product-listing-config-sorting-grid', { sync: true }), {
        global: {
            provide: {
                validationService: {},
                repositoryFactory: {
                    create: (name) => {
                        if (name === 'custom_field') {
                            return {
                                search: () => Promise.resolve(customFields),
                                saveAll: () => Promise.resolve(),
                                delete: () => Promise.resolve(),
                            };
                        }
                        return {};
                    },
                },
            },
            stubs: {
                'sw-data-grid': {
                    props: ['dataSource'],
                    template: `
                        <div>
                          <template v-for="item in dataSource">
                              <slot name="actions" v-bind="{ item: item }"></slot>
                              <slot name="column-fields" v-bind="{ item: item }"></slot>
                              <slot name="column-priority" v-bind="{ item: item }">
                                  <div :class="'column-priority_' + item.id">
                                      <sw-number-field v-model:value="item.priority" class="sw-grid-priority"></sw-number-field>
                                  </div>
                              </slot>
                          </template>
                        </div>
                    `,
                },
                'sw-context-menu-item': {
                    template: '<div @click="$emit(\'click\')"></div>',
                },
                'sw-pagination': true,
                'sw-number-field': {
                    template: `
                    <input type="number" :value="value" @input="$emit('update:value', Number($event.target.value))" />
                `,
                    props: {
                        value: 0,
                    },
                },
            },
            mocks: {
                $tc: (param) => {
                    if (snippets[param]) {
                        return snippets[param];
                    }
                    return param;
                },
            },
            mixins: [
                Shopware.Mixin.getByName('sw-inline-snippet'),
            ],
        },
        props: {
            productSortings,
            defaultSorting,
        },
    });
}

// eslint-disable-next-line max-len
describe('src/module/sw-cms/elements/product-listing/config/components/sw-cms-el-config-product-listing-config-sorting-grid', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should remove entry from product sortings on delete', async () => {
        const productSortings = new EntityCollection('', '', {}, {}, [
            { id: '1a2b3c', locked: false },
            { id: 'foo', locked: false },
            { id: '7a8b9c', locked: false },
        ]);

        const wrapper = await createWrapper(productSortings);

        const itemFoo = wrapper.find('.sw-cms-el-config-product-listing-config-sorting-grid__grid_item_foo');

        expect(wrapper.vm.productSortings.has('foo')).toBeTruthy();

        await itemFoo.trigger('click');

        expect(wrapper.vm.productSortings.has('foo')).toBeFalsy();
    });

    it('should not show context menu when item is locked', async () => {
        const productSortings = new EntityCollection('', '', {}, {}, [
            { id: '1a2b3c', locked: false },
            { id: 'foo', locked: false },
            { id: 'bar', locked: false },
        ]);

        const wrapper = await createWrapper(productSortings);

        let itemBar = wrapper.find('.sw-cms-el-config-product-listing-config-sorting-grid__grid_item_bar');

        expect(itemBar.exists()).toBeTruthy();

        await wrapper.setProps({
            productSortings: new EntityCollection('', '', {}, {}, [
                { id: '1a2b3c', locked: false },
                { id: 'foo', locked: false },
                { id: 'bar', locked: true },
            ]),
        });

        await wrapper.vm.$nextTick();
        await wrapper.vm.$forceUpdate();
        await flushPromises();

        itemBar = wrapper.find('.sw-cms-el-config-product-listing-config-sorting-grid__grid_item_bar');

        expect(itemBar.exists()).toBeFalsy();
    });

    it('should update the priority', async () => {
        const productSortings = new EntityCollection('', '', {}, {}, [
            { id: '1a2b3c', locked: false, priority: 1 },
            { id: 'foo', locked: false, priority: 5 },
            { id: 'bar', locked: false, priority: 3 },
        ]);

        const wrapper = await createWrapper(productSortings);

        expect(wrapper.vm.productSortings.get('bar').priority).toBe(3);

        const itemBar = wrapper.find('.column-priority_bar');
        await itemBar.find('input').setValue(7);

        await wrapper.vm.$nextTick();
        await wrapper.vm.$forceUpdate();

        expect(wrapper.vm.productSortings.get('bar').priority).toBe(7);
    });

    it('should display criteria properly', async () => {
        const productSortings = new EntityCollection('', '', {}, {}, [
            { id: '1a2b3c', fields: [{ field: 'product.name' }], locked: false, priority: 1 },
            { id: 'foo', fields: [{ field: 'custom_field_2' }], locked: false, priority: 5 },
            { id: 'bar', fields: [{ field: 'product.price' }, { field: 'custom_field_0' }], locked: false, priority: 3 },
        ]);

        const wrapper = await createWrapper(productSortings);
        await flushPromises();

        expect(wrapper.findAll('.sw-cms-el-config-product-listing-config-sorting-grid__criteria').at(0).text()).toBe('Product name');
        expect(wrapper.findAll('.sw-cms-el-config-product-listing-config-sorting-grid__criteria').at(1).text()).toBe('custom field 2');
        expect(wrapper.findAll('.sw-cms-el-config-product-listing-config-sorting-grid__criteria').at(2).text()).toBe('Product price, custom field 0');
    });

    it('should disable delete button of default sorting', async () => {
        const productSortings = new EntityCollection('', '', {}, {}, [
            { id: '1a2b3c', fields: [{ field: 'product.name' }], locked: false, priority: 1 },
            { id: 'foo', fields: [{ field: 'custom_field_2' }], locked: false, priority: 5 },
            { id: 'bar', fields: [{ field: 'product.price' }, { field: 'custom_field_0' }], locked: false, priority: 3 },
        ]);

        const defaultSorting = {
            id: 'foo',
        };

        const wrapper = await createWrapper(productSortings, defaultSorting);
        await flushPromises();

        expect(wrapper.find('.sw-cms-el-config-product-listing-config-sorting-grid__grid_item_foo').attributes('disabled')).toBe('true');
    });
});
