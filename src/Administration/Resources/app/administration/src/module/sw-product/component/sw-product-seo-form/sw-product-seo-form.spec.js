/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

const manyVariants = Array.from({ length: 30 }, (_, index) => ({
    id: `variant${index + 1}`,
    name: `Variant ${index + 1}`,
    optionName: index === 28 ? 'Red' : `Color ${index + 1}`,
    translated: {
        name: `Variant ${index + 1}`,
    },
}));

function variantMatches(variant, criteria) {
    // Like the server: the term searches the name, the queries search the option names
    const optionNames = criteria.queries.map(({ query }) => query.value);

    return (
        (!!criteria.term && variant.name.includes(criteria.term)) ||
        optionNames.some((optionName) => variant.optionName.includes(optionName))
    );
}

function createVariantRepository(variants = manyVariants) {
    const searchedCriteria = [];

    const search = jest.fn((criteria) => {
        searchedCriteria.push(Shopware.Data.Criteria.fromCriteria(criteria));

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

    // Like the real repository, which searches with the given criteria restricted to the id
    const get = jest.fn((id, context, criteria) => {
        criteria.setIds([id]);

        return search(criteria).then((result) => result.get(id));
    });

    return { searchedCriteria, search, get };
}

describe('module/sw-product/component/sw-product-seo-form', () => {
    async function createWrapper(productEntityOverride, parentProductOverride, productRepository = null) {
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

    /** @tupe Wrapper */
    let wrapper;

    it('should not be visible if there are not variants', async () => {
        const productEntity = {
            canonicalProductId: null,
            childCount: 0,
            metaTitle: 'title',
        };

        wrapper = await createWrapper(productEntity);
        await flushPromises();

        const switchComponent = wrapper.find('.sw-field--switch');
        const singleSelectComponent = wrapper.find('.sw-entity-single-select');

        expect(switchComponent.exists()).toBe(false);
        expect(singleSelectComponent.exists()).toBe(false);
    });

    it('should not be visible if there is a parent product', async () => {
        const productEntity = {
            canonicalProductId: null,
            childCount: 2,
            metaTitle: 'title',
        };

        const parentProduct = {
            id: 'parent-id',
        };

        wrapper = await createWrapper(productEntity, parentProduct);
        await flushPromises();

        const switchComponent = wrapper.find('.sw-field--switch');
        const singleSelectComponent = wrapper.find('.sw-entity-single-select');

        expect(switchComponent.exists()).toBe(false);
        expect(singleSelectComponent.exists()).toBe(false);
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should have a disabled select and a turned off switch if there is no canonical url',
        async () => {
            const productEntity = {
                canonicalProductId: null,
                childCount: 3,
                metaTitle: 'title',
            };

            wrapper = await createWrapper(productEntity);
            await flushPromises();

            const switchComponent = wrapper.getComponent('.mt-switch');
            const singleSelectComponent = wrapper.find('.sw-entity-single-select');

            // check if switch is off
            expect(switchComponent.vm.checked).toBe(false);

            // check if single select is disabled
            expect(singleSelectComponent.classes('is--disabled')).toBe(true);
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should have a selected value if there is a canonical url in the Vuex store',
        async () => {
            const productEntity = {
                id: 'product-id',
                canonicalProductId: 'first',
                childCount: 3,
                metaTitle: 'title',
            };

            wrapper = await createWrapper(productEntity);
            await flushPromises();

            const switchComponent = wrapper.getComponent('.mt-switch');
            const singleSelectComponent = wrapper.get('.sw-entity-single-select');

            // check if switch is turned on
            expect(switchComponent.vm.modelValue).toBe(true);

            // check if single select is enabled
            expect(singleSelectComponent.attributes('disabled')).toBeUndefined();

            // check value of select field
            const textOfSelectField = singleSelectComponent.find('.sw-product-variant-info__product-name').text();
            expect(textOfSelectField).toBe('first');
        },
    );

    it('should show the inherited OG image for variants', async () => {
        const parentMedia = {
            id: 'parent-media-id',
            url: 'https://example.com/parent-image.jpg',
        };

        const productEntity = {
            id: 'child-id',
            childCount: 0,
            metaTitle: 'title',
            openGraphMediaId: null,
            openGraphMedia: null,
        };

        const parentProduct = {
            id: 'parent-id',
            openGraphMediaId: parentMedia.id,
            openGraphMedia: parentMedia,
        };

        wrapper = await createWrapper(productEntity, parentProduct);
        await flushPromises();

        const mediaUpload = wrapper.getComponent({ name: 'sw-media-upload-v2' });

        expect(mediaUpload.props('source')).toEqual(parentMedia);
    });

    it('should show the length of the meta title and description against the recommended length', async () => {
        wrapper = await createWrapper({ metaTitle: 'Title', metaDescription: 'a'.repeat(156), childCount: 0 });
        await flushPromises();

        const hints = wrapper.findAll('.sw-product-seo-form__recommended-length');

        expect(hints).toHaveLength(2);
        expect(hints[0].text()).toBe('sw-product.seoForm.recommendedLength');
        expect(hints[0].classes()).not.toContain('is--exceeded');
        expect(hints[1].classes()).toContain('is--exceeded');
    });

    it('should count empty and inherited values for the recommended length', async () => {
        wrapper = await createWrapper(
            { metaTitle: null, metaDescription: null, childCount: 0 },
            { id: 'parent-id', metaTitle: 'a'.repeat(57), metaDescription: null },
        );
        await flushPromises();

        const hints = wrapper.findAll('.sw-product-seo-form__recommended-length');

        expect(hints[0].classes()).toContain('is--exceeded');
        expect(hints[1].classes()).not.toContain('is--exceeded');
        expect(wrapper.vm.getRecommendedLengthClass(undefined, 56)).toEqual({ 'is--exceeded': false });
    });

    describe('canonical variant select with many variants', () => {
        const productEntity = {
            id: 'product-id',
            childCount: 30,
            metaTitle: 'title',
        };

        beforeEach(() => {
            jest.useFakeTimers();
        });

        afterEach(() => {
            jest.useRealTimers();
        });

        function getSelectionText() {
            return wrapper.get('.sw-entity-single-select__selection-text').text();
        }

        function getResultTexts() {
            return wrapper.findAll('.sw-select-result').map((result) => result.text());
        }

        async function openResultList() {
            await wrapper.get('.sw-select__selection').trigger('click');
            await flushPromises();
        }

        async function closeResultList() {
            await wrapper.get('.sw-select__selection').trigger('keydown.esc');
            await flushPromises();
        }

        async function searchFor(searchTerm) {
            const input = wrapper.get('.sw-entity-single-select__selection-input');
            await input.setValue(searchTerm);
            await input.trigger('input');
            jest.advanceTimersByTime(400);
            await flushPromises();
        }

        async function scrollToTheEndOfTheResultList() {
            const listContent = wrapper.get('.sw-select-result-list__content');
            Object.defineProperty(listContent.element, 'scrollHeight', { value: 1050, configurable: true });
            Object.defineProperty(listContent.element, 'clientHeight', { value: 250, configurable: true });
            Object.defineProperty(listContent.element, 'scrollTop', { value: 800, configurable: true });
            await listContent.trigger('scroll');
            await flushPromises();
        }

        it.activeFeatureFlags(['v6.8.0.0'])('should only search the variants of the product', async () => {
            const productRepository = createVariantRepository();
            wrapper = await createWrapper(
                { ...productEntity, canonicalProductId: 'variant2' },
                undefined,
                productRepository,
            );
            await flushPromises();

            await openResultList();

            const criteria = productRepository.searchedCriteria.at(-1);
            expect(criteria.filters).toEqual([Shopware.Data.Criteria.equals('parentId', 'product-id')]);
            expect(criteria.associations.map(({ association }) => association)).toEqual(['options']);
        });

        it.activeFeatureFlags(['v6.8.0.0'])(
            'should show the selected variant when it is beyond the first page',
            async () => {
                wrapper = await createWrapper(
                    { ...productEntity, canonicalProductId: 'variant28' },
                    undefined,
                    createVariantRepository(),
                );
                await flushPromises();

                expect(getSelectionText()).toBe('Variant 28');
            },
        );

        it.activeFeatureFlags(['v6.8.0.0'])('should search the variants by their option names', async () => {
            const productRepository = createVariantRepository();
            wrapper = await createWrapper(
                { ...productEntity, canonicalProductId: 'variant2' },
                undefined,
                productRepository,
            );
            await flushPromises();

            await openResultList();
            await searchFor('Red');

            expect(getResultTexts()).toEqual(['Variant 29']);
            expect(productRepository.searchedCriteria.at(-1).queries).toEqual([
                { score: 3500, query: Shopware.Data.Criteria.equals('product.options.name', 'Red') },
                { score: 500, query: Shopware.Data.Criteria.contains('product.options.name', 'Red') },
            ]);
        });

        it.activeFeatureFlags(['v6.8.0.0'])(
            'should show all variants and keep the selection after a search without a selection',
            async () => {
                wrapper = await createWrapper(
                    { ...productEntity, canonicalProductId: 'variant2' },
                    undefined,
                    createVariantRepository(),
                );
                await flushPromises();

                await openResultList();
                await searchFor('Red');
                await closeResultList();

                expect(getSelectionText()).toBe('Variant 2');

                await openResultList();

                expect(getResultTexts()).toHaveLength(25);
            },
        );

        it.activeFeatureFlags(['v6.8.0.0'])(
            'should keep the selected variant when the result list is closed without a search',
            async () => {
                wrapper = await createWrapper(
                    { ...productEntity, canonicalProductId: 'variant2' },
                    undefined,
                    createVariantRepository(),
                );
                await flushPromises();

                await openResultList();
                await closeResultList();

                expect(Shopware.Store.get('swProductDetail').product.canonicalProductId).toBe('variant2');
                expect(getSelectionText()).toBe('Variant 2');
            },
        );

        it.activeFeatureFlags(['v6.8.0.0'])(
            'should load the next variants when scrolling to the end of the result list',
            async () => {
                wrapper = await createWrapper(
                    { ...productEntity, canonicalProductId: 'variant2' },
                    undefined,
                    createVariantRepository(),
                );
                await flushPromises();

                await openResultList();
                expect(getResultTexts()).toHaveLength(25);

                await scrollToTheEndOfTheResultList();

                expect(getResultTexts()).toHaveLength(30);
                expect(getResultTexts().at(-1)).toBe('Variant 30');
            },
        );
    });
});
