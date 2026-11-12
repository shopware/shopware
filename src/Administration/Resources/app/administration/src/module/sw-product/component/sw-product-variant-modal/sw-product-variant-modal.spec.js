/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';

function getMedias() {
    return [
        {
            id: '1',
            media: {
                url: 'http://shopware.com/image1.jpg',
            },
        },
        {
            id: '2',
            media: {
                url: 'http://shopware.com/image2.jpg',
            },
        },
    ];
}

function getOptions() {
    return [
        {
            name: 'b',
            translated: {
                name: 'b',
            },
            group: {
                translated: {
                    name: 'color',
                },
            },
            position: 1,
            id: 'option_b',
        },
        {
            name: 'c',
            translated: {
                name: 'c',
            },
            group: {
                translated: {
                    name: 'size',
                },
            },
            position: 5,
            id: 'option_c',
        },
        {
            name: 'a',
            translated: {
                name: 'a',
            },
            group: {
                translated: {
                    name: 'material',
                },
            },
            position: 1,
            id: 'option_a',
        },
    ];
}

function getConfiguratorSettings() {
    return [
        {
            productId: '72bfaf5d90214ce592715a9649d8760a',
            id: '1',
            option: {
                groupId: 'group1',
                name: 'b',
                id: 'option_b',
            },
        },
        {
            productId: '72bfaf5d90214ce592715a9649d8760a',
            id: '2',
            option: {
                groupId: 'group2',
                name: 'a',
                id: 'option_a',
            },
        },
        {
            productId: '72bfaf5d90214ce592715a9649d8760a',
            id: '3',
            option: {
                groupId: 'group3',
                name: 'c',
                id: 'option_c',
            },
        },
    ];
}

function getGroups() {
    return [
        {
            id: 'group1',
            name: 'color',
            displayType: 'text',
            sortingType: 'alphanumeric',
            options: [
                {
                    name: 'b',
                    translated: {
                        name: 'b',
                    },
                    position: 1,
                    id: 'option_b',
                },
                {
                    name: 'b1',
                    translated: {
                        name: 'b1',
                    },
                    position: 2,
                    id: 'option_b1',
                },
            ],
        },
        {
            id: 'group2',
            name: 'size',
            displayType: 'text',
            sortingType: 'alphanumeric',
            options: [
                {
                    name: 'c',
                    translated: {
                        name: 'c',
                    },
                    position: 5,
                    id: 'option_c',
                },
                {
                    name: 'c1',
                    translated: {
                        name: 'c1',
                    },
                    position: 1,
                    id: 'option_c1',
                },
            ],
        },
        {
            id: 'group3',
            name: 'material',
            displayType: 'text',
            sortingType: 'alphanumeric',
            options: [
                {
                    name: 'a',
                    translated: {
                        name: 'a',
                    },
                    position: 1,
                    id: 'option_a',
                },
            ],
        },
    ];
}

function getVariants(returnCurrency = true) {
    const variantPrice = {
        currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
        net: 24,
        gross: 24,
        linked: true,
        listPrice: null,
        extensions: [],
    };

    return {
        price: returnCurrency ? [variantPrice] : null,
        childCount: 2,
        name: 'random product',
        translated: {
            name: 'random product',
        },
        id: '72bfaf5d90214ce592715a9649d8760a',
        options: getOptions(),
    };
}

