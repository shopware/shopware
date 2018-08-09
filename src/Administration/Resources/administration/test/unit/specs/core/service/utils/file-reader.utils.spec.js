import fileReaderUtils from 'src/core/service/utils/file-reader.utils';

// these tests use Blob objects to simulate a File objects
describe('src/core/service/utils/file-reader.utils.js', () => {
    it('should provide promised based file access', () => {
        const fileMock = new Blob();
        expect(fileReaderUtils.readFileAsText(fileMock)).to.be.a('Promise');
        expect(fileReaderUtils.readFileAsDataURL(fileMock)).to.be.a('Promise');
        expect(fileReaderUtils.readFileAsArrayBuffer(fileMock)).to.be.a('Promise');
    });

    it('should read a file as text', (done) => {
        const fileMock = new Blob(['this is test data']);

        fileReaderUtils.readFileAsText(fileMock).then((loadedText) => {
            expect(loadedText).to.equal('this is test data');
        }).finally(done);
    });

    it('should read a file as DataURL', (done) => {
        const fileMock = new Blob(['this is test data']);

        fileReaderUtils.readFileAsDataURL(fileMock).then((dataURL) => {
            expect(dataURL).to.match(/^data:;base64/);
        }).finally(done);
    });

    it('should read a file as ArrayBuffer', (done) => {
        const fileMock = new Blob(['this is test data']);

        fileReaderUtils.readFileAsArrayBuffer(fileMock).then((dataBuffer) => {
            expect(dataBuffer).to.be.a('ArrayBuffer');
        }).finally(done);
    });
});
