import template from './sw-sales-channel-products-assignment-dynamic-product-groups.html.twig';
import './sw-sales-channel-products-assignment-dynamic-product-groups.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { ShopwareError } = Shopware.Classes;

Component.register('sw-sales-channel-products-assignment-dynamic-product-groups', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        salesChannel: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            productStreamFilter: null,
            productStreamInvalid: false,
            productStreamId: null,
        };
    },

    computed: {
        productStreamRepository() {
            return this.repositoryFactory.create('product_stream');
        },

        productColumns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('sw-sales-channel.detail.products.columnProductName'),
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'productNumber',
                    label: this.$tc('sw-sales-channel.detail.products.columnProductNumber'),
                    allowResize: true,
                },
            ];
        },

        productStreamInvalidError() {
            if (!this.productStreamInvalid) {
                return null;
            }

            return new ShopwareError({
                code: 'PRODUCT_STREAM_INVALID',
                detail: this.$tc('sw-category.base.products.dynamicProductGroupInvalidMessage'),
            });
        },

        productStreamCriteria() {
            if (!this.productStreamFilter) {
                return null;
            }

            const criteria = new Criteria();
            criteria.filters = this.productStreamFilter;
            criteria.addAssociation('visibilities.salesChannel');
            criteria.addFilter(Criteria.not('and', [
                Criteria.equals('product.visibilities.salesChannelId', this.salesChannel.id),
            ]));

            return criteria;
        },
    },

    watch: {
        productStreamId(id) {
            if (!id) {
                this.productStreamFilter = null;
                return;
            }
            this.loadProductStreamPreview();
        },
    },

    methods: {
        loadProductStreamPreview() {
            return this.productStreamRepository.get(this.productStreamId)
                .then(response => {
                    this.productStreamInvalid = response.invalid;
                    this.productStreamFilter = [...response.apiFilter];
                })
                .catch(err => {
                    this.productStreamFilter = null;
                    this.productStreamInvalid = true;

                    this.createNotificationError({
                        message: err.message,
                    });
                });
        },

        onSelectionChange(selection) {
            this.$emit('selection-change', selection, 'groupProducts');
        },
    },
});
