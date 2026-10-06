/**
 * @sw-package framework
 */
describe('src/app/filter/breadcrumb.filter.ts', () => {
    const breadcrumbFilter = Shopware.Filter.getByName('breadcrumb');

    it('should contain a filter', () => {
        expect(breadcrumbFilter).toBeDefined();
    });

    it.each([
        [undefined, ''],
        [null, ''],
        [[], ''],
        [{}, ''],
    ])('should return an empty string for %p', (value, expected) => {
        expect(breadcrumbFilter(value)).toBe(expected);
    });

    it('should join an array breadcrumb with the default separator', () => {
        expect(breadcrumbFilter(['Ladies', 'Jackets'])).toBe('Ladies / Jackets');
    });

    it('should join an object breadcrumb with the default separator', () => {
        expect(breadcrumbFilter({ parent: 'Ladies', child: 'Jackets' })).toBe('Ladies / Jackets');
    });

    it('should join with a custom separator', () => {
        expect(breadcrumbFilter(['Ladies', 'Jackets'], ' > ')).toBe('Ladies > Jackets');
    });
});
