/**
 * @sw-package discovery
 */
import {
    FILELESS_MEDIA_TYPES,
    isFilelessMediaType,
    REPRESENTATIVE_MEDIA_TYPES,
    isRepresentativeMediaType,
} from 'src/core/service/utils/media-type.utils';

describe('src/core/service/utils/media-type.utils', () => {
    it('should treat a spatial scene as fileless', () => {
        expect(isFilelessMediaType({ mediaType: { name: 'SPATIAL_SCENE' } })).toBe(true);
    });

    it.each([
        'IMAGE',
        'VIDEO',
        'DOCUMENT',
        'SPATIAL_OBJECT',
    ])('should not treat %s as fileless', (name) => {
        expect(isFilelessMediaType({ mediaType: { name } })).toBe(false);
    });

    it('should not treat media without a resolved type as fileless', () => {
        expect(isFilelessMediaType({})).toBe(false);
        expect(isFilelessMediaType({ mediaType: null })).toBe(false);
        expect(isFilelessMediaType(null)).toBe(false);
        expect(isFilelessMediaType(undefined)).toBe(false);
    });

    it('should expose the known fileless types', () => {
        expect(FILELESS_MEDIA_TYPES).toEqual(['SPATIAL_SCENE']);
    });

    describe('isRepresentativeMediaType', () => {
        it.each(REPRESENTATIVE_MEDIA_TYPES)('recognises %s as representing an entity', (typeName) => {
            expect(isRepresentativeMediaType({ mediaType: { name: typeName } })).toBe(true);
        });

        it('treats every other media as the file it is', () => {
            expect(isRepresentativeMediaType({ mediaType: { name: 'IMAGE' } })).toBe(false);
        });

        it.each([
            [undefined],
            [null],
            [{}],
            [{ mediaType: null }],
            [{ mediaType: {} }],
        ])('returns false for %p', (media) => {
            expect(isRepresentativeMediaType(media)).toBe(false);
        });
    });
});
