import { Component } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-show-case-list.html.twig';

Component.register('sw-show-case-list', {
    template,
    inject: ['repositoryFactory', 'context'],

    data() {
        return {
            repository: null
        };
    },

    computed: {
        columns() {
            return this.getColumns();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.repository = this.repositoryFactory.create('product', '/product');

            return this.repository.search(new Criteria(), this.context);
        },

        getColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                label: this.$tc('sw-product.list.columnName'),
                routerLink: 'sw.show.case.detail',
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
