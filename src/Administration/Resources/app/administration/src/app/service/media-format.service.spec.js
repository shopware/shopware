/**
 * @sw-package discovery
 */

import { isPlayableMediaFormat, shouldShowUnsupportedFormatWarning } from './media-format.service';

describe('app/service/media-format.service.js', () => {
    it.each([
        { mimeType: 'video/mp4', expected: true },
        { mimeType: 'video/ogg', expected: true },
        { mimeType: 'video/webm', expected: true },
        { mimeType: 'audio/mp3', expected: true },
        { mimeType: 'audio/mpeg', expected: true },
        { mimeType: 'audio/ogg', expected: true },
        { mimeType: 'audio/wav', expected: true },
        { mimeType: 'video/quicktime', expected: false },
        { mimeType: 'video/x-msvideo', expected: false },
        { mimeType: 'audio/aac', expected: false },
        { mimeType: 'image/jpeg', expected: false },
        { mimeType: 'application/pdf', expected: false },
    ])(
        'isPlayableMediaFormat should return correct value (mimeType: $mimeType, expected: $expected)',
        ({ mimeType, expected }) => {
            expect(isPlayableMediaFormat(mimeType)).toBe(expected);
        },
    );

    it.each([
        { mimeType: 'video/quicktime', expected: true },
        { mimeType: 'video/x-msvideo', expected: true },
        { mimeType: 'audio/aac', expected: true },
        { mimeType: 'video/mp4', expected: false },
        { mimeType: 'audio/mp3', expected: false },
        { mimeType: 'image/jpeg', expected: false },
        { mimeType: 'application/pdf', expected: false },
    ])(
        'shouldShowUnsupportedFormatWarning should return correct value (mimeType: $mimeType, expected: $expected)',
        ({ mimeType, expected }) => {
            expect(shouldShowUnsupportedFormatWarning(mimeType)).toBe(expected);
        },
    );
});
