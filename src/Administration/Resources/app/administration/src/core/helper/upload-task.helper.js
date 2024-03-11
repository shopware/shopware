/**
 * @package admin
 *
 * This class wraps an upload and stores information about it. For use in core/data/UploadStore
 * @class
 * @private
 */
export default class UploadTask {
    constructor({ uploadTag, src, targetId, fileName, extension = 'dat', isPrivate = false }) {
        this.running = false;
        this.src = src;
        this.uploadTag = uploadTag;
        this.targetId = targetId;
        this.fileName = fileName;
        this.extension = extension;
        this.error = null;
        this.isPrivate = isPrivate;
    }
}
