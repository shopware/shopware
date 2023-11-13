describe('src/app/filter/media-name.filter.js', () => {
    const mediaNameFilter = Shopware.Filter.getByName('mediaName');

    it('should contain a filter', () => {
        expect(mediaNameFilter).toBeDefined();
    });

    it('should return empty string fallback when no value is given', () => {
        expect(mediaNameFilter()).toBe('');
    });

    it('should return given fallback when no value is given', () => {
        expect(mediaNameFilter(undefined, 'fooBar')).toBe('fooBar');
    });

    it('should return the values inside the entity by default', () => {
        expect(mediaNameFilter({
            entity: {
                fileName: 'my-file-name',
                fileExtension: 'jpg',
            },
        })).toBe('my-file-name.jpg');
    });

    it('should return the values even when not entity is given', () => {
        expect(mediaNameFilter({
            fileName: 'my-file-name',
            fileExtension: 'jpg',
        })).toBe('my-file-name.jpg');
    });

    it('should return the fallback when fileName or fileExtension is missing', () => {
        expect(mediaNameFilter(
            {
                fileNameFoo: 'my-file-name',
                fileExtensionBar: 'jpg',
            },
            'my-fallback',
        )).toBe('my-fallback');
    });
});
