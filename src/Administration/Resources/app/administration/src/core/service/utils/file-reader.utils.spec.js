/**
 * @package admin
 */

import fileReaderUtils from 'src/core/service/utils/file-reader.utils';

jest.spyOn(global.console, 'info').mockImplementation(() => jest.fn());

// these tests use Blob objects to simulate a File objects
describe('src/core/service/utils/file-reader.utils.js', () => {
    it('should provide promised based file access', async () => {
        const fileMock = new Blob();
        expect(fileReaderUtils.readFileAsText(fileMock)).toBeInstanceOf(Object);
        expect(fileReaderUtils.readFileAsDataURL(fileMock)).toBeInstanceOf(Object);
        expect(fileReaderUtils.readFileAsArrayBuffer(fileMock)).toBeInstanceOf(Object);
    });

    it('should read a file as text', async () => {
        const fileMock = new Blob(['this is test data']);

        const loadedText = await fileReaderUtils.readFileAsText(fileMock);
        expect(loadedText).toBe('this is test data');
    });

    it('should read a file as DataURL', async () => {
        const fileMock = new Blob(['this is test data']);

        const dataURL = await fileReaderUtils.readFileAsDataURL(fileMock);
        expect(dataURL).toMatch(/^data:.*;base64.*/);
    });

    it('should read a file as ArrayBuffer', async () => {
        const fileMock = new Blob(['this is test data']);

        const dataBuffer = await fileReaderUtils.readFileAsArrayBuffer(fileMock);
        expect(dataBuffer).toBeInstanceOf(ArrayBuffer);
    });

    it('should get Name and Extension from an URL', async () => {
        const urlObject = { href: 'http://localhost/picture%20with%20blanks.png' };

        expect(fileReaderUtils.getNameAndExtensionFromUrl(urlObject))
            .toEqual({ extension: 'png', fileName: 'picture with blanks' });
    });
});
