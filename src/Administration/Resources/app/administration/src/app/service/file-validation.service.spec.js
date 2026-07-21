/**
 * @sw-package framework
 */
import fileValidationService from './file-validation.service';

const { checkByExtension, checkByType } = fileValidationService();

const fileAcceptString = 'png, doc, txt, pdf';

const fileMock = {
    name: 'test.pdf',
    type: 'application/pdf',
    size: 10,
};

describe('src/app/service/file-helper.service.ts', () => {
    describe('by extension', () => {
        it('should return true when any extension is accepted', () => {
            expect(checkByExtension(fileMock, '*')).toBe(true);
        });

        it('should return true when file has valid type and extension', () => {
            expect(checkByExtension(fileMock, fileAcceptString)).toBe(true);
        });

        it('should return false when file has not valid type', () => {
            expect(checkByExtension({ ...fileMock, type: 'test/test' }, fileAcceptString)).toBe(false);
        });

        it('should return false when file has not valid extension', () => {
            expect(checkByExtension({ ...fileMock, name: 'test.test' }, fileAcceptString)).toBe(false);
        });

        it('should be able to extend valid types with new type', () => {
            expect(
                checkByExtension({ ...fileMock, type: 'test/test', name: 'test.test' }, 'test', {
                    'test/test': ['test'],
                }),
            ).toBe(true);
        });

        it('should be able to extend valid types with new extension', () => {
            expect(
                checkByExtension({ ...fileMock, type: 'application/pdf', name: 'test.test' }, 'test', {
                    'application/pdf': [
                        'pdf',
                        'test',
                    ],
                }),
            ).toBe(true);
        });

        it('should return false when extension has invalid format', () => {
            expect(checkByExtension({ ...fileMock, name: 'test.pdf/dummy' }, fileAcceptString)).toBe(false);

            expect(checkByExtension({ ...fileMock, name: 'test' }, fileAcceptString)).toBe(false);
        });

        it('should return true when filename contains dots', () => {
            expect(checkByExtension({ ...fileMock, name: 'test.dummy.pdf' }, fileAcceptString)).toBe(true);
        });

        it('should return false when filename is empty', () => {
            expect(checkByExtension({ ...fileMock, name: '' }, fileAcceptString)).toBe(false);
        });

        it('should return true for backend allowed extension and mime type', () => {
            expect(
                checkByExtension({ ...fileMock, name: 'book.epub', type: 'application/epub+zip' }, 'epub', null, {
                    epub: ['application/epub+zip'],
                }),
            ).toBe(true);
        });

        it('should return false when backend metadata knows the mime type for another extension', () => {
            expect(
                checkByExtension({ ...fileMock, name: 'book.epub', type: 'application/pdf' }, 'pdf, epub', null, {
                    pdf: ['application/pdf'],
                    epub: ['application/epub+zip'],
                }),
            ).toBe(false);
        });

        it('should return true when backend metadata does not know the browser mime type', () => {
            expect(
                checkByExtension({ ...fileMock, name: 'book.epub', type: 'application/x-unknown' }, 'epub', null, {
                    epub: ['application/epub+zip'],
                }),
            ).toBe(true);
        });

        it('should return true when backend metadata exists and the browser mime type is empty', () => {
            expect(
                checkByExtension({ ...fileMock, name: 'book.epub', type: '' }, 'epub', null, {
                    epub: ['application/epub+zip'],
                }),
            ).toBe(true);
        });

        it('should use legacy fallback when no backend metadata is passed', () => {
            expect(checkByExtension({ ...fileMock, name: 'book.epub', type: 'application/epub+zip' }, 'epub')).toBe(false);
        });
    });

    describe('by type', () => {
        it('should return true when any type is accepted', () => {
            expect(checkByType(fileMock, '*/*')).toBe(true);
        });

        it('should return false when type category is not matching', () => {
            expect(checkByType(fileMock, 'dummy/*')).toBe(false);
        });

        it('should return true when type category is matching and specifier is *', () => {
            expect(checkByType(fileMock, 'application/*')).toBe(true);
        });

        it('should return true when type category is matching and specifier is matching', () => {
            expect(checkByType(fileMock, 'application/pdf')).toBe(true);
        });

        it('should return false when type category is matching and specifier is not matching', () => {
            expect(checkByType(fileMock, 'application/bin')).toBe(false);
        });

        it('should return true when one of the mime types match', () => {
            expect(checkByType(fileMock, 'application/bin, application/pdf')).toBe(true);
        });

        it('should return false when nones of the mime types match', () => {
            expect(checkByType(fileMock, 'application/bin, text/plain')).toBe(false);
        });

        it('should return true when checking for the `model/gltf-binary` with an empty mime type but matching extension', () => {
            expect(checkByType({ ...fileMock, type: '', name: 'test.glb' }, 'model/gltf-binary')).toBe(true);
        });

        it('should return true when checking for `model/gltf-binary` among multiple allowed mime-types', () => {
            expect(
                checkByType(
                    {
                        ...fileMock,
                        type: 'model/gltf-binary',
                        name: 'test.glb',
                    },
                    'image/png, model/gltf-binary',
                ),
            ).toBe(true);
        });

        it('should return true when checking for `model/gltf-binary` or `image/*` with a png', () => {
            expect(checkByType({ ...fileMock, type: 'image/png', name: 'test.png' }, 'model/gltf-binary, image/*')).toBe(
                true,
            );
        });

        it('should return false when checking for the `model/gltf-binary` with an empty mime type and non matching extension', () => {
            expect(checkByType({ ...fileMock, type: '', name: 'test.txt' }, 'model/gltf-binary')).toBe(false);
        });
    });
});
