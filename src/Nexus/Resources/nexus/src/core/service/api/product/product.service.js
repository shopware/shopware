export default function ProductService(client) {
    /**
     * Defines the return format for the API. The API can provide the content as JSON (json) or XML (xml).
     * @type {String} [returnFormat=json]
     */
    let returnFormat = 'json';

    return {
        readAll,
        readByUuid,
        updateByUuid,
        create,
        getReturnFormat,
        setReturnFormat
    };

    /**
     * Receives products from the API end point as a paginated list.
     *
     * @param {Number} limit - The limit of products you want to receive
     * @param {Number} offset - Offset of the products you want to receive
     * @returns {Promise}
     */
    function readAll(limit = 25, offset = 0) {
        return client.get(`/product.${returnFormat}?limit=${limit}&offset=${offset}`).then((response) => {
            return response.data;
        });
    }

    /**
     * Receives a single product from the API end point.
     *
     * @param {String} uuid - Product UUID
     * @returns {Promise}
     */
    function readByUuid(uuid) {
        if (!uuid) {
            return Promise.reject(new Error('"uuid" argument needs to be provided'));
        }

        return client.get(`/product/${uuid}.${returnFormat}`).then((response) => {
            return response.data;
        });
    }

    /**
     * Updates a single product. Partial updates are supported using the {@param payload}.
     *
     * @param {String} uuid - Product UUID
     * @param {Object} payload - change set
     * @returns {Promise}
     */
    function updateByUuid(uuid, payload) {
        if (!uuid) {
            return Promise.reject(new Error('"uuid" argument needs to be provided'));
        }

        return client.patch(`/product/${uuid}.json`, payload).then((response) => {
            return response.data;
        });
    }

    /**
     * Creates a new product.
     *
     * @param {Object} payload - the data of the new product
     * @returns {Promise}
     */
    function create(payload) {
        return client.post('/product.json', payload).then((response) => {
            return response.data;
        });
    }

    /**
     * Getter for the return format of the service.
     * @returns {string} [returnFormat]
     */
    function getReturnFormat() {
        return returnFormat;
    }

    /**
     * Setter for the return format of the service.
     * @param {String} newReturnFormat
     * @returns {String}
     */
    function setReturnFormat(newReturnFormat) {
        returnFormat = newReturnFormat;

        return returnFormat;
    }
}
