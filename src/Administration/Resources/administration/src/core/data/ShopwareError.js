/**
 * @module core/data/ShopwareError
 */
import utils from 'src/core/service/util.service';

/**
 * @class
 * @description Simple data structure to hold information about Api Errors.
 * @memberOf module:core/data/ShopwareError
 */
export default class ShopwareError {
    constructor({
        code = 0,
        title = '',
        detail = '',
        parameters = {},
        status = ''
    } = {}) {
        this._id = utils.createId();
        this._code = code;
        this._title = title;
        this._detail = detail;
        this._parameters = parameters;
        this._status = status;
    }

    get id() {
        return this._id;
    }

    get code() {
        return this._code;
    }

    set code(value) {
        this._code = value;
    }

    get title() {
        return this._title;
    }

    set title(value) {
        this._title = value;
    }

    get detail() {
        return this._detail;
    }

    set detail(value) {
        this._detail = value;
    }

    get parameters() {
        return this._parameters;
    }

    set parameters(value) {
        this._parameters = value;
    }

    get status() {
        return this._status;
    }

    set status(value) {
        this._status = value;
    }

    get trace() {
        return this._trace;
    }

    set trace(value) {
        this._trace = value;
    }
}
