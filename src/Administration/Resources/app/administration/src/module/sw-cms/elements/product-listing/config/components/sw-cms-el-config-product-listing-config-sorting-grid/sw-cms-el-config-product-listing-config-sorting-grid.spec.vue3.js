import { mount } from '@vue/test-utils_v3';
import swCmsElConfigProductListingConfigSortingGrid from 'src/module/sw-cms/elements/product-listing/config/components/sw-cms-el-config-product-listing-config-sorting-grid';
import EntityCollection from 'src/core/data/entity-collection.data';
import Vue from 'vue';

Shopware.Component.register('sw-cms-el-config-product-listing-config-sorting-grid', swCmsElConfigProductListingConfigSortingGrid);

async function createWrapper(productSortings = []) {
    return mount(await Shopware.Component.build('sw-cms-el-config-product-listing-config-sorting-grid'), {
        global: {
            provide: {
                validationService: {},
            },
            stubs: {
                'sw-data-grid': {
                    props: ['dataSource'],
                    template: `
<div>
  <template v-for="item in dataSource">
      <slot name="actions" v-bind="{ item: item }"></slot>
      <slot name="column-priority" v-bind="{ item: item }">
          <div :class="'column-priority_' + item.id">
              <sw-number-field v-model="item.priority" class="sw-grid-priority"></sw-number-field>
          </div>
      </slot>
  </template>
</div>`,
                },
                'sw-context-menu-item': {
                    template: '<div @click="$emit(\'click\')"></div>',
                },
                'sw-number-field': {
                    template: `
                    <input type="number" :value="value" @input="$emit('input', Number($event.target.value))" />
                `,
                    props: {
                        value: 0,
                    },
                },
            },
        },
        props: Vue.observable({
            productSortings: productSortings,
            defaultSorting: {},
        }),
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
});
