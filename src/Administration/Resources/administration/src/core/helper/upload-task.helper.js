/**
 * This class wraps an upload and stores information about it. For use in core/data/UploadStore
 * @class
 */
export default class UploadTask {
    constructor({ uploadTag, src, targetId, fileName, extension = 'dat' }) {
        this._running = false;
        this._error = null;
        this._uploadTag = uploadTag;
        this._src = src;
        this._targetId = targetId;
        this._fileName = fileName;
        this._extension = extension;
    }

    get uploadTag() {
        return this._uploadTag;
    }

    set uploadTag(value) {
        this._uploadTag = value;
    }

    get src() {
        return this._src;
    }

    set src(value) {
        this._src = value;
    }

    get targetId() {
        return this._targetId;
    }

    set targetId(value) {
        this._targetId = value;
    }

    get fileName() {
        return this._fileName;
    }

    set fileName(value) {
        this._fileName = value;
    }

    get extension() {
        return this._extension;
    }

    set extension(value) {
        this._extension = value;
    }

    get running() {
        return this._running;
    }

    set running(value) {
        this._running = value;
    }

    get error() {
        return this._error;
    }

    set error(value) {
        this._error = value;
    }
}
