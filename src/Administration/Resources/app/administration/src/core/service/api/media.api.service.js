import { fileReader, array } from 'src/core/service/util.service';
import UploadTask from 'src/core/helper/upload-task.helper';
import ApiService from '../api.service';

const UploadEvents = {
    UPLOAD_ADDED: 'media-upload-add',
    UPLOAD_FINISHED: 'media-upload-finish',
    UPLOAD_FAILED: 'media-upload-fail',
    UPLOAD_CANCELED: 'media-upload-cancel',
};

/**
 * Gateway for the API end point "media"
 * @class
 * @extends ApiService
 */
class MediaApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'media') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'mediaService';
        this.uploads = [];
        this.$listeners = {};
    }

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

    addUpload(uploadTag, uploadData) {
        this.addUploads(uploadTag, [uploadData]);
    }

    addUploads(uploadTag, uploadCollection) {
        const tasks = uploadCollection.map((uploadData) => {
            return new UploadTask({ uploadTag, ...uploadData });
        });

        this.uploads.push(...tasks);

        this.getListenerForTag(uploadTag).forEach((listener) => {
            listener(this._createUploadEvent(
                UploadEvents.UPLOAD_ADDED,
                uploadTag,
                { data: tasks },
            ));
        });
    }

    keepFile(uploadTag, uploadData) {
        const task = new UploadTask({ uploadTag, ...uploadData });
        this.getListenerForTag(uploadTag).forEach((listener) => {
            listener(this._createUploadEvent(
                UploadEvents.UPLOAD_FINISHED,
                uploadTag,
                {
                    targetId: task.targetId,
                    successAmount: 0,
                    failureAmount: 0,
                    totalAmount: 0,
                    customMessage: 'global.sw-media-upload.notification.assigned.message',
                },
            ));
        });
    }

    cancelUpload(uploadTag, uploadData) {
        const tasks = new UploadTask({ uploadTag, ...uploadData });
        this.getListenerForTag(uploadTag).forEach((listener) => {
            listener(this._createUploadEvent(
                UploadEvents.UPLOAD_CANCELED,
                uploadTag,
                { data: tasks },
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
                            totalAmount: totalUploads,
                        },
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
                        task,
                    ));
                });
            });
        }));
    }

    _startUpload(task) {
        if (task.src instanceof File) {
            return fileReader.readAsArrayBuffer(task.src).then((buffer) => {
                return this.uploadMediaById(
                    task.targetId,
                    task.src.type,
                    buffer,
                    task.extension,
                    task.fileName,
                );
            });
        }

        if (task.src instanceof URL) {
            return this.uploadMediaFromUrl(
                task.targetId,
                task.src.href,
                task.extension,
                task.fileName,
            );
        }

        return Promise.reject(new Error('src of upload must either be an instance of File or URL'));
    }

    uploadMediaById(id, mimeType, data, extension, fileName = id) {
        if (mimeType === 'application/json') {
            mimeType = 'text/plain';
        }

        const apiRoute = `/_action/${this.getApiBasePath(id)}/upload`;
        const headers = this.getBasicHeaders({ 'Content-Type': mimeType });
        const params = {
            extension,
            fileName,
        };

        return this.httpClient.post(
            apiRoute,
            data,
            { params, headers },
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    uploadMediaFromUrl(id, url, extension, fileName = id) {
        const apiRoute = `/_action/${this.getApiBasePath(id)}/upload`;
        const headers = this.getBasicHeaders({ 'Content-Type': 'application/json' });
        const params = {
            extension,
            fileName,
        };

        const body = JSON.stringify({ url });

        return this.httpClient.post(
            apiRoute,
            body,
            { params, headers },
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    renameMedia(id, fileName) {
        const apiRoute = `/_action/${this.getApiBasePath(id)}/rename`;
        return this.httpClient.post(
            apiRoute,
            JSON.stringify({
                fileName,
            }),
            {
                params: {},
                headers: this.getBasicHeaders(),
            },
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    provideName(fileName, extension, mediaId = null) {
        const apiRoute = `/_action/${this.getApiBasePath()}/provide-name`;
        return this.httpClient.get(
            apiRoute,
            {
                params: { fileName, extension, mediaId },
                headers: this.getBasicHeaders(),
            },
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export { MediaApiService as default, UploadEvents };
