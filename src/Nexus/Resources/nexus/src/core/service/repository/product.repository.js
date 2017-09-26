import ProductServiceFactory from './../api/product/product.service';
import ProxyFactory from './../../factory/data-proxy.factory';

export default function ProductRepository(client) {
    const ProductService = ProductServiceFactory(client);

    return {
        getByUuid,
        updateByUuid,
        getList
    };

    function getByUuid(uuid) {
        return ProductService.readByUuid(uuid).then((response) => {
            return ProxyFactory.create(response.data);
        });
    }

    function updateByUuid(uuid, proxy) {
        if (!uuid || !proxy) {
            return Promise.reject(new Error('Missing required parameters.'));
        }

        // There are no changes
        if (Object.keys(proxy.changeSet).length === 0) {
            return Promise.resolve();
        }

        /**
         * We have to remap the categories at the moment.
         *
         * ToDo: Add category support!
         */
        if (proxy.data.categories) {
            proxy.data.categories.map((entry) => {
                return {
                    categoryUuid: entry
                };
            });
        }

        return ProductService.updateByUuid(uuid, proxy.changeSet).then((response) => {
            proxy.data = response.data;
        });
    }

    function getList(limit, offset) {
        return ProductService.readAll(limit, offset).then((response) => {
            return {
                listProxy: ProxyFactory.create(response.data),
                total: response.total,
                errors: response.errors
            };
        });
    }
}
