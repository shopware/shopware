import string from 'src/core/service/utils/string.utils';

// Disable developer hints in jest output
jest.spyOn(global.console, 'warn').mockImplementation(() => jest.fn());

// these tests use Blob objects to simulate a File objects
describe('src/core/service/utils/types.utils.js', () => {
    it('should be false if text exists', () => {
        expect(string.isEmptyOrSpaces('A')).toBe(false);
    });
    it('should be false if text with whitespaces exists', () => {
        expect(string.isEmptyOrSpaces(' A ')).toBe(false);
    });
    it('should be true if value is null', () => {
        expect(string.isEmptyOrSpaces(null)).toBe(true);
    });
    it('should be true if value is undefined', () => {
        expect(string.isEmptyOrSpaces(undefined)).toBe(true);
    });
    it('should be true if no text exists', () => {
        expect(string.isEmptyOrSpaces('')).toBe(true);
    });
    it('should be true if only whitespaces exist', () => {
        expect(string.isEmptyOrSpaces('    ')).toBe(true);
    });
});
