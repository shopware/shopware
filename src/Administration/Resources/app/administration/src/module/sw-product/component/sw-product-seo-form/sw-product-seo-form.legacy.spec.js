/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';

// @deprecated tag:v6.8.0 - The tests will be removed with the canonical variant select without the v6.8.0.0 flag.
describe('module/sw-product/component/sw-product-seo-form without the v6.8.0.0 flag', () => {
    async function createWrapper(productEntityOverride, parentProductOverride) {
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
                optionName: 'Blue',
                translated: {
                    name: 'first',
                },
            },
            {
                id: 'second',
                name: 'second',
                optionName: 'Red',
                translated: {
                    name: 'second',
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

                            return {
                                search: (criteria) => {
                                    const optionNames = criteria.queries.map(({ query }) => query.value);

                                    return Promise.resolve(
                                        optionNames.length > 0
                                            ? productVariants.filter((variant) => optionNames.includes(variant.optionName))
                                            : productVariants,
                                    );
                                },
                            };
                        },
                    },
                    validationService: {},
                },
                stubs: {
                    'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),

                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                    'sw-single-select': await wrapTestComponent('sw-single-select'),
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

    /** @tupe Wrapper */
    let wrapper;

    it.deprecated('v6.8.0.0')(
        'should have a disabled select and a turned off switch if there is no canonical url',
        async () => {
            const productEntity = {
                canonicalProductId: null,
                childCount: 3,
                metaTitle: 'title',
            };

            wrapper = await createWrapper(productEntity);
            await flushPromises();

            const switchInput = wrapper.get('.mt-switch input');
            const singleSelectComponent = wrapper.find('.sw-single-select');

            // check if switch is off
            expect(switchInput.element.checked).toBe(false);

            // check if single select is disabled
            expect(singleSelectComponent.classes('is--disabled')).toBe(true);
        },
    );

    it.deprecated('v6.8.0.0')('should have a selected value if there is a canonical url in the Vuex store', async () => {
        const productEntity = {
            id: 'product-id',
            canonicalProductId: 'first',
            childCount: 3,
            metaTitle: 'title',
        };

        wrapper = await createWrapper(productEntity);
        await flushPromises();

        const switchInput = wrapper.get('.mt-switch input');
        const singleSelectComponent = wrapper.get('.sw-single-select');

        // check if switch is turned on
        expect(switchInput.element.checked).toBe(true);

        // check if single select is enabled
        expect(singleSelectComponent.attributes('disabled')).toBeUndefined();

        // check value of select field
        const textOfSelectField = singleSelectComponent.find('.sw-product-variant-info__product-name').text();
        expect(textOfSelectField).toBe('first');
    });

    it.deprecated('v6.8.0.0')('should search the variants by their option names', async () => {
        jest.useFakeTimers();

        wrapper = await createWrapper({
            id: 'product-id',
            canonicalProductId: 'first',
            childCount: 2,
            metaTitle: 'title',
        });
        await flushPromises();

        await wrapper.get('.sw-select__selection').trigger('click');
        await flushPromises();

        const input = wrapper.get('.sw-single-select__selection-input');
        await input.setValue('Red');
        await input.trigger('input');
        jest.advanceTimersByTime(100);
        await flushPromises();

        expect(wrapper.findAll('.sw-select-result').map((result) => result.text())).toEqual(['second']);

        jest.useRealTimers();
    });
});
