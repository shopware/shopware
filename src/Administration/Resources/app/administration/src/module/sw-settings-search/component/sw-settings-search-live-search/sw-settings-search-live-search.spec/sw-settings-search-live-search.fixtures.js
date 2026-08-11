/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

export const salesChannels = [
    {
        name: 'Storefront',
        id: '7e0e4a256138402c82a20fcbb4fbb858',
    },
    {
        name: 'Headless',
        id: '98432def39fc4624b33213a56b8c944d',
    },
];

export const productSortings = [
    {
        key: 'score',
        priority: 10,
        label: 'Top results',
        translated: {
            label: 'Top results',
        },
    },
    {
        key: 'name-asc',
        priority: 2,
        label: 'Name A-Z',
        translated: {
            label: 'Name A-Z',
        },
    },
];

export const mockResults = {
    nothing: {
        terms: 'nothing',
        result: {
            elements: [],
        },
    },
    oneResult: {
        terms: 'iron',
        result: {
            elements: [
                {
                    name: 'Durable Iron OpenDoor',
                    extensions: {
                        search: {
                            _score: 28799.999999,
                        },
                    },
                },
            ],
        },
    },
    multipleResults: {
        terms: 'awesome',
        result: {
            elements: [
                {
                    name: 'Awesome Copper Belly-flop Buffet',
                    extensions: {
                        search: {
                            _score: 40320,
                        },
                    },
                },
                {
                    name: 'Awesome Wooden Crystal Qlear',
                    extensions: {
                        search: {
                            _score: 34560,
                        },
                    },
                },
                {
                    name: 'Awesome Silk Ghost Voices',
                    extensions: {
                        search: {
                            _score: 34559.9999,
                        },
                    },
                },
            ],
        },
    },
};

export async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-settings-search-live-search', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $route: {
                        meta: {
                            $module: {
                                icon: 'regular-icon',
                            },
                        },
                    },
                },
                stubs: {
                    'sw-container': true,
                    'sw-field-error': true,
                    'sw-simple-search-field': await wrapTestComponent('sw-simple-search-field'),
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-single-select': await wrapTestComponent('sw-single-select'),
                    'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-popover': {
                        props: ['popoverClass'],
                        template: `
                    <div class="sw-popover" :class="popoverClass">
                        <slot></slot>
                    </div>`,
                    },
                    'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                    'sw-product-variant-info': await wrapTestComponent('sw-product-variant-info'),
                    'sw-settings-search-live-search-keyword': await wrapTestComponent(
                        'sw-settings-search-live-search-keyword',
                    ),
                    'sw-settings-search-example-modal': true,
                    'sw-settings-search-live-search-explain': true,
                    'sw-loader': true,
                    'sw-field-copyable': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                    'sw-checkbox-field': true,
                    'sw-context-menu-item': true,
                    'sw-context-button': true,
                    'sw-data-grid-settings': true,
                    'sw-data-grid-column-boolean': true,
                    'sw-data-grid-inline-edit': true,
                    'router-link': true,
                    'sw-data-grid-skeleton': true,
                    'sw-provide': true,
                },

                provide: {
                    repositoryFactory: {
                        create: (entity) => {
                            if (entity === 'product_sorting') {
                                return {
                                    search: () => {
                                        return Promise.resolve(productSortings);
                                    },
                                };
                            }

                            return {
                                search: () => {
                                    return Promise.resolve(salesChannels);
                                },
                            };
                        },
                    },
                    validationService: {},
                    liveSearchService: {
                        search: jest.fn(({ search: terms }) => {
                            if (terms === mockResults.nothing.terms) {
                                return Promise.resolve(mockResults.nothing.result);
                            }

                            if (terms === mockResults.oneResult.terms) {
                                return Promise.resolve(mockResults.oneResult.result);
                            }

                            if (terms === mockResults.multipleResults.terms) {
                                return Promise.resolve(mockResults.multipleResults.result);
                            }

                            return Promise.resolve({});
                        }),
                    },
                },
            },

            props: {
                currentSalesChannelId: null,
                searchTerms: '',
                searchResults: {},
            },
        },
    );
}
