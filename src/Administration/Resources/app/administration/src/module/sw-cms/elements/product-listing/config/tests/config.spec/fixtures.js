/**
 * @sw-package discovery
 */
import { reactive } from 'vue';
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/service/cms.service';
import EntityCollection from 'src/core/data/entity-collection.data';

const productSortingRepositoryMock = {
    search() {
        return Promise.resolve(new EntityCollection('', '', Shopware.Context.api, null, [{}], 1));
    },
    route: '/product_sorting',
    schema: {
        entity: 'product_sorting',
    },
};

const propertyGroupMock = [
    { id: 'x01', name: 'bar' },
    { id: 'x02', name: 'baz' },
    { id: 'x03', name: 'foo' },
];

const propertyGroupRepositoryMock = {
    search(criteria) {
        let properties = [...propertyGroupMock];
        if (criteria?.term) {
            properties = properties.filter((propertyGroup) => propertyGroup.name.includes(criteria.term));
        }

        return Promise.resolve(properties);
    },
    route: '/property_group',
    schema: {
        entity: 'property_group',
    },
};

const repositoryMockFactory = (entity) => {
    if (entity === 'product_sorting') {
        return productSortingRepositoryMock;
    }

    if (entity === 'property_group') {
        return propertyGroupRepositoryMock;
    }

    throw new Error(`Repository for ${entity} is not implemented`);
};

export async function createWrapper(activeTab = 'sorting', { featureActive = false } = {}) {
    return mount(
        await wrapTestComponent('sw-cms-el-config-product-listing', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-cms-el-config-product-listing-config-sorting-grid': true,
                    'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                    'sw-entity-single-select': true,
                    'sw-simple-search-field': true,
                    'sw-entity-multi-select': true,
                    'sw-select-field': true,

                    'sw-pagination': true,
                    'sw-container': true,
                    'sw-tabs-item': true,
                    'mt-tabs': {
                        name: 'mt-tabs',
                        emits: ['new-item-active'],
                        props: {
                            defaultItem: {
                                type: String,
                                required: false,
                                default: undefined,
                            },
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                required: true,
                            },
                        },
                        template: '<div class="mt-tabs"></div>',
                    },

                    'sw-tabs': {
                        data() {
                            return { active: activeTab };
                        },
                        template: `
                        <div class="sw-tabs">
                            <slot></slot>
                            <slot name="content" v-bind="{ active }"></slot>
                        </div>
                    `,
                    },
                    'sw-highlight-text': true,
                    'sw-select-result': true,
                    'sw-checkbox-field': true,
                    'sw-context-menu-item': true,
                    'sw-context-button': true,
                    'sw-data-grid-settings': true,
                    'sw-data-grid-column-boolean': true,
                    'sw-data-grid-inline-edit': true,
                    'router-link': true,
                    'sw-data-grid-skeleton': true,
                    'sw-provide': true,
                    'sw-cms-inherit-wrapper': {
                        template: '<div><slot :isInherited="false"></slot></div>',
                        props: [
                            'field',
                            'element',
                            'contentEntity',
                            'label',
                        ],
                    },
                },
                provide: {
                    feature: {
                        isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
                    },
                    cmsService: Shopware.Service('cmsService'),
                    repositoryFactory: {
                        create: (entity) => repositoryMockFactory(entity),
                    },
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
            props: reactive({
                defaultConfig: {},
                element: {
                    type: 'product-listing',
                    config: {
                        boxLayout: {
                            value: {},
                        },
                        boxHeadlineLevel: {
                            value: null,
                        },
                        defaultSorting: {
                            value: {},
                        },
                        availableSortings: {
                            value: {},
                        },
                        showSorting: {
                            value: true,
                        },
                        useCustomSorting: {
                            value: true,
                        },
                        filters: {
                            value: 'manufacturer-filter,rating-filter,price-filter,shipping-free-filter,property-filter',
                        },
                        // eslint-disable-next-line inclusive-language/use-inclusive-words
                        propertyWhitelist: {
                            value: [],
                        },
                    },
                },
            }),
        },
    );
}

export function registerCmsPageStore() {
    Shopware.Store.register({
        id: 'cmsPage',
    });
}

export { EntityCollection };
