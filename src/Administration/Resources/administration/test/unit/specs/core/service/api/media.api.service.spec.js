import { Application, State } from 'src/core/shopware';
import { itAsync, afterEachAsync, beforeEachAsync } from '../../../../async-helper';

describe('core/service/api/mediaService', () => {
    let mediaService;
    let mediaStore;
    let testCatalog;

    beforeEachAsync((done) => {
        mediaService = Application.getContainer('service').mediaService;
        mediaStore = State.getStore('media');

        const catalogStore = State.getStore('catalog');
        testCatalog = catalogStore.create();
        testCatalog.name = 'testCatalog';

        testCatalog.save().then(() => {
            done();
        }).catch((err) => {
            done(err);
        });
    }, 10000);

    afterEachAsync((done) => {
        testCatalog.isLocal = false;
        testCatalog.delete(true).then(() => {
            done();
        }).catch((err) => {
            done(err);
        });
    }, 10000);

    itAsync('should save file from url', (done) => {
        const mediaItem = mediaStore.create();
        mediaItem.name = 'testItem';
        mediaItem.catalogId = testCatalog.id;

        const testUrl = `${process.env.APP_URL}/api/v1/_info/entity-schema.json`;
        mediaItem.save().then(() => {
            mediaService.uploadMediaFromUrl(mediaItem.id, testUrl, 'json').then(() => {
                done();
            }).catch((err) => {
                done(err);
            });
        }).then(() => {
            mediaStore.getByIdAsync(mediaItem.id).then((objUnderTest) => {
                expect(objUnderTest.mimeType).to.equal('text/plain');
                expect(objUnderTest.url).to.match(/.json$/);

                done();
            }).catch((err) => {
                done(err);
            });
        }).finally(() => {
            mediaStore.getByIdAsync(mediaItem.id).then((media) => {
                media.delete(true).then(() => {
                    done();
                }).catch((err) => {
                    done(err);
                });
            }).catch((err) => {
                done(err);
            });
        });
    });
});
