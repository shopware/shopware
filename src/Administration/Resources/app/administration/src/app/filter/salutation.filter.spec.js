/**
 * @package admin
 */
describe('src/app/filter/salutation.filter.ts', () => {
    const salutationFilter = Shopware.Filter.getByName('salutation');

    it('should contain a filter', () => {
        expect(salutationFilter).toBeDefined();
    });

    it('should return empty string fallback when no value is given', () => {
        expect(salutationFilter()).toBe('');
    });

    it('should return given fallback when no value is given', () => {
        expect(salutationFilter(undefined, 'fooBar')).toBe('fooBar');
    });

    it('should return the correct salutation', () => {
        expect(salutationFilter({
            salutation: {
                id: '1',
                salutationKey: 'mr',
                displayName: 'Mr.',
            },
            title: 'Dr.',
            firstName: 'Max',
            lastName: 'Mustermann',
        })).toBe('Mr. Dr. Max Mustermann');
    });

    it('should hide salutation when no salutationKey was defined', () => {
        expect(salutationFilter({
            salutation: {
                id: '1',
                salutationKey: 'not_specified',
                displayName: 'Mr.',
            },
            title: 'Dr.',
            firstName: 'Max',
            lastName: 'Mustermann',
        })).toBe('Dr. Max Mustermann');
    });

    it('should return the fallback snippet when no subvalues are given', () => {
        expect(salutationFilter({
            salutation: {
                id: '1',
                salutationKey: 'mr',
                displayName: '',
            },
            title: '',
            firstName: '',
            lastName: '',
        })).toBe('');
    });
});
