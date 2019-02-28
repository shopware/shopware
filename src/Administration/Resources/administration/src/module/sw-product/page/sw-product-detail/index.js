import { Component, Mixin, State } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-product-detail.html.twig';

Component.register('sw-product-detail', {
    template,

    inject: ['mediaService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('product')
    ],

    data() {
        return {
            product: {},
            manufacturers: [],
            currencies: [],
            taxes: [],
            attributeSets: []
        };
    },

    computed: {
        productStore() {
            return State.getStore('product');
        },

        manufacturerStore() {
            return State.getStore('product_manufacturer');
        },

        currencyStore() {
            return State.getStore('currency');
        },

        productMediaStore() {
            return this.product.getAssociation('media');
        },

        taxStore() {
            return State.getStore('tax');
        },

        attributeSetStore() {
            return State.getStore('attribute_set');
        }
    },

    created() {
        this.createdComponent();
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.productId = this.$route.params.id;
                this.loadEntityData();
            }

            this.$root.$on('sw-product-media-form-open-sidebar', this.openMediaSidebar);
        },

        loadEntityData() {
            this.product = this.productStore.getById(this.productId);

            this.product.getAssociation('media').getList({
                page: 1,
                limit: 50,
                sortBy: 'position',
                sortDirection: 'ASC'
            });

            this.manufacturerStore.getList({ page: 1, limit: 100 }).then((response) => {
                this.manufacturers = response.items;
            });

            this.currencyStore.getList({ page: 1, limit: 100 }).then((response) => {
                this.currencies = response.items;
            });

            this.taxStore.getList({ page: 1, limit: 100 }).then((response) => {
                this.taxes = response.items;
            });

            this.attributeSetStore.getList({
                page: 1,
                limit: 100,
                criteria: CriteriaFactory.equals('relations.entityName', 'product'),
                associations: {
                    attributes: {
                        limit: 100,
                        sort: 'attribute.config.attributePosition'
                    }
                }
            }, true).then((response) => {
                this.attributeSets = response.items;
            });
        },

        abortOnLanguageChange() {
            return this.product.hasChanges();
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        },

        onSave() {
            // todo: add functionality for saving the variant changes
            const productName = this.product.name || this.product.meta.viewData.name;
            const titleSaveSuccess = this.$tc('sw-product.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-product.detail.messageSaveSuccess', 0, { name: productName });
            const titleSaveError = this.$tc('global.notification.notificationSaveErrorTitle');
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessage', 0, { entityName: productName }
            );

            return this.product.save().then(() => {
                this.$refs.mediaSidebarItem.getList();
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            }).catch((exception) => {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });
                warn(this._name, exception.message, exception.response);
                throw exception;
            });
        },

        onAddItemToProduct(mediaItem) {
            if (this._checkIfMediaIsAlreadyUsed(mediaItem.id)) {
                this.createNotificationInfo({
                    message: this.$tc('sw-product.mediaForm.errorMediaItemDuplicated')
                });
                return;
            }
            const productMedia = this.productMediaStore.create();
            productMedia.mediaId = mediaItem.id;
            this.product.media.push(productMedia);
        },

        _checkIfMediaIsAlreadyUsed(mediaId) {
            return this.product.media.some((productMedia) => {
                return productMedia.mediaId === mediaId;
            });
        }
    }
});
