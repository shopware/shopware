import ProxyFactory from './../factory/data-proxy.factory';

export default {

    inject: ['productService'],

    getData() {
        return {
            limit: 25,
            total: 0,
            page: 1
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

    this.getProductList();
}

function getProductList() {
    return this.productService.getList(this.limit, this.offset).then((response) => {
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
