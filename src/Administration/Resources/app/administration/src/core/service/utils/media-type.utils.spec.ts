/**
 * @sw-package discovery
 */
import {
    registerFilelessMediaType,
    isFilelessMediaType,
    registerRepresentativeMediaType,
    isRepresentativeMediaType,
} from 'src/core/service/utils/media-type.utils';

describe('src/core/service/utils/media-type.utils', () => {
    describe('isFilelessMediaType', () => {
        it('recognises a registered type', () => {
            registerFilelessMediaType('DESCRIBED_ELSEWHERE');

            expect(isFilelessMediaType({ mediaType: { name: 'DESCRIBED_ELSEWHERE' } })).toBe(true);
        });

        it.each([
            'IMAGE',
            'VIDEO',
            'DOCUMENT',
            'SPATIAL_OBJECT',
        ])('treats %s as an ordinary file-carrying type', (name) => {
            expect(isFilelessMediaType({ mediaType: { name } })).toBe(false);
        });

        it.each([
            [undefined],
            [null],
            [{}],
            [{ mediaType: null }],
            [{ mediaType: {} }],
        ])('returns false for %p', (media) => {
            expect(isFilelessMediaType(media)).toBe(false);
        });
    });

    describe('isRepresentativeMediaType', () => {
        it('recognises a registered type', () => {
            registerRepresentativeMediaType('STANDS_FOR_SOMETHING');

            expect(isRepresentativeMediaType({ mediaType: { name: 'STANDS_FOR_SOMETHING' } })).toBe(true);
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

    it('keeps the two lists apart', () => {
        registerFilelessMediaType('ONLY_FILELESS');
        registerRepresentativeMediaType('ONLY_REPRESENTATIVE');

        expect(isRepresentativeMediaType({ mediaType: { name: 'ONLY_FILELESS' } })).toBe(false);
        expect(isFilelessMediaType({ mediaType: { name: 'ONLY_REPRESENTATIVE' } })).toBe(false);
    });
});
