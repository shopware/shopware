import UploadTask from 'src/core/helper/upload-task.helper';
import { fileReader, array } from 'src/core/service/util.service';

const UploadEvents = {
    UPLOAD_ADDED: 'media-upload-add',
    UPLOAD_FINISHED: 'media-upload-finish',
    UPLOAD_FAILED: 'media-upload-fail'
};

/**
 * @module core/data/UploadStore
 * @deprecated tag:v6.4.0
 */
class UploadStore {
    constructor(mediaService) {
        this.uploads = [];
        this.$listeners = {};
        this.mediaService = mediaService;
    }

    /*
     * Event dispatching
     */
    hasListeners(uploadTag) {
        if (!uploadTag) {
            return false;
        }

        return this.$listeners.hasOwnProperty(uploadTag);
    }

    hasDefaultListeners() {
        return this.hasListeners('default');
    }

    addListener(uploadTag, callback) {
        if (!this.hasListeners(uploadTag)) {
            this.$listeners[uploadTag] = [];
        }
        this.$listeners[uploadTag].push(callback);
    }

    removeListener(uploadTag, callback) {
        if (!this.hasListeners(uploadTag)) {
            return;
        }

        if (callback === undefined) {
            array.remove(this.$listeners[uploadTag], () => true);
            return;
        }

        array.remove(this.$listeners[uploadTag], (listener) => {
            return listener === callback;
        });
    }

    removeDefaultListener(callback) {
        this.removeListener('default', callback);
    }

    addDefaultListener(callback) {
        this.addListener('default', callback);
    }

    getListenerForTag(uploadTag) {
        const tagListener = this.hasListeners(uploadTag) ? this.$listeners[uploadTag] : [];
        const defaultListeners = this.hasDefaultListeners() ? this.$listeners.default : [];

        return [...tagListener, ...defaultListeners];
    }

    _createUploadEvent(action, uploadTag, payload) {
        return { action, uploadTag, payload };
    }

    /*
     * store functionality
     */
    addUpload(uploadTag, uploadData) {
        this.addUploads(uploadTag, [uploadData]);
    }

    addUploads(uploadTag, uploadCollection) {
        const tasks = [];
        uploadCollection.forEach((uploadData) => {
            const task = new UploadTask({ uploadTag, ...uploadData });
            tasks.push(task);
            this.uploads.push(task);
        });

        this.getListenerForTag(uploadTag).forEach((listener) => {
            listener(this._createUploadEvent(
                UploadEvents.UPLOAD_ADDED,
                uploadTag,
                { data: tasks }
            ));
        });
    }

    removeByTag(uploadTag) {
        array.remove(this.uploads, (upload) => {
            return upload.uploadTag === uploadTag;
        });
    }

    runUploads(tag) {
        const affectedUploads = array.remove(this.uploads, (upload) => {
            return upload.uploadTag === tag;
        });
        const affectedListeners = this.getListenerForTag(tag);

        if (affectedUploads.length === 0) {
            return Promise.resolve();
        }

        const totalUploads = affectedUploads.length;
        let successUploads = 0;
        let failureUploads = 0;
        return Promise.all(affectedUploads.map((task) => {
            if (task.running) {
                return Promise.resolve();
            }

            task.running = true;
            return this._startUpload(task).then(() => {
                task.running = false;
                successUploads += 1;
                affectedListeners.forEach((listener) => {
                    listener(this._createUploadEvent(
                        UploadEvents.UPLOAD_FINISHED,
                        tag,
                        {
                            targetId: task.targetId,
                            successAmount: successUploads,
                            failureAmount: failureUploads,
                            totalAmount: totalUploads
                        }
                    ));
                });
            }).catch((cause) => {
                task.error = cause;
                task.running = false;
                failureUploads += 1;
                task.successAmount = successUploads;
                task.failureAmount = failureUploads;
                task.totalAmount = totalUploads;
                affectedListeners.forEach((listener) => {
                    listener(this._createUploadEvent(
                        UploadEvents.UPLOAD_FAILED,
                        tag,
                        task
                    ));
                });
            });
        }));
    }

    _startUpload(task) {
        if (task.src instanceof File) {
            return fileReader.readAsArrayBuffer(task.src).then((buffer) => {
                return this.mediaService.uploadMediaById(
                    task.targetId,
                    task.src.type,
                    buffer,
                    task.extension,
                    task.fileName
                );
            });
        }

        if (task.src instanceof URL) {
            return this.mediaService.uploadMediaFromUrl(
                task.targetId,
                task.src.href,
                task.extension,
                task.fileName
            );
        }

        return Promise.reject(new Error('src of upload must either be an instance of File or URL'));
    }
}

export { UploadStore as default, UploadEvents };
