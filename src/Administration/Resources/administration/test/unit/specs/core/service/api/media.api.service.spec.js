import { Application, State } from 'src/core/shopware';
import { itAsync, beforeEachAsync } from '../../../../async-helper';

describe('core/service/api/mediaService', () => {
    let mediaService;
    let mediaStore;

    beforeEachAsync((done) => {
        mediaService = Application.getContainer('service').mediaService;
        mediaStore = State.getStore('media');
        done();
    }, 10000);

    itAsync('should save file from url', (done) => {
        const mediaItem = mediaStore.create();
        mediaItem.name = 'testItem';

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
