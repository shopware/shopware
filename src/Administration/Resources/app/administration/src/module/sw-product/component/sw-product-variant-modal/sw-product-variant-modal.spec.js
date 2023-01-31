/*
 * @package inventory
 */

import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-product/component/sw-product-variant-modal';

function getOptions() {
    return [
        {
            name: 'b',
            translated: {
                name: 'b'
            },
            group: {
                translated: {
                    name: 'color'
                }
            },
            position: 1,
            id: 'option_b'
        },
        {
            name: 'c',
            translated: {
                name: 'c'
            },
            group: {
                translated: {
                    name: 'size'
                }
            },
            position: 5,
            id: 'option_c'
        },
        {
            name: 'a',
            translated: {
                name: 'a'
            },
            group: {
                translated: {
                    name: 'material'
                }
            },
            position: 1,
            id: 'option_a'
        }
    ];
}

function getGroups() {
    return [
        {
            id: 'group1',
            name: 'color',
            displayType: 'text',
            sortingType: 'alphanumeric',
            options: [{
                name: 'b',
                translated: {
                    name: 'b'
                },
                position: 1,
                id: 'option_b'
            },
            {
                name: 'b1',
                translated: {
                    name: 'b1'
                },
                position: 2,
                id: 'option_b1'
            }],
        },
        {
            id: 'group2',
            name: 'size',
            displayType: 'text',
            sortingType: 'alphanumeric',
            options: [{
                name: 'c',
                translated: {
                    name: 'c'
                },
                position: 5,
                id: 'option_c'
            },
            {
                name: 'c1',
                translated: {
                    name: 'c1'
                },
                position: 1,
                id: 'option_c1'
            }],
        },
        {
            id: 'group3',
            name: 'material',
            displayType: 'text',
            sortingType: 'alphanumeric',
            options: [{
                name: 'a',
                translated: {
                    name: 'a'
                },
                position: 1,
                id: 'option_a'
            }],
        }
    ];
}

