export default function ProductService(client) {
    return {
        readAll,
        readByUuid,
        updateByUuid
    };

    /**
     * Reads out products from the API end point as a paginated list.
     *
     * @param {Number} limit - The limit of products you want to receive
     * @param {Number} offset - Offset of the products you want to receive
     * @returns {Promise}
     */
    function readAll(limit = 25, offset = 0) {
        return client.get(`/product.json?limit=${limit}&offset=${offset}`).then((response) => {
            return response.data;
        });
    }

    /**
     * Reads out a single product from the API end point.
     *
     * @param {String} uuid - Product UUID
     * @returns {Promise}
     */
    function readByUuid(uuid) {
        if (!uuid) {
            return Promise.reject(new Error('"uuid" argument needs to be provided'));
        }

        return client.get(`/product/${uuid}.json`).then((response) => {
            return response.data;
        });
    }

    /**
     * Updates a single product. Partial updates are supported using the {@param payload}.
     *
     * @param {String} uuid - Product UUID
     * @param {Object} payload - Changeset
     * @returns {Promise}
     */
    function updateByUuid(uuid, payload) {
        if (!uuid) {
            return Promise.reject(new Error('"uuid" argument needs to be provided'));
        }

        return client.put(`/product/${uuid}.json`, payload).then((response) => {
            return response.data;
        });
    }
}
