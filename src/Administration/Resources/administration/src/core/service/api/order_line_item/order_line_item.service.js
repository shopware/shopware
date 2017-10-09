export default function OrderLineItemService(client) {
    /**
     * Defines the return format for the API. The API can provide the content as JSON (json) or XML (xml).
     * @type {String} [returnFormat=json]
     */
    let returnFormat = 'json';

    return {
        readAll,
        readByUuid,
        updateByUuid,
        getReturnFormat,
        setReturnFormat
    };

    /**
     * @param {String} orderUuid - The order uuid you want the line items from
     * @param {Number} limit - The limit of orders you want to receive
     * @param {Number} offset - Offset of the orders you want to receive
     * @returns {Promise}
     */
    function readAll(orderUuid, limit = 25, offset = 0) {
        const queryString = JSON.stringify({
            type: 'nested',
            queries: [
                {
                    type: 'term',
                    field: 'order_line_item.order_uuid',
                    value: orderUuid
                }
            ]
        });

        const url = `/orderLineItem.${returnFormat}?limit=${limit}&offset=${offset}&query=${queryString}`;
        return client.get(url).then((response) => {
            return response.data;
        });
    }

    /**
     * @param {String} uuid - Order UUID
     * @returns {Promise}
     */
    function readByUuid(uuid) {
        if (!uuid) {
            return Promise.reject(new Error('"uuid" argument needs to be provided'));
        }

        return client.get(`/orderLineItem/${uuid}.${returnFormat}`).then((response) => {
            return response.data;
        });
    }

    /**
     * Updates a single order. Partial updates are supported using the {@param payload}.
     *
     * @param {String} uuid - Order UUID
     * @param {Object} payload - Changeset
     * @returns {Promise}
     */
    function updateByUuid(uuid, payload) {
        if (!uuid) {
            return Promise.reject(new Error('"uuid" argument needs to be provided'));
        }

        return client.patch(`/orderLineItem/${uuid}.json`, payload).then((response) => {
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
