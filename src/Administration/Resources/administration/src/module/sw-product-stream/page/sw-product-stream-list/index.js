import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-product-stream-list.twig';
import './sw-product-stream-list.scss';

Component.register('sw-product-stream-list', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            productStreams: [],
            showDeleteModal: false,
            isLoading: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        productStreamStore() {
            return State.getStore('product_stream');
        },
        filters() {
            return [{
                active: false,
                label: this.$tc('sw-product-stream.filter.valid'),
                criteria: { type: 'equals', field: 'invalid', value: false }
            }];
        }
    },

    methods: {
        onEdit(productStream) {
            if (productStream && productStream.id) {
                this.$router.push({
                    name: 'sw.product.stream.detail',
                    params: {
                        id: productStream.id
                    }
                });
            }
        },

        onInlineEditSave(productStream) {
            this.isLoading = true;

            productStream.save().then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onInlineEditCancel(productStream) {
            productStream.discardChanges();
        },

        onChangeLanguage() {
            this.getList();
        },

        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.productStreams = [];

            return this.productStreamStore.getList(params).then((response) => {
                this.total = response.total;
                this.productStreams = response.items;
                this.isLoading = false;

                return this.productStreams;
            });
        },

        onDeleteProductStream(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.productStreamStore.getById(id).delete(true).then(() => {
                this.getList();
            });
        },

        onDuplicate(id) {
            this.productStreamStore.apiService.clone(id).then((productStream) => {
                this.$router.push(
                    {
                        name: 'sw.product.stream.detail',
                        params: { id: productStream.id }
                    }
                );
            });
        }
    }
});
