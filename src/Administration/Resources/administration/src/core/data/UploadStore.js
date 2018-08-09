/**
 * @module core/data/UploadStore
 */
import utils from 'src/core/service/util.service';

class UploadStore {
    constructor() {
        this.uploads = [];
    }

    createUpload(entityId, file) {
        const upload = { entityId, file, id: utils.createId() };
        this.addUpload(upload);
    }

    addUpload(upload) {
        this.uploads.push(upload);
    }

    removeUpload(uploadId) {
        this.uploads = this.uploads.filter(upload => upload.id !== uploadId);
    }

    getUploadById(id) {
        return this.uploads.filter(u => u.id === id)[0];
    }

    getUploadsForEntity(id) {
        return this.uploads.filter(u => u.entityId === id);
    }

    removeUploadsForEntity(id) {
        this.uploads = this.uploads.filter(u => u.entityId !== id);
    }
}

export default UploadStore;
