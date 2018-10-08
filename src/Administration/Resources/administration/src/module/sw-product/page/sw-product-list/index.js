import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-product-list.twig';
import './sw-product-list.scss';

Component.register('sw-product-list', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            products: [],
            showDeleteModal: false,
            isLoading: false
        };
    },

    computed: {
        productStore() {
            return State.getStore('product');
        },

        productColumns() {
            return this.getProductColumns();
        },

        filters() {
            return [{
                active: false,
                label: 'Preis über 50€',
                criteria: { type: 'range', field: 'product.price', options: { '>': 50 } }
            }, {
                active: false,
                label: 'Lagerbestand unter 10',
                criteria: { type: 'range', field: 'product.stock', options: { '<': 10 } }
            }];
        }
    },

    filters: {
        stockColorVariant(value) {
            if (value > 25) {
                return 'success';
            }
            if (value < 25 && value > 0) {
                return 'warning';
            }

            return 'error';
        }
    },

    methods: {
        onEdit(product) {
            if (product && product.id) {
                this.$router.push({
                    name: 'sw.product.detail',
                    params: {
                        id: product.id
                    }
                });
            }
        },

        onInlineEditSave(product) {
            product.save();
        },

        onInlineEditCancel(product) {
            product.discardChanges();
        },

        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.products = [];

            return this.productStore.getList(params).then((response) => {
                this.total = response.total;
                this.products = response.items;
                this.isLoading = false;

                return this.products;
            });
        },

        onChangeLanguage() {
            this.getList();
        },

        onDeleteProduct(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.productStore.getById(id).delete(true).then(() => {
                this.getList();
            });
        },

        getProductColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                label: this.$tc('sw-product.list.columnName'),
                routerLink: 'sw.product.detail',
                inlineEdit: 'string',
                allowResize: true,
                primary: true
            }, {
                property: 'manufacturer.name',
                dataIndex: 'manufacturer.name',
                label: this.$tc('sw-product.list.columnManufacturer'),
                allowResize: true
            }, {
                property: 'active',
                dataIndex: 'active',
                label: this.$tc('sw-product.list.columnActive'),
                inlineEdit: 'boolean',
                allowResize: true,
                align: 'center'
            }, {
                property: 'price.gross',
                dataIndex: 'price.gross',
                label: this.$tc('sw-product.list.columnPrice'),
                allowResize: true,
                align: 'right'
            }, {
                property: 'stock',
                dataIndex: 'stock',
                label: this.$tc('sw-product.list.columnInStock'),
                inlineEdit: 'number',
                allowResize: true,
                align: 'right'
            }];
        }
    }
});
