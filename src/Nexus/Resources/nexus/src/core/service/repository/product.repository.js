import ProductServiceFactory from './../api/product/product.service';
import ProxyFactory from './../../factory/data-proxy.factory';
import utils from './../util.service';

export default function ProductRepository(client) {
    const ProductService = ProductServiceFactory(client);

    return {
        getNew,
        getByUuid,
        updateByUuid,
        create,
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
            return response.data;
        });
    }

    function create(proxy) {
        return ProductService.create([proxy.data]).then((response) => {
            if (response.errors.length) {
                return Promise.reject(new Error('API error'));
            }

            proxy.data = response.data[0];
            return response.data[0];
        });
    }

    function getNew() {
        const uuid = utils.createUuid();
        const product = {
            uuid: null,
            taxUuid: 'SWAG-TAX-UUID-1',
            mainDetailUuid: uuid,
            manufacturerUuid: 'SWAG-PRODUCT-MANUFACTURER-UUID-1',
            details: [{
                uuid
            }]
        };

        return ProxyFactory.create(product);
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
