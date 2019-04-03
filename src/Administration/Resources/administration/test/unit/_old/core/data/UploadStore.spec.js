import UploadStore from 'src/core/data/UploadStore';
import utils from 'src/core/service/util.service';

function mediaServiceMock() {
    return {
        uploadMediaById() {
            return Promise.resolve();
        },
        uploadMediaFromUrl() {
            return Promise.resolve();
        }
    };
}

function defaultUpdateData() {
    return {
        targetId: utils.createId(),
        src: new URL('http://some.ressource.domain'),
        fileName: 'file',
        extension: 'ext'
    };
}

/* eslint no-unused-expressions: 0 */
describe('src/core/data/uploadStore.js', () => {
    test('adds listener', () => {
        const mediaService = mediaServiceMock();
        const uploadStore = new UploadStore(mediaService);

        const callback = () => {};
        uploadStore.addListener('test-tag', callback);

        expect(uploadStore.$listeners['test-tag']).not.to.be.empty;
        expect(uploadStore.$listeners['test-tag'][0]).to.be.equals(callback);
    });

    test('removes the correct listener', () => {
        const mediaService = mediaServiceMock();
        const uploadStore = new UploadStore(mediaService);

        const callback = () => {};
        uploadStore.addListener('test-tag', callback);
        uploadStore.addListener('test-tag-2', callback);

        expect(uploadStore.$listeners['test-tag']).to.exist;
        expect(uploadStore.$listeners['test-tag-2']).to.exist;

        uploadStore.removeListener('test-tag');

        expect(uploadStore.$listeners['test-tag']).to.be.empty;
        expect(uploadStore.$listeners['test-tag-2']).to.exist;
    });

    test('removes a given callback from listeners', () => {
        const mediaService = mediaServiceMock();
        const uploadStore = new UploadStore(mediaService);

        const toRemove = () => { return true; };
        const toStay = () => {};

        uploadStore.addListener('test-tag', toRemove);
        uploadStore.addListener('test-tag', toStay);

        uploadStore.removeListener('test-tag', toRemove);

        expect(uploadStore.$listeners['test-tag']).toHaveLength(1);
        expect(uploadStore.$listeners['test-tag'][0]).to.be.equals(toStay);
    });

    test('should save uploads', () => {
        const mediaService = mediaServiceMock();
        const uploadStore = new UploadStore(mediaService);

        const listenerOne = sinon.spy();
        const listenerTwo = sinon.spy();
        uploadStore.addListener('test-tag', listenerOne);
        uploadStore.addListener('test', listenerTwo);

        uploadStore.addUpload('test-tag', defaultUpdateData());


        expect(listenerOne.called).to.be.equals(true);
        expect(listenerTwo.called).to.be.equals(false);
    });

    test('calls default and correct listeners', () => {
        const mediaService = mediaServiceMock();
        const uploadStore = new UploadStore(mediaService);

        const defaultListener = sinon.spy();
        const correctListener = sinon.spy();
        const otherTagsListener = sinon.spy();

        uploadStore.addDefaultListener(defaultListener);
        uploadStore.addListener('test-tag', correctListener);
        uploadStore.addListener('wrong-tag', otherTagsListener);

        uploadStore.addUpload('test-tag', defaultUpdateData());
        expect(defaultListener.withArgs('sw-media-upload-added').calledOnce);
        expect(correctListener.withArgs('sw-media-upload-added').calledOnce);

        uploadStore.runUploads('test-tag');
        expect(defaultListener.withArgs('sw-media-upload-finished').calledOnce);
        expect(correctListener.withArgs('sw-media-upload-finished').calledOnce);

        expect(otherTagsListener.notCalled);
    });

    test('does not stop just because one upload failed', () => {
        const mediaService = mediaServiceMock();

        // simulate that the first upload fails
        function failingUploadFunction() {
            if (!this.called) {
                this.called = true;
                return Promise.reject();
            }

            return Promise.resolve();
        }
        mediaService.uploadMediaFromUrl = failingUploadFunction;

        const uploadStore = new UploadStore(mediaService);

        const defaultListener = sinon.spy();
        uploadStore.addDefaultListener(defaultListener);
        uploadStore.addUploads('test-tag', [
            defaultUpdateData(),
            defaultUpdateData()
        ]);
        uploadStore.runUploads('test-tag');
        expect(defaultListener.calledWith('sw-media-upload-failed').calleOnce);
        expect(defaultListener.calledWith('sw-media-upload-finished').calleOnce);
    });
});
