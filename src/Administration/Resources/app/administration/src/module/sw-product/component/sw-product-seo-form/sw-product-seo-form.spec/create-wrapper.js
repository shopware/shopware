/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

export const manyVariants = Array.from({ length: 30 }, (_, index) => ({
    id: `variant${index + 1}`,
    name: `Variant ${index + 1}`,
    optionName: index === 28 ? 'Red' : `Color ${index + 1}`,
    translated: {
        name: `Variant ${index + 1}`,
    },
}));

function variantMatches(variant, criteria) {
    const optionNames = criteria.queries
        .filter(({ query }) => query.field === 'product.options.name')
        .map(({ query }) => query.value);

    return (
        (!!criteria.term && variant.name.includes(criteria.term)) ||
        optionNames.some((optionName) => variant.optionName.includes(optionName))
    );
}

export function createVariantRepository(variants = manyVariants) {
    const repository = { searchedCriteria: [], criteria: null };

    repository.search = jest.fn((criteria) => {
        repository.searchedCriteria.push(Shopware.Data.Criteria.fromCriteria(criteria));
        repository.criteria = criteria;

        let result = variants;

        if (criteria.ids.length > 0) {
            result = result.filter((variant) => criteria.ids.includes(variant.id));
        }

        if (criteria.term || criteria.queries.length > 0) {
            result = result.filter((variant) => variantMatches(variant, criteria));
        }

        const offset = (criteria.page - 1) * criteria.limit;

        return Promise.resolve(
            new EntityCollection(
                '/product',
                'product',
                null,
                criteria,
                result.slice(offset, offset + criteria.limit),
                result.length,
            ),
        );
    });

    repository.get = jest.fn((id, context, criteria) => {
        criteria.setIds([id]);

        return repository.search(criteria).then((result) => result.get(id));
    });

    return repository;
}

export async function createWrapper(productEntityOverride, parentProductOverride, productRepository = null) {
    const productEntity = productEntityOverride || {
        metaTitle: 'test',
    };

    const parentProduct = parentProductOverride || {
        id: null,
    };

    const productVariants = [
        {
            id: 'first',
            name: 'first',
            optionName: 'first',
            translated: {
                name: 'first',
            },
        },
    ];

    const mediaEntities = {
        'parent-media-id': {
            id: 'parent-media-id',
            url: 'https://example.com/parent-image.jpg',
        },
        'child-media-id': {
            id: 'child-media-id',
            url: 'https://example.com/child-image.jpg',
        },
    };

    Shopware.Store.get('swProductDetail').product = productEntity;
    Shopware.Store.get('swProductDetail').parentProduct = parentProduct;

    return mount(await wrapTestComponent('sw-product-seo-form', { sync: true }), {
        global: {
            directives: {
                tooltip: {},
            },
            provide: {
                repositoryFactory: {
                    create: (entityName) => {
                        if (entityName === 'media') {
                            return {
                                get: (id) => {
                                    return Promise.resolve(mediaEntities[id] || null);
                                },
                            };
                        }

                        return productRepository ?? createVariantRepository(productVariants);
                    },
                },
                validationService: {},
            },
            stubs: {
                'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),

                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-product-variant-info': await wrapTestComponent('sw-product-variant-info'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-popover': {
                    template: '<div class="sw-popover"><slot></slot></div>',
                },
                'sw-help-text': true,
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-textarea-field': await wrapTestComponent('sw-textarea-field'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-inheritance-switch': true,
                'sw-field-copyable': true,
                'sw-textarea-field-deprecated': true,
                'sw-ai-copilot-badge': true,
                'sw-highlight-text': true,
                'sw-loader': true,
                'sw-media-modal-v2': true,
                'sw-upload-listener': true,
                'sw-media-upload-v2': {
                    name: 'sw-media-upload-v2',
                    template: '<div class="sw-media-upload-v2"></div>',
                    props: [
                        'source',
                        'disabled',
                        'uploadTag',
                        'allowMultiSelect',
                        'caption',
                        'fileAccept',
                    ],
                },
            },
        },
    });
}
