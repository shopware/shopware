import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/elements/product-listing/config/components/sw-cms-el-config-product-listing-config-sorting-grid';
import EntityCollection from 'src/core/data-new/entity-collection.data';
import Vue from 'vue';

function createWrapper(productSortings = []) {
    return shallowMount(Shopware.Component.build('sw-cms-el-config-product-listing-config-sorting-grid'), {
        sync: false,
        mocks: {
            $tc: v => v
        },
        provide: {
            validationService: {}
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
</div>`
            },
            'sw-context-menu-item': {
                template: '<div @click="$emit(\'click\')"></div>'
            },
            'sw-number-field': {
                template: `
                    <input type="number" :value="value" @input="$emit('input', Number($event.target.value))" />
                `,
                props: {
                    value: 0
                }
            }
        },
        propsData: Vue.observable({
            productSortings: productSortings,
            defaultSorting: {}
        })
    });
}

// eslint-disable-next-line max-len
describe('src/module/sw-cms/elements/product-listing/config/components/sw-cms-el-config-product-listing-config-sorting-grid', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should remove entry from product sortings on delete', async () => {
        const productSortings = new EntityCollection('', '', {}, {}, [
            { id: '1a2b3c', locked: false },
            { id: 'foo', locked: false },
            { id: '7a8b9c', locked: false }
        ]);

        const wrapper = createWrapper(productSortings);

        const itemFoo = wrapper.find('.sw-cms-el-config-product-listing-config-sorting-grid__grid_item_foo');

        expect(wrapper.vm.productSortings.has('foo')).toBeTruthy();

        await itemFoo.trigger('click');

        expect(wrapper.vm.productSortings.has('foo')).toBeFalsy();
    });

    it('should not show context menu when item is locked', async () => {
        const productSortings = new EntityCollection('', '', {}, {}, [
            { id: '1a2b3c', locked: false },
            { id: 'foo', locked: false },
            { id: 'bar', locked: false }
        ]);

        const wrapper = createWrapper(productSortings);

        const itemBar = wrapper.find('.sw-cms-el-config-product-listing-config-sorting-grid__grid_item_bar');

        expect(itemBar.exists()).toBeTruthy();

        wrapper.setProps({
            productSortings: new EntityCollection('', '', {}, {}, [
                { id: '1a2b3c', locked: false },
                { id: 'foo', locked: false },
                { id: 'bar', locked: true }
            ])
        });

        await wrapper.vm.$nextTick();
        await wrapper.vm.$forceUpdate();

        expect(itemBar.exists()).toBeFalsy();
    });

    it('should update the priority', async () => {
        const productSortings = new EntityCollection('', '', {}, {}, [
            { id: '1a2b3c', locked: false, priority: 1 },
            { id: 'foo', locked: false, priority: 5 },
            { id: 'bar', locked: false, priority: 3 }
        ]);

        const wrapper = createWrapper(productSortings);

        expect(wrapper.vm.productSortings.get('bar').priority).toBe(3);

        const itemBar = wrapper.find('.column-priority_bar');
        itemBar.find('input').setValue(7);

        await wrapper.vm.$nextTick();
        await wrapper.vm.$forceUpdate();

        expect(wrapper.vm.productSortings.get('bar').priority).toBe(7);
    });
});
