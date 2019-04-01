import { fileSize } from 'src/core/service/utils/format.utils';

describe('src/core/service/utils/format.utils.js', () => {
    describe('filesize', () => {
        it('should convert bytes to a readable format', () => {
            expect(fileSize(0)).to.equal('0.00B');
            expect(fileSize(1018)).to.equal('0.99KB');
            expect(fileSize(1023)).to.equal('1.00KB');
            expect(fileSize(1024)).to.equal('1.00KB');
            expect(fileSize(102400000)).to.equal('97.66MB');
        });
    });
});
