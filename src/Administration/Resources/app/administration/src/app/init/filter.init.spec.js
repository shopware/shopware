import createAppFilter from 'src/app/init/filter.init';

describe('src/app/init/filter.init.js', () => {
    beforeAll(() => {
        createAppFilter();
    });

    [
        'asset',
        'currency',
        'date',
        'fileSize',
        'mediaName',
        'salutation',
        'stockColorVariant',
        'striphtml',
        'thumbnailSize',
        'truncate',
        'unicodeUri',
    ].forEach((filterName) => {
        it(`should register filter "${filterName}"`, () => {
            expect(Shopware.Filter.getByName(filterName)).toBeInstanceOf(Function);
        });
    });
});
