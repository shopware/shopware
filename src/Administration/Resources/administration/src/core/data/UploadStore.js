/**
 * @module core/data/UploadStore
 */
import remove from 'lodash/remove';
import UploadTask from 'src/core/helper/uploadTask.helper';
import { debug, fileReader } from 'src/core/service/util.service';

const UploadEvents = {
    UPLOAD_ADDED: 'sw-media-upload-added',
    UPLOAD_FINISHED: 'sw-media-upload-finished',
    UPLOAD_FAILED: 'sw-media-upload-failed'
};

class UploadStore {
    constructor(mediaService) {
        this.tags = new Map();
        this.uploads = [];
        this.$listeners = new Map();
        this.mediaService = mediaService;
    }

    /*
     * Event dispatching
     */
    addListener(key, callback) {
        if (this.$listeners.has(key)) {
            debug.warn('UploadStore', `Overriding existing listener for key ${key}.`);
        }
        this.$listeners.set(key, callback);
    }

    removeListener(key) {
        this.$listeners.delete(key);
    }

    _notifyListeners(event) {
        this.$listeners.forEach((callback) => {
            callback(event);
        });
    }

    _createUploadEvent(action, uploadTag, payload) {
        return { action, uploadTag, payload };
    }

    /*
     * store functionality
     */
    _isTagMissing(tag) {
        return !this.tags.has(tag);
    }

    addUpload(uploadTag, uploadData) {
        this.addUploads(uploadTag, [uploadData]);
    }

    addUploads(uploadTag, uploadCollection) {
        uploadCollection.forEach((uploadData) => {
            this.uploads.push(new UploadTask({ uploadTag, ...uploadData }));
        });

        this._notifyListeners(this._createUploadEvent(
            UploadEvents.UPLOAD_ADDED,
            uploadTag,
            { data: uploadCollection }
        ));
    }

    removeUpload(id) {
        remove(this.uploads, (upload) => {
            return upload.id === id;
        });
    }

    removeByTag(uploadTag) {
        remove(this.uploads, (upload) => {
            return upload.uploadTag === uploadTag;
        });
    }

    runUploads(tag) {
        const affectedUploads = this.uploads.filter((upload) => {
            return upload.uploadTag === tag;
        });

        if (affectedUploads.length === 0) {
            return Promise.resolve();
        }

        return Promise.all(affectedUploads.map((task) => {
            if (task.running) {
                return Promise.resolve();
            }

            task.running = true;
            return this._startUpload(task).then(() => {
                this.removeUpload(task.id);
                task.running = false;
                this._notifyListeners(this._createUploadEvent(
                    UploadEvents.UPLOAD_FINISHED,
                    tag,
                    { targetId: task.targetId }
                ));
            }).catch((cause) => {
                this.removeUpload(task.id);
                task.error = cause;
                task.running = false;
                this._notifyListeners(this._createUploadEvent(
                    UploadEvents.UPLOAD_FAILED,
                    tag,
                    task
                ));
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
                ).then(() => {
                    this.removeUpload(task.id);
                });
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

        return Promise.reject(new Error('src of upload must either be instaceof File or URL'));
    }
}

export { UploadStore as default, UploadEvents };
