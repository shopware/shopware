import utils from 'src/core/service/util.service';

/**
 * This class wraps an upload and stores information about it. For use in core/data/UploadStore
 * @class
 */
class UploadTask {
    constructor(uploadFunction) {
        this.id = utils.createId();

        this.running = false;
        this.resolved = false;

        this.uploadFunction = uploadFunction;
    }

    start() {
        if (this.running || this.resolved) {
            return Promise.resolve({});
        }

        this.running = true;

        return Promise.resolve(this.uploadFunction()).then(() => {
            this.markAsResolved();
        }).catch(() => {
            this.markAsResolved();
        });
    }

    markAsResolved() {
        this.running = false;
        this.resolved = true;
    }
}

export default UploadTask;