async function createWrapper(repositoryOverrides = {}) {
    return mount(await wrapTestComponent('sw-product-variant-modal', { sync: true }), {
        attachTo: document.body,
        props: {
            productEntity: {
                price: [
                    {
                        currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                        net: 12,
                        gross: 12,
                        linked: true,
                        listPrice: null,
                        extensions: [],
                    },
                ],
                productNumber: 'SW10000',
                childCount: 2,
                name: 'random product',
                translated: {
                    name: 'name',
                },
                id: '72bfaf5d90214ce592715a9649d8760a',
                configuratorSettings: [
                    {
                        productId: '72bfaf5d90214ce592715a9649d8760a',
                        id: '1',
                        option: {
                            groupId: 'group1',
                            name: 'b',
                            id: 'option_b',
                        },
                    },
                    {
                        productId: '72bfaf5d90214ce592715a9649d8760a',
                        id: '2',
                        option: {
                            groupId: 'group2',
                            name: 'a',
                            id: 'option_a',
                        },
                    },
                    {
                        productId: '72bfaf5d90214ce592715a9649d8760a',
                        id: '3',
                        option: {
                            groupId: 'group3',
                            name: 'c',
                            id: 'option_c',
                        },
                    },
                ],
            },
        },
        global: {
            provide: {
                repositoryFactory: {
                    create: (entity) => {
                        if (repositoryOverrides[entity]) {
                            return repositoryOverrides[entity];
                        }

                        return {
                            get: () => Promise.resolve({ id: 'b7d2554b0ce847cd82f3ac9bd1c0dfca' }),
                            search: () => {
                                if (entity === 'product') {
                                    return Promise.resolve(getVariants());
                                }

                                if (entity === 'property_group') {
                                    return Promise.resolve(getGroups());
                                }

                                if (entity === 'product_media') {
                                    return Promise.resolve(getMedias());
                                }

                                if (entity === 'product_configurator_setting') {
                                    return Promise.resolve(getConfiguratorSettings());
                                }

                                return Promise.resolve([]);
                            },
                        };
                    },
                },
                feature: {
                    isActive: () => true,
                },
            },
            stubs: {
                'sw-simple-search-field': {
                    inheritAttrs: false,
                    props: ['value'],
                    methods: {
                        handleInput(event) {
                            this.$emit('update:value', event.target.value);
                            this.$emit('search-term-change');
                        },
                    },
                    template: `
                        <input
                            :class="$attrs.class"
                            :value="value"
                            @input="handleInput"
                        >
                    `,
                },
                'sw-modal': {
                    template: `
                        <div class="sw-modal">
                            <slot name="modal-header"></slot>
                            <slot></slot>
                            <slot name="modal-footer"></slot>
                        </div>
                    `,
                },
                'sw-label': true,
                'sw-container': {
                    template: '<div><slot></slot></div>',
                },
                'sw-context-menu': {
                    template: '<div class="sw-context-menu"><slot></slot></div>',
                },
                'sw-tree': {
                    props: ['items'],
                    template: `
                        <div class="sw-tree">
                            <slot name="items" :treeItems="items" :checkItem="() => {}"></slot>
                        </div>
                    `,
                },
                'sw-tree-item': {
                    props: ['item', 'activeItemIds', 'activeParentIds'],
                    data() {
                        return {
                            checked: false,
                        };
                    },
                    template: `
                        <div class="sw-tree-item">
                            <input
                                class="sw-tree-item__selection"
                                type="checkbox"
                                :value="checked"
                                @change="toggleItemCheck($event, item)"
                            >
                            <slot name="content" v-bind="{ item }">
                                <span class="sw-tree-item__label">
                                    {{ item.name }}
                                </span>
                            </slot>
                        </div>
                    `,
                    methods: {
                        toggleItemCheck(event, item) {
                            this.checked = event.target.checked;
                            this.item.checked = event.target.checked;

                            this.$emit('check-item', item);
                        },
                    },
                },
                'sw-data-grid': {
                    props: ['dataSource'],
                    computed: {
                        rows() {
                            return Array.isArray(this.dataSource) ? this.dataSource : [];
                        },
                    },
                    template: `
                        <div class="sw-data-grid">
                            <slot name="bulk"></slot>
                            <div v-for="item in rows" :key="item.id" class="sw-data-grid__row">
                                <slot name="column-name" :item="item" :is-inline-edit="false"></slot>
                                <slot name="column-price" :item="item" :is-inline-edit="false"></slot>
                                <slot name="actions" :item="item"></slot>
                            </div>
                            <slot name="bulk-modals"></slot>
                        </div>
                    `,
                },
                'sw-bulk-edit-modal': true,
                'sw-checkbox-field': true,
                'sw-context-menu-item': {
                    props: ['disabled', 'variant'],
                    emits: ['click'],
                    template:
                        '<button type="button" :disabled="disabled" :variant="variant" @click="$emit(\'click\')"><slot></slot></button>',
                },
                'sw-media-preview-v2': true,
                'router-link': true,
                'sw-product-variant-info': true,
                'mt-number-field': true,
                'sw-inheritance-switch': true,
                'sw-price-preview': {
                    name: 'sw-price-preview',
                    props: [
                        'value',
                        'defaultPrice',
                        'taxRate',
                        'currency',
                    ],
                    template: '<div class="sw-price-preview"></div>',
                },
                'sw-color-badge': true,
                'sw-product-variants-media-upload': true,
                'sw-text-field': true,
                'sw-price-field': true,
                'sw-pagination': true,
                'sw-button-process': true,
            },
            mocks: {
                $route: {
                    meta: {
                        $module: {
                            icon: 'regular-content',
                        },
                    },
                },
            },
        },
    });
}

