import UploadStore from 'src/core/data/UploadStore';

describe('src/core/data/uploadStore.js', () => {
    it('should save uploads', () => {
        const uStore = new UploadStore();
        const entityId = 'entityID';
        const fileDummy = {};

        uStore.createUpload(entityId, fileDummy);

        expect(uStore.uploads).to.have.lengthOf(1);
    });

    it('should find an upload by its ID', () => {
        const uStore = new UploadStore();
        const entityId = 'entityID';
        const fileDummy = {};

        uStore.createUpload(entityId, fileDummy);
        const uploadId = uStore.uploads[0].id;

        const foundUpload = uStore.getUploadById(uploadId);

        expect(foundUpload).to.include({ entityId });
    });

    it('should find uploads for an entity', () => {
        const testData = [
            {
                entityId: 'eID1',
                file: { id: 'fileDummy1' }
            },
            {
                entityId: 'eID1',
                file: { id: 'fileDummy2' }
            },
            {
                entityId: 'eID2',
                file: { id: 'fileDummy3' }
            }
        ];
        const uStore = new UploadStore();
        testData.forEach(testItem => { uStore.createUpload(testItem.entityId, testItem.file); });

        const foundUploads = uStore.getUploadsForEntity('eID1');

        expect(foundUploads).to.have.lengthOf(2);
        foundUploads.forEach(upload => { expect(upload).to.not.include({ entityId: 'eID2' }); });
    });

    it('should delete an upload by its ID', () => {
        const uStore = new UploadStore();
        const entityId = 'entityID';
        const fileDummy = {};

        uStore.createUpload(entityId, fileDummy);
        const uploadId = uStore.uploads[0].id;

        uStore.removeUpload(uploadId);

        expect(uStore.uploads).to.be.empty;// eslint-disable-line no-unused-expressions
    });

    it('should delete all uploads for an entity', () => {
        const testData = [
            {
                entityId: 'eID1',
                file: { id: 'fileDummy1' }
            },
            {
                entityId: 'eID1',
                file: { id: 'fileDummy2' }
            },
            {
                entityId: 'eID2',
                file: { id: 'fileDummy3' }
            }
        ];

        const uStore = new UploadStore();
        testData.forEach(testItem => { uStore.createUpload(testItem.entityId, testItem.file); });

        uStore.removeUploadsForEntity('eID1');

        expect(uStore.uploads).to.have.lengthOf(1);
        expect(uStore.uploads[0]).to.include({ entityId: 'eID2' });
    });
});
