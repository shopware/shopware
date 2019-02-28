import { Component, State, Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import { deepCopyObject } from 'src/core/service/utils/object.utils';
import template from './sw-product-variants-generated-variants.html.twig';
import './sw-products-variants-generated-variants.scss';

Component.register('sw-product-variants-generated-variants', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            variantList: [],
            isLoading: false,
            showDeleteModal: false,
            modalLoading: false,
            priceEdit: false
        };
    },

    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        }
    },

    computed: {
        variantStore() {
            return State.getStore('product');
        },

        fieldPriceClasses() {
            return {
                'sw-product-variants-generated-variants__grid--priceField--edit': this.priceEdit
            };
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getList();
        },

        isPriceEditing(value) {
            this.priceEdit = value;
        },

        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            const queries = this.buildQueries(params.term);

            params.criteria = CriteriaFactory.equals('product.parentId', this.product.id);
            params.associations = { variations: {} };
            if (queries.length > 0) {
                params.queries = queries;
            }
            delete params.term;
            console.log('getList started');
            this.variantStore.getList(params).then((res) => {
                this.total = res.total;
                this.variantList = res.items;
                this.isLoading = false;
                this.$emit('variantListUpdated', this.variantList);
                console.log('getList finished', this.variantList);
            });
        },

        buildQueries(input) {
            if (input === undefined) {
                return [];
            }
            const terms = input.split(' ');
            const queries = [];

            terms.forEach((term) => {
                // queries.push({
                //     query: {
                //         type: 'equals',
                //         field: 'product.productNumber',
                //         value: term
                //     },
                //     score: 5000
                // });
                queries.push({
                    query: {
                        type: 'equals',
                        field: 'product.variations.name',
                        value: term
                    },
                    score: 3500
                });

                // queries.push({
                //     query: {
                //         type: 'contains',
                //         field: 'product.productNumber',
                //         value: term
                //     },
                //     score: 500
                // });

                queries.push({
                    query: {
                        type: 'contains',
                        field: 'product.variations.name',
                        value: term
                    },
                    score: 500
                });
            });

            return queries;
        },

        onSearch() {
            this.getList();
        },

        onOptionDelete(item) {
            this.showDeleteModal = item.id;
        },

        onOptionResetDelete(item) {
            item.isDeleted = false;
        },

        onSubmitPriceNet(value, e, item) {
            this.isLoading = true;
            this.variantList = deepCopyObject(this.variantList);

            item.price.net = value;
            item.save().then(() => {
                this.getList();
            });
        },

        onSubmitPriceGross(value, e, item) {
            this.isLoading = true;
            this.variantList = deepCopyObject(this.variantList);

            item.price.gross = value;
            item.save().then(() => {
                this.getList();
            });
        },

        onSubmitStock(value, e, item) {
            this.isLoading = true;
            this.variantList = deepCopyObject(this.variantList);

            item.stock = parseInt(value, 10);
            item.save().then(() => {
                this.getList();
            });
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },
        onConfirmDelete(item) {
            this.modalLoading = true;
            item.delete(true).then(() => {
                this.showDeleteModal = false;
                this.modalLoading = false;
                this.getList();
            });
        }
    }
});