describe('module/sw-product/component/sw-product-variant-modal', () => {
    let wrapper;

    beforeEach(async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        await flushPromises();
    });

    afterEach(() => {
        wrapper?.unmount();
    });

    async function remountWrapper(repositoryOverrides = {}) {
        wrapper.unmount();
        wrapper = await createWrapper(repositoryOverrides);
        await flushPromises();
    }

    it('should wait for the currency before loading variants', async () => {
        let resolveCurrency;
        const currencyRepository = {
            get: jest.fn(
                () =>
                    new Promise((resolve) => {
                        resolveCurrency = resolve;
                    }),
            ),
        };
        const productRepository = {
            search: jest.fn().mockResolvedValue(getVariants()),
        };

        await remountWrapper({
            currency: currencyRepository,
            product: productRepository,
        });
        expect(productRepository.search).not.toHaveBeenCalled();

        resolveCurrency();
        await flushPromises();
        expect(productRepository.search).toHaveBeenCalledTimes(1);
    });

    it('should open the delete confirmation for an unnamed variant', async () => {
        global.activeAclRoles = ['product.deleter'];
        const variant = {
            ...getVariants(),
            translated: { name: '' },
        };
        const productRepository = {
            search: jest.fn().mockResolvedValue([variant]),
        };
        await remountWrapper({ product: productRepository });
        await wrapper.find('.sw-data-grid__row button[variant="danger"]').trigger('click');

        expect(wrapper.find('.sw-product-variant-modal__delete-modal').exists()).toBe(true);
    });

    it('should pass variant prices to the price preview', async () => {
        const variant = getVariants();
        const productRepository = {
            search: jest.fn().mockResolvedValue([variant]),
        };

        await remountWrapper({ product: productRepository });

        expect(wrapper.findComponent({ name: 'sw-price-preview' }).props('value')).toEqual(variant.price);
    });

    it('should use the parent price when a variant inherits its price', async () => {
        const variant = { ...getVariants(false), price: null };
        const productRepository = {
            search: jest.fn().mockResolvedValue([variant]),
        };

        await remountWrapper({ product: productRepository });

        const pricePreview = wrapper.findComponent({ name: 'sw-price-preview' });
        expect(pricePreview.props('value')).toEqual([]);
        expect(pricePreview.props('defaultPrice')).toEqual(wrapper.props('productEntity').price[0]);
    });

    it('should disable variant deletion without the delete permission', async () => {
        const productRepository = {
            search: jest.fn().mockResolvedValue([getVariants()]),
        };

        await remountWrapper({ product: productRepository });

        expect(wrapper.find('.sw-data-grid__row button[variant="danger"]').element.disabled).toBe(true);
    });

    it('should get list groups of product', async () => {
        await flushPromises();

        const filterContextMenu = wrapper.find('.sw-product-variant-modal__filter-context-menu');

        expect(filterContextMenu.attributes().style).toBe('display: none;');

        await wrapper.find('.sw-product-variant-modal__button-filter').trigger('click');
        await flushPromises();

        expect(filterContextMenu.attributes().style).toBeFalsy();
        expect(wrapper.findAll('.sw-tree-item')).toHaveLength(6);
    });

    it('should apply the selected option when filtering variants', async () => {
        const productRepository = {
            search: jest.fn().mockResolvedValue(getVariants()),
        };

        await remountWrapper({ product: productRepository });
        productRepository.search.mockClear();

        await wrapper.find('.sw-product-variant-modal__button-filter').trigger('click');
        const treeItemSelect = wrapper.findAll('.sw-tree-item').at(3);
        await treeItemSelect.find('input').setChecked();
        await treeItemSelect.trigger('change');
        await wrapper
            .find('.sw-product-variant-modal__filter-buttons button:not(.sw-product-variant-modal__reset-filter)')
            .trigger('click');
        await flushPromises();

        const criteria = productRepository.search.mock.calls.at(-1)[0];
        expect(criteria.filters).toContainEqual({
            type: 'equalsAny',
            field: 'product.optionIds',
            value: 'option_b',
        });
    });

    it('should clear selected option filters when resetting them', async () => {
        const productRepository = {
            search: jest.fn().mockResolvedValue(getVariants()),
        };

        await remountWrapper({ product: productRepository });
        productRepository.search.mockClear();

        await wrapper.find('.sw-product-variant-modal__button-filter').trigger('click');
        const treeItemSelect = wrapper.findAll('.sw-tree-item').at(4);
        await treeItemSelect.find('input').setChecked();
        await treeItemSelect.trigger('change');
        await wrapper
            .find('.sw-product-variant-modal__filter-buttons button:not(.sw-product-variant-modal__reset-filter)')
            .trigger('click');
        await flushPromises();

        expect(productRepository.search.mock.calls.at(-1)[0].filters).toContainEqual(
            expect.objectContaining({ field: 'product.optionIds', value: 'option_a' }),
        );

        await wrapper.find('.sw-product-variant-modal__reset-filter').trigger('click');
        await flushPromises();

        expect(productRepository.search.mock.calls.at(-1)[0].filters).not.toEqual(
            expect.arrayContaining([expect.objectContaining({ field: 'product.optionIds' })]),
        );
    });

    it('should load media for the parent product', async () => {
        const productMediaRepository = {
            search: jest.fn().mockResolvedValue(getMedias()),
        };

        await remountWrapper({ product_media: productMediaRepository });

        expect(productMediaRepository.search).toHaveBeenCalledTimes(1);
        expect(productMediaRepository.search.mock.calls[0][0].filters).toContainEqual(
            expect.objectContaining({ field: 'productId', value: '72bfaf5d90214ce592715a9649d8760a' }),
        );
    });

    it('should omit empty search terms created by leading whitespace', async () => {
        const productRepository = {
            search: jest.fn().mockResolvedValue(getVariants()),
        };

        await remountWrapper({ product: productRepository });
        productRepository.search.mockClear();

        await wrapper.find('.sw-product-variant-modal__search').setValue(' red');
        await flushPromises();

        const criteria = productRepository.search.mock.calls.at(-1)[0];
        // Regression test for issue #16838: a leading space produced an empty
        // term, which built a `contains` filter with an empty value and made the
        // API reject the whole query with FRAMEWORK__INVALID_FILTER_QUERY.
        expect(criteria.queries).toHaveLength(2);
        criteria.queries.forEach((query) => {
            expect(query.query.value).not.toBe('');
        });
    });
});
