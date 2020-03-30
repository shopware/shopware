import { fileSize } from 'src/core/service/utils/format.utils';

describe('src/core/service/utils/format.utils.js', () => {
    describe('filesize', () => {
        test('should convert bytes to a readable format', () => {
            expect(fileSize(0)).toBe('0.00 B');
            expect(fileSize(1018)).toBe('0.99 KB');
            expect(fileSize(1023)).toBe('1.00 KB');
            expect(fileSize(1024)).toBe('1.00 KB');
            expect(fileSize(102400000)).toBe('97.66 MB');
        });
    });
});
