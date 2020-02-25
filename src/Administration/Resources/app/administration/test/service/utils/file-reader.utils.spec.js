import fileReaderUtils from 'src/core/service/utils/file-reader.utils';

jest.spyOn(global.console, 'info').mockImplementation(() => jest.fn());

// these tests use Blob objects to simulate a File objects
describe('src/core/service/utils/file-reader.utils.js', () => {
    it('should provide promised based file access', () => {
        const fileMock = new Blob();
        expect(fileReaderUtils.readFileAsText(fileMock)).toBeInstanceOf(Object);
        expect(fileReaderUtils.readFileAsDataURL(fileMock)).toBeInstanceOf(Object);
        expect(fileReaderUtils.readFileAsArrayBuffer(fileMock)).toBeInstanceOf(Object);
    });

    it('should read a file as text', (done) => {
        const fileMock = new Blob(['this is test data']);

        fileReaderUtils.readFileAsText(fileMock).then((loadedText) => {
            expect(loadedText).toBe('this is test data');
        }).finally(done);
    });

    it('should read a file as DataURL', (done) => {
        const fileMock = new Blob(['this is test data']);

        fileReaderUtils.readFileAsDataURL(fileMock).then((dataURL) => {
            expect(dataURL).toMatch(/^data:;base64/);
        }).finally(done);
    });

    it('should read a file as ArrayBuffer', (done) => {
        const fileMock = new Blob(['this is test data']);

        fileReaderUtils.readFileAsArrayBuffer(fileMock).then((dataBuffer) => {
            expect(dataBuffer).toBeInstanceOf(ArrayBuffer);
        }).finally(done);
    });
});
