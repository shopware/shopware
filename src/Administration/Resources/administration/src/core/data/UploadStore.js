/**
 * @module core/data/UploadStore
 */
import remove from 'lodash/remove';
import UploadTask from 'src/core/helper/uploadTask.helper';
import { fileReader } from 'src/core/service/util.service';

const UploadEvents = {
    UPLOAD_ADDED: 'sw-media-upload-added',
    UPLOAD_FINISHED: 'sw-media-upload-finished',
    UPLOAD_FAILED: 'sw-media-upload-failed'
};

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
            remove(this.$listeners[uploadTag], () => true);
            return;
        }

        remove(this.$listeners[uploadTag], (listener) => {
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
        remove(this.uploads, (upload) => {
            return upload.uploadTag === uploadTag;
        });
    }

    runUploads(tag) {
        const affectedUploads = remove(this.uploads, (upload) => {
            return upload.uploadTag === tag;
        });
        const affectedListeners = this.getListenerForTag(tag);

        if (affectedUploads.length === 0) {
            return Promise.resolve();
        }

        return Promise.all(affectedUploads.map((task) => {
            if (task.running) {
                return Promise.resolve();
            }

            task.running = true;
            return this._startUpload(task).then(() => {
                task.running = false;
                affectedListeners.forEach((listener) => {
                    listener(this._createUploadEvent(
                        UploadEvents.UPLOAD_FINISHED,
                        tag,
                        { targetId: task.targetId }
                    ));
                });
            }).catch((cause) => {
                task.error = cause;
                task.running = false;
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
