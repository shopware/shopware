/**
 * @package admin
 */
describe('src/app/filter/thumbnail-size.filter.ts', () => {
    const thumbnailSizeFilter = Shopware.Filter.getByName('thumbnailSize');
    const mediaThumbnailFactory = Shopware.Service('repositoryFactory').create('media_thumbnail_size');

    it('should contain a filter', () => {
        expect(thumbnailSizeFilter).toBeDefined();
    });

    it('should return empty string fallback when no value is given', () => {
        expect(thumbnailSizeFilter()).toBe('');
    });

    it('should return empty string fallback when no width and height is given', () => {
        const mockMedia = mediaThumbnailFactory.create();
        mockMedia.width = 500;

        expect(thumbnailSizeFilter(mockMedia)).toBe('');
    });

    it('should return thumbnail size as string when width and height is given', () => {
        const mockMedia = mediaThumbnailFactory.create();
        mockMedia.width = 500;
        mockMedia.height = 700;

        expect(thumbnailSizeFilter(mockMedia)).toBe('500x700');
    });
});
