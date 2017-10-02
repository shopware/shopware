import ProxyFactory from './../factory/data-proxy.factory';

export default {

    getData() {
        return {
            limit: 25,
            total: 0,
            page: 1
        };
    },

    methods: {
        initProductList,
        getProductList,
        getList
    }
};

function initProductList(dataKey = 'productList') {
    this.productListDataKey = dataKey;
    this[dataKey] = [];

    this.getProductList();
}

function getProductList() {
    return this.getList(this.limit, this.offset).then((listData) => {
        this.productListProxy = listData.listProxy;
        this[this.productListDataKey] = listData.listProxy.data;
        this.total = listData.total;
        return listData;
    });
}

function getList(limit, offset) {
    return this.productService.getList(limit, offset).then((response) => {
        return {
            listProxy: ProxyFactory.create(response.data),
            total: response.total,
            errors: response.errors
        };
    });
}
