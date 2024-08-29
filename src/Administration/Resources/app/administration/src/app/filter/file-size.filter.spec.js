/**
 * @package admin
 * @group disabledCompat
 */
describe('src/app/filter/file-size.filter.js', () => {
    const fileSizeFilter = Shopware.Filter.getByName('fileSize');

    Shopware.Utils.format.fileSize = jest.fn();

    beforeEach(() => {
        Shopware.Utils.format.fileSize.mockClear();
    });

    it('should contain a filter', () => {
        expect(fileSizeFilter).toBeDefined();
    });

    it('should return empty string when no value is given', () => {
        expect(fileSizeFilter()).toBe('');
    });

    it('should call the fileSize format util for formatting', () => {
        fileSizeFilter(
            1856165,
            {
                myLocaleOptions: 'foo',
            },
        );

        expect(Shopware.Utils.format.fileSize).toHaveBeenCalledWith(
            1856165,
            {
                myLocaleOptions: 'foo',
            },
        );
    });
});
