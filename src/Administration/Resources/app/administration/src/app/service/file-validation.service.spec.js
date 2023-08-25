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
            expect(checkByExtension({ ...fileMock, type: 'test/test' }, fileAcceptString)).toBe(
                false,
            );
        });

        it('should return false when file has not valid extension', () => {
            expect(checkByExtension({ ...fileMock, name: 'test.test' }, fileAcceptString)).toBe(
                false,
            );
        });

        it('should be able to extend valid types with new type', () => {
            expect(
                checkByExtension(
                    { ...fileMock, type: 'test/test', name: 'test.test' },
                    fileAcceptString,
                    null,
                    { 'test/test': ['test'] },
                ),
            ).toBe(false);
        });

        it('should be able to extend valid types with new extension', () => {
            expect(
                checkByExtension(
                    { ...fileMock, type: 'application/pdf', name: 'test.pdf' },
                    fileAcceptString,
                    null,
                    { 'application/pdf': ['pdf', 'test'] },
                ),
            ).toBe(true);
        });

        it('should return false when extension has invalid format', () => {
            expect(
                checkByExtension({ ...fileMock, name: 'test.pdf/dummy' }, fileAcceptString),
            ).toBe(false);

            expect(
                checkByExtension({ ...fileMock, name: 'test' }, fileAcceptString),
            ).toBe(false);
        });
    });

    describe('by type', () => {
        it('should return true when any type is accepted', () => {
            expect(checkByType(fileMock, '*/*')).toBe(true);
        });

        it('should return false when type categorie is not matching', () => {
            expect(
                checkByType(fileMock, 'dummy/*'),
            ).toBe(false);
        });

        it('should return true when type categorie is matching and specifier is *', () => {
            expect(
                checkByType(fileMock, 'application/*'),
            ).toBe(true);
        });

        it('should return true when type categorie is matching and specifier is matching', () => {
            expect(
                checkByType(fileMock, 'application/pdf'),
            ).toBe(true);
        });

        it('should return true when type categorie is matching and specifier is not matching', () => {
            expect(
                checkByType(fileMock, 'application/bin'),
            ).toBe(false);
        });
    });
});
