import ProxyFactory from './../factory/data-proxy.factory';

export default {

    inject: ['productService'],

    getData() {
        return {
            offset: 0,
            limit: 25,
            total: 0
        };
    },

    methods: {
        initProductList,
        getProductList
    }
};

function initProductList(dataKey = 'productList') {
    this.productListDataKey = dataKey;
    this[dataKey] = [];

    return this.getProductList(this.offset, this.limit);
}

function getProductList(offset, limit) {
    return this.productService.getList(limit, offset).then((response) => {
        this.productListProxy = ProxyFactory.create(response.data);
        this[this.productListDataKey] = this.productListProxy.data;
        this.total = response.total;
        this.errors = response.errors;

        return {
            productListProxy: this.productListProxy,
            total: response.total,
            errors: response.errors
        };
    });
}
