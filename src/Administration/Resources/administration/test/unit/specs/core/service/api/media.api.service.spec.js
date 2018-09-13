import { Application, State } from 'src/core/shopware';

describe('core/service/api/mediaService', () => {
    let mediaService;
    let mediaStore;
    let testCatalog;

    beforeEach(() => {
        mediaService = Application.getContainer('service').mediaService;
        mediaStore = State.getStore('media');

        const catalogStore = State.getStore('catalog');
        testCatalog = catalogStore.create();
        testCatalog.name = 'testCatalog';

        return testCatalog.save();
    });

    afterEach(() => {
        testCatalog.isLocal = false;
        return testCatalog.delete(true);
    });

    it('should save image from url', () => {
        const mediaItem = mediaStore.create();
        mediaItem.name = 'testItem';
        mediaItem.catalogId = testCatalog.id;

        const testUrl = 'http://localhost:8000/api/v1/entity-schema.json';

        return mediaItem.save().then(() => {
            return mediaService.uploadMediaFromUrl(mediaItem.id, testUrl, '.json');
        }).then(() => {
            return mediaStore.getByIdAsync(mediaItem.id, true).then((objUnderTest) => {
                expect(objUnderTest.mimeType).to.equal('text/plain');
                expect(objUnderTest.url).to.match(/.json$/);
            });
        }).finally(() => {
            return mediaStore.getByIdAsync(mediaItem.id, true).then((media) => {
                return media.delete(true);
            });
        });
    });
});
