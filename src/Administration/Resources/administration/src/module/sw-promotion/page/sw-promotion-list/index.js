import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-promotion-list.html.twig';
import './sw-promotion-list.scss';

Component.register('sw-promotion-list', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            promotions: [],
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
        promotionStore() {
            return State.getStore('promotion');
        },

        promotionColumns() {
            return this.getPromotionColumns();
        }
    },

    methods: {
        onEdit(promotion) {
            if (promotion && promotion.id) {
                this.$router.push({
                    name: 'sw.promotion.detail',
                    params: {
                        id: promotion.id
                    }
                });
            }
        },

        onInlineEditSave(promotion) {
            promotion.save();
        },

        onInlineEditCancel(promotion) {
            promotion.discardChanges();
        },

        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.promotions = [];

            return this.promotionStore.getList(params).then((response) => {
                this.total = response.total;
                this.promotions = response.items;
                this.isLoading = false;

                return this.promotions;
            });
        },

        onChangeLanguage() {
            this.getList();
        },

        onDeletePromotion(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.promotionStore.getById(id).delete(true).then(() => {
                this.getList();
            });
        },

        getPromotionColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                label: this.$tc('sw-promotion.list.columnName'),
                routerLink: 'sw.promotion.detail',
                inlineEdit: 'string',
                allowResize: true,
                primary: true
            }, {
                property: 'active',
                dataIndex: 'active',
                label: this.$tc('sw-promotion.list.columnActive'),
                inlineEdit: 'boolean',
                allowResize: true,
                align: 'center'
            }, {
                property: 'validFrom',
                dataIndex: 'validFrom',
                label: this.$tc('sw-promotion.list.columnValidFrom'),
                inlineEdit: 'date',
                allowResize: true,
                align: 'center'
            }, {
                property: 'validUntil',
                dataIndex: 'validUntil',
                label: this.$tc('sw-promotion.list.columnValidUntil'),
                inlineEdit: 'date',
                allowResize: true,
                align: 'center'
            }];
        }
    }
});
