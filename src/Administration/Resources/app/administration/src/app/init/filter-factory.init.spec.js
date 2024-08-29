/**
 * @package admin
 * @group disabledCompat
 */
import initializeFilterFactory from 'src/app/init/filter-factory.init';
import FilterFactory from 'src/core/data/filter-factory.data';

describe('src/app/init/filter-factory.init.ts', () => {
    beforeAll(() => {
        initializeFilterFactory();
    });

    it('should register the filter Factory', () => {
        expect(Shopware.Service('filterFactory')).toBeInstanceOf(FilterFactory);
    });
});
