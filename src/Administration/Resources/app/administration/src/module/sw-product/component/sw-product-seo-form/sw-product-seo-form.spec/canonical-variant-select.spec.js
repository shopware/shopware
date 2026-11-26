/**
 * @sw-package inventory
 */

import { createVariantRepository, createWrapper } from './create-wrapper';

describe('module/sw-product/component/sw-product-seo-form canonical variant select', () => {
    let wrapper;

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

    async function mountWithVariants(canonicalProductId = 'variant2') {
        const productRepository = createVariantRepository();
        wrapper = await createWrapper({ ...productEntity, canonicalProductId }, undefined, productRepository);
        await flushPromises();

        return productRepository;
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
        const productRepository = await mountWithVariants();

        await openResultList();

        const criteria = productRepository.searchedCriteria.at(-1);
        expect(criteria.filters).toEqual([Shopware.Data.Criteria.equals('parentId', 'product-id')]);
        expect(criteria.associations.map(({ association }) => association)).toEqual(['options']);
        expect(criteria.sortings).toEqual([
            Shopware.Data.Criteria.sort('_score', 'DESC'),
            Shopware.Data.Criteria.sort('productNumber', 'ASC'),
            Shopware.Data.Criteria.sort('id', 'ASC'),
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should keep the queries other code added to the criteria', async () => {
        const productRepository = await mountWithVariants();

        await openResultList();
        const extensionQuery = { score: 100, query: Shopware.Data.Criteria.equals('manufacturerNumber', 'M-1') };
        productRepository.criteria.addQuery(extensionQuery.query, extensionQuery.score);

        await searchFor('Red');
        expect(productRepository.searchedCriteria.at(-1).queries).toEqual([
            extensionQuery,
            { score: 3500, query: Shopware.Data.Criteria.equals('product.options.name', 'Red') },
            { score: 500, query: Shopware.Data.Criteria.contains('product.options.name', 'Red') },
        ]);

        await closeResultList();
        await openResultList();
        expect(productRepository.searchedCriteria.at(-1).queries).toEqual([extensionQuery]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should show the selected variant when it is beyond the first page', async () => {
        await mountWithVariants('variant28');

        expect(getSelectionText()).toBe('Variant 28');
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should search the variants by their option names', async () => {
        const productRepository = await mountWithVariants();

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
            await mountWithVariants();

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
            await mountWithVariants();

            await openResultList();
            await closeResultList();

            expect(Shopware.Store.get('swProductDetail').product.canonicalProductId).toBe('variant2');
            expect(getSelectionText()).toBe('Variant 2');
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should load the next variants when scrolling to the end of the result list',
        async () => {
            await mountWithVariants();

            await openResultList();
            expect(getResultTexts()).toHaveLength(25);

            await scrollToTheEndOfTheResultList();

            expect(getResultTexts()).toHaveLength(30);
            expect(getResultTexts().at(-1)).toBe('Variant 30');
        },
    );
});
