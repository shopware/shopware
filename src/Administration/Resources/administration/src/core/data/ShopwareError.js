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
        status = '',
        trace
    } = {}) {
        this.id = utils.createId();
        this.code = code;
        this.title = title;
        this.detail = detail;
        this.parameters = parameters;
        this.status = status;
        this.trace = trace;
    }
}
