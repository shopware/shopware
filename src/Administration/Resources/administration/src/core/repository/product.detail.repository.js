import ProxyFactory from './../factory/data-proxy.factory';
import utils from './../service/util.service';

export default {

    inject: ['productService'],

    computed: {
        productMainDetails() {
            const product = this[this.productDataKey];

            if (!product || product.details.length <= 0) {
                return {};
            }

            return product.details.find((item) => {
                return item.isMain === true;
            });
        }
    },

    methods: {
        initProduct,
        saveProduct,
        getProductByUuid,
        updateProductByUuid,
        createProduct,
        getDefaultProduct,
        getNewProduct,
        getProductMainDetails,
        addProductPrice
    }
};

function initProduct(uuid, dataKey = 'product') {
    this.productDataKey = dataKey;
    this[dataKey] = this.getDefaultProduct();

    if (!uuid) {
        const productProxy = this.getNewProduct();

        this.productProxy = productProxy;
        this[dataKey] = productProxy.data;
    } else {
        this.getProductByUuid(uuid).then((productProxy) => {
            this.productProxy = productProxy;
            this[dataKey] = productProxy.data;
        });
    }
}

function saveProduct() {
    const uuid = this.productProxy.data.uuid;

    if (!uuid) {
        return this.createProduct(this.productProxy).then((data) => {
            this.productProxy.data = data;
            return data;
        }).catch();
    }

    return this.updateProductByUuid(uuid, this.productProxy).then((data) => {
        this.productProxy.data = data;
        return data;
    }).catch();
}

function getProductByUuid(uuid) {
    return this.productService.getByUuid(uuid).then((response) => {
        return ProxyFactory.create(response.data);
    });
}

function updateProductByUuid(uuid, proxy) {
    if (!uuid || !proxy) {
        return Promise.reject(new Error('Missing required parameters.'));
    }

    // There are no changes
    if (Object.keys(proxy.changeSet).length === 0) {
        return Promise.reject();
    }

    const changeSet = { ...proxy.changeSet };

    /**
     * We have to remap the categories at the moment.
     *
     * ToDo: Add category support!
     */
    if (changeSet.categories) {
        changeSet.categories = mapCategories(changeSet.categories);
    }

    return this.productService.updateByUuid(uuid, changeSet).then((response) => {
        return response.data;
    });
}

function createProduct(proxy) {
    const data = proxy.data;

    /**
     * We have to remap the categories at the moment.
     *
     * ToDo: Add category support!
     */
    if (data.categories) {
        data.categories = mapCategories(data.categories);
    }

    return this.productService.create([proxy.data]).then((response) => {
        if (response.errors.length) {
            return Promise.reject(new Error('API error'));
        }

        return response.data[0];
    });
}

function getDefaultProduct() {
    return {
        attribute: {},
        mainDetail: {},
        categories: [],
        details: []
    };
}

function getNewProduct() {
    const uuid = utils.createUuid();
    const product = {
        uuid: null,
        taxUuid: 'SWAG-TAX-UUID-1',
        mainDetailUuid: uuid,
        manufacturerUuid: null,
        details: [{
            uuid,
            isMain: true,
            prices: [{
                uuid: null,
                price: 0,
                basePrice: 0,
                pseudoPrice: null,
                quantityStart: 1,
                quantityEnd: null,
                percentage: 0,
                customerGroupUuid: '3294e6f6-372b-415f-ac73-71cbc191548f'
            }]
        }]
    };

    return ProxyFactory.create(product);
}

function getProductMainDetails() {
    if (!this.productProxy || this.productProxy.data.details.length <= 0) {
        return {};
    }

    return this.productProxy.data.details.find((item) => {
        return item.isMain === true;
    });
}

function addProductPrice() {
    const uuid = utils.createUuid();
    const mainDetails = this.getProductMainDetails();

    if (!mainDetails.prices) {
        mainDetails.prices = [];
    }

    mainDetails.prices.push({
        uuid,
        price: 0,
        basePrice: 0,
        pseudoPrice: null,
        quantityStart: 1,
        quantityEnd: null,
        percentage: null,
        customerGroupUuid: '3294e6f6-372b-415f-ac73-71cbc191548f'
    });
}

function mapCategories(categories) {
    const mappedCategories = [];

    categories.forEach((entry) => {
        mappedCategories.push({
            categoryUuid: entry.uuid
        });
    });

    return mappedCategories;
}
