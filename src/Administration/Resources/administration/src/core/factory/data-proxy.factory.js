/**
 * @module core/factory/data-proxy
 */
import DataProxy from 'src/core/helper/data-proxy.helper';

export default {
    create: createProxy
};

/**
 * Creates a new instance of the data proxy using the provided data
 *
 * @param {Object} data
 * @returns {DataProxy}
 */
function createProxy(data) {
    return new DataProxy(data);
}
