import { createPrice, mountPriceMatrices, setUpShippingMethod, unmountPriceMatrices } from './price-matrices.harness';

/**
 * @sw-package checkout
 */

describe('sw-settings-shipping-price-matrices', () => {
    beforeEach(() => {
        setUpShippingMethod([createPrice('price1', 'ruleA')]);
    });

    afterEach(unmountPriceMatrices);

    /** @public-api usedRules */
    it('returns undefined as the used rules, because the shipping detail store has no usedRules', async () => {
        // ARRANGE
        const wrapper = await mountPriceMatrices();

        // ACT
        const usedRules: unknown = wrapper.vm.usedRules;

        // ASSERT
        expect(usedRules).toBeUndefined();
    });

    /** @public-api ruleRepository */
    it('creates the rule repository', async () => {
        // ARRANGE
        const wrapper = await mountPriceMatrices();

        // ACT
        const ruleRepository = wrapper.vm.ruleRepository as { entityName: string };

        // ASSERT
        expect(ruleRepository.entityName).toBe('rule');
    });

    /** @public-api ruleFilter */
    it('filters the first 500 rules to those for prices or without a module type', async () => {
        // ARRANGE
        const wrapper = await mountPriceMatrices();

        // ACT
        const ruleFilter = wrapper.vm.ruleFilter;

        // ASSERT
        expect(ruleFilter.getPage()).toBe(1);
        expect(ruleFilter.getLimit()).toBe(500);
        expect(ruleFilter.getCriteriaData().filters).toEqual([
            {
                type: 'multi',
                operator: 'OR',
                queries: [
                    { type: 'contains', field: 'rule.moduleTypes.types', value: 'price' },
                    { type: 'equals', field: 'rule.moduleTypes', value: null },
                ],
            },
        ]);
    });

    /** @public-api isLoaded */
    it('throws when isLoaded is read, because the component has no currencies', async () => {
        // ARRANGE
        const wrapper = await mountPriceMatrices();

        // ACT
        const readIsLoaded = (): unknown => wrapper.vm.isLoaded;

        // ASSERT
        expect(readIsLoaded).toThrow(TypeError);
    });
});
