/**
 * @package admin
 */

import string from 'src/core/service/utils/string.utils';

// these tests use Blob objects to simulate a File objects
describe('src/core/service/utils/types.utils.js', () => {
    it('should be false if text exists', async () => {
        expect(string.isEmptyOrSpaces('A')).toBe(false);
    });
    it('should be false if text with whitespaces exists', async () => {
        expect(string.isEmptyOrSpaces(' A ')).toBe(false);
    });
    it('should be true if value is null', async () => {
        expect(string.isEmptyOrSpaces(null)).toBe(true);
    });
    it('should be true if value is undefined', async () => {
        expect(string.isEmptyOrSpaces(undefined)).toBe(true);
    });
    it('should be true if no text exists', async () => {
        expect(string.isEmptyOrSpaces('')).toBe(true);
    });
    it('should be true if only whitespaces exist', async () => {
        expect(string.isEmptyOrSpaces('    ')).toBe(true);
    });
});
