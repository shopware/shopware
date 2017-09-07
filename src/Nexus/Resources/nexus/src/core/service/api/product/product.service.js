export default function ProductService(client) {
    return {
        readAll,
        readProductById,
        readProductByOrderNumber,
        updateProductById,
        deleteProductById
    };

    function readProductById(id) {
        let product = {};

        if (!id) {
            return Promise.reject(new Error('"id" argument needs to be provided'));
        }

        return client.get(`/articles/${id}`).then((response) => {
            product = response.data.data;

            return product;
        });
    }

    function readProductByOrderNumber(orderNumber) {
        let product = {};

        if (!orderNumber) {
            return Promise.reject(new Error('"orderNumber" argument needs to be provided'));
        }

        return client.get(`/articles/${orderNumber}?useNumberAsId=true`).then((response) => {
            product = response.data.data;

            return product;
        });
    }

    function readAll(limit = 25, offset = 0) {
        return client.get(`/product.json?limit=${limit}&offset=${offset}`).then((response) => {
            return response.data;
        });
    }

    function updateProductById(id, changes = {}) {
        let changedProduct = {};

        if (!id) {
            return Promise.reject(new Error('"id" argument needs to be provided'));
        }

        return client.put(`/articles/${id}`, changes).then((response) => {
            changedProduct = response.data.data;

            return changedProduct;
        });
    }

    function deleteProductById(id) {
        let success = false;

        if (!id) {
            return Promise.reject(new Error('"id" argument needs to be provided'));
        }

        return client.delete(`/articles/${id}`).then((response) => {
            success = response.data.data.success;

            return success;
        });
    }
}
