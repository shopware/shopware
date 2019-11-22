/**
 * This class wraps an upload and stores information about it. For use in core/data/UploadStore
 * @class
 */
export default class UploadTask {
    constructor({ uploadTag, src, targetId, fileName, extension = 'dat' }) {
        this.running = false;
        this.src = src;
        this.uploadTag = uploadTag;
        this.targetId = targetId;
        this.fileName = fileName;
        this.extension = extension;
        this.error = null;
    }
}
