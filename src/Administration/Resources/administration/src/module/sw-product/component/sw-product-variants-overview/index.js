import { Component, State, Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-product-variants-overview.html.twig';
import './sw-products-variants-overview.scss';

Component.register('sw-product-variants-overview', {
    template,

    mixins: [
        Mixin.getByName('notification'),
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

        variantColumns() {
            return this.getVariantColumns();
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
            params.associations = {
                variations: {
                    sort: [
                        { field: 'groupId' },
                        { field: 'id' }
                    ]
                }
            };

            if (queries.length > 0) {
                params.queries = queries;
            }

            params.sortings = [
                { field: 'product.variations.groupId' },
                { field: 'product.variations.id' }
            ];

            delete params.term;
            this.variantStore.getList(params).then((res) => {
                this.total = res.total;
                this.variantList = res.items;
                this.isLoading = false;
                this.$emit('variantListUpdated', this.variantList);
            });
        },

        buildQueries(input) {
            if (input === undefined) {
                return [];
            }
            const terms = input.split(' ');
            const queries = [];

            terms.forEach((term) => {
                // todo: add queries for the other fields

                queries.push({
                    query: {
                        type: 'equals',
                        field: 'product.variations.name',
                        value: term
                    },
                    score: 3500
                });

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

        getVariantColumns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('sw-product.variations.generatedListColumnVariation'),
                    allowResize: true
                },
                {
                    property: 'price',
                    dataIndex: 'price.gross',
                    label: this.$tc('sw-product.variations.generatedListColumnPrice'),
                    allowResize: true,
                    inlineEdit: 'number'
                },
                {
                    property: 'stock',
                    dataIndex: 'stock',
                    label: this.$tc('sw-product.variations.generatedListColumnStock'),
                    allowResize: true,
                    inlineEdit: 'number'
                },
                {
                    property: 'product-number',
                    label: this.$tc('sw-product.variations.generatedListColumnProductNumber'),
                    allowResize: true
                }
            ];
        },

        onOptionDelete(item) {
            this.showDeleteModal = item.id;
        },

        onOptionResetDelete(item) {
            item.isDeleted = false;
        },

        onInlineEditSave(variation) {
            return variation.save().then(() => {
                this.getList().then(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('sw-product.variations.generatedListTitleSaveSuccess'),
                        message: this.$tc('sw-product.variations.generatedListMessageSaveSuccess')
                    });
                });
            }).catch(() => {
                variation.discardChanges();
                this.createNotificationError({
                    title: this.$tc('sw-product.variations.generatedListTitleSaveError'),
                    message: this.$tc('sw-product.variations.generatedListMessageSaveError')
                });
            });
        },

        onInlineEditCancel(variation) {
            variation.discardChanges();
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },
        onConfirmDelete(item) {
            this.modalLoading = true;
            this.showDeleteModal = false;

            item.delete(true).then(() => {
                this.modalLoading = false;
                this.createNotificationSuccess({
                    // todo: Change translation
                    title: this.$tc('sw-product.variations.generatedListTitleDeleteError'),
                    message: this.$tc('sw-product.variations.generatedListMessageDeleteSuccess')
                });
                this.getList();
            });
        }
    }
});