function getVariants(returnCurrency = true) {
    return {
        price: !returnCurrency ? null : [
            {
                currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                net: 24,
                gross: 24,
                linked: true,
                listPrice: null,
                extensions: []
            }
        ],
        childCount: 2,
        name: 'random product',
        translated: {
            name: 'random product'
        },
        id: '72bfaf5d90214ce592715a9649d8760a',
        options: getOptions()
    };
}

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-product-variant-modal'), {
        propsData: {
            productEntity: {
                price: [
                    {
                        currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                        net: 12,
                        gross: 12,
                        linked: true,
                        listPrice: null,
                        extensions: []
                    }
                ],
                productNumber: 'SW10000',
                childCount: 2,
                name: 'random product',
                translated: {
                    name: 'name'
                },
                id: '72bfaf5d90214ce592715a9649d8760a',
                configuratorSettings: [
                    {
                        productId: '72bfaf5d90214ce592715a9649d8760a',
                        id: '1',
                        option: {
                            groupId: 'group1',
                            name: 'b',
                            id: 'option_b'
                        },
                    },
                    {
                        productId: '72bfaf5d90214ce592715a9649d8760a',
                        id: '2',
                        option: {
                            groupId: 'group2',
                            name: 'a',
                            id: 'option_a'
                        },
                    },
                    {
                        productId: '72bfaf5d90214ce592715a9649d8760a',
                        id: '3',
                        option: {
                            groupId: 'group3',
                            name: 'c',
                            id: 'option_c'
                        },
                    }
                ]
            },
        },
        provide: {
            repositoryFactory: {
                create: (entity) => {
                    return {
                        get: () => Promise.resolve(),
                        search: () => {
                            if (entity === 'product') {
                                return Promise.resolve(getVariants());
                            }

                            if (entity === 'property_group') {
                                return Promise.resolve(getGroups());
                            }

                            return Promise.resolve([]);
                        }
                    };
                }
            },
            acl: {
                can: () => true
            },
            feature: {
                isActive: () => true
            }
        },
        stubs: {
            'sw-modal': {
                template: `
                    <div class="sw-modal">
                      <slot name="modal-header"></slot>
                      <slot></slot>
                      <slot name="modal-footer"></slot>
                    </div>
                `
            },
            'sw-label': true,
            'sw-simple-search-field': true,
            'sw-empty-state': true,
            'sw-button': {
                template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>'
            },
            'sw-container': {
                template: '<div><slot></slot></div>'
            },
            'sw-context-menu': {
                template: '<div class="sw-context-menu"><slot></slot></div>'
            },
            'sw-tree': {
                props: ['items'],
                template: `
                    <div class="sw-tree">
                      <slot name="items" :treeItems="items" :checkItem="() => {}"></slot>
                    </div>
                `
            },
            'sw-tree-item': {
                props: ['item', 'activeItemIds', 'activeParentIds'],
                data() {
                    return {
                        checked: false
                    };
                },
                template: `
                    <div class="sw-tree-item">
                      <input class="sw-tree-item__selection"
                             type="checkbox"
                             :value="checked"
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
                    }
                }
            },
            'sw-icon': true,
            'sw-data-grid': {
                template: `
                    <div class="sw-data-grid">
                        <slot name="bulk"></slot>
                        <slot name="bulk-modals"></slot>
                    </div>
                `
            },
            'sw-bulk-edit-modal': true,
        }
    });
}


describe('module/sw-product/component/sw-product-variant-modal', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should sort options by their position', async () => {
        const sortedOptions = wrapper.vm.sortOptions(getOptions());

        expect(sortedOptions).toEqual([
            { name: 'a', translated: { name: 'a' }, group: { translated: { name: 'material' } }, id: 'option_a', position: 1 },
            { name: 'b', translated: { name: 'b' }, group: { translated: { name: 'color' } }, id: 'option_b', position: 1 },
            { name: 'c', translated: { name: 'c' }, group: { translated: { name: 'size' } }, id: 'option_c', position: 5 }
        ]);
    });

    it('should build variants options', async () => {
        const builtVariantOptions = wrapper.vm.buildVariantOptions(getVariants());

        expect(builtVariantOptions).toBe('(material: a, color: b, size: c)');
    });

    it('should variant name', async () => {
        const builtVariantName = wrapper.vm.buildVariantName(getVariants());

        expect(builtVariantName).toBe('random product (material: a, color: b, size: c)');
    });

    it('should omit the parenthesis', async () => {
        const builtVariantOptions = wrapper.vm.buildVariantOptions(getVariants(), ', ', true);

        expect(builtVariantOptions).toBe('material: a, color: b, size: c');
    });

    it('should use a custom separator', async () => {
        const builtVariantOptions = wrapper.vm.buildVariantOptions(getVariants(), ' - ');

        expect(builtVariantOptions).toBe('(material: a - color: b - size: c)');
    });

    it('should omit the group name', async () => {
        const builtVariantOptions = wrapper.vm.buildVariantOptions(getVariants(), ', ', false, true);

        expect(builtVariantOptions).toBe('(a, b, c)');
    });

    it('should get variant price of variant', async () => {
        const variantPriceObject = wrapper.vm.getVariantPrice(getVariants());
        const netPrice = variantPriceObject.net;
        const grossPrice = variantPriceObject.gross;

        expect(netPrice).toBe(24);
        expect(grossPrice).toBe(24);
    });

    it('should get variant price of parent product', async () => {
        const variantPriceObject = wrapper.vm.getVariantPrice(getVariants(false));
        const netPrice = variantPriceObject.net;
        const grossPrice = variantPriceObject.gross;

        expect(netPrice).toBe(12);
        expect(grossPrice).toBe(12);
    });

    it('should return the correct permissions tooltip', async () => {
        const tooltipObject = wrapper.vm.getNoPermissionsTooltip('product.editor');

        expect(tooltipObject).toEqual({
            showDelay: 300,
            message: 'sw-privileges.tooltip.warning',
            appearance: 'dark',
            showOnDisabledElements: true,
            disabled: true
        });
    });

    it('should get list groups of product', async () => {
        await flushPromises();

        const filterContextMenu = wrapper.find('.sw-product-variant-modal__filter-context-menu');

        expect(filterContextMenu.attributes().style).toEqual('display: none;');

        await wrapper.find('.sw-product-variant-modal__button-filter').trigger('click');

        expect(filterContextMenu.attributes().style).toBeFalsy();
        expect(wrapper.findAll('.sw-tree-item').length).toEqual(6);
    });

    it('should able to select filter option', async () => {
        await flushPromises();
        await wrapper.find('.sw-product-variant-modal__button-filter').trigger('click');

        const treeItemSelects = wrapper.findAll('.sw-tree-item');
        await treeItemSelects.at(3).find('input').setChecked();
        await treeItemSelects.at(3).trigger('change');

        expect(wrapper.vm.includeOptions).toEqual([{
            id: 'option_b',
            groupId: 'group1'
        }]);
    });

    it('should able to reset filter option', async () => {
        await flushPromises();
        await wrapper.find('.sw-product-variant-modal__button-filter').trigger('click');

        const treeItemSelects = wrapper.findAll('.sw-tree-item');
        await treeItemSelects.at(4).find('input').setChecked();
        await treeItemSelects.at(4).trigger('change');

        expect(wrapper.vm.includeOptions).toEqual([{
            id: 'option_a',
            groupId: 'group2'
        }]);

        await wrapper.find('.sw-product-variant-modal__reset-filter').trigger('click');

        expect(wrapper.vm.includeOptions).toEqual([]);
    });

    it('should be able to turn on bulk edit modal', async () => {
        await wrapper.setData({
            showBulkEditModal: false,
            productVariants: [{
                id: 'variant1',
                name: 'variant1',
            }],
        });
        expect(wrapper.find('sw-bulk-edit-modal-stub').exists()).toBe(false);

        await wrapper.find('.sw-product-variant-modal__bulk-edit-action').trigger('click');
        expect(wrapper.find('sw-bulk-edit-modal-stub').exists()).toBe(true);
    });

    it('should push to a new route when editing items', async () => {
        wrapper.vm.$router.push = jest.fn();
        await wrapper.setData({
            $refs: {
                variantGrid: {
                    selection: {
                        foo: { states: ['is-download'] },
                    },
                },
            },
        });

        await wrapper.vm.onEditItems();
        expect(wrapper.vm.$router.push).toHaveBeenCalledWith(expect.objectContaining({
            name: 'sw.bulk.edit.product',
            params: expect.objectContaining({
                parentId: '72bfaf5d90214ce592715a9649d8760a',
                includesDigital: true,
            }),
        }));

        wrapper.vm.$router.push.mockRestore();
    });
});
