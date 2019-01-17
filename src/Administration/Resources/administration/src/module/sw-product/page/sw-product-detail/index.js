import { Component, Mixin, State } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import template from './sw-product-detail.html.twig';

Component.register('sw-product-detail', {
    template,

    inject: ['mediaService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('product')
    ],

    data() {
        return {
            product: {},
            manufacturers: [],
            currencies: [],
            taxes: []
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

        uploadStore() {
            return State.getStore('upload');
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
                this.product = this.productStore.getById(this.productId);

                this.product.getAssociation('media').getList({
                    page: 1,
                    limit: 50,
                    sortBy: 'position',
                    sortDirection: 'ASC'
                });

                this.product.getAssociation('categories').getList({
                    page: 1,
                    limit: 50
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
            }

            this.$root.$on('sw-product-media-form-open-sidebar', this.openMediaSidebar);
        },

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        },

        onRemoveImageToUpload(item) {
            this.mediaItems = this.mediaItems.filter((e) => { return e.mediaEntity.id !== item.id; });
        },

        onSave() {
            const productName = this.product.name;
            const titleSaveSuccess = this.$tc('sw-product.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-product.detail.messageSaveSuccess', 0, { name: productName });
            const titleSaveError = this.$tc('global.notification.notificationSaveErrorTitle');
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessage', 0, { entityName: productName }
            );

            return this.product.save().then(() => {
                const productId = this.product.id;
                const totalTasks = this.uploadStore.getPendingTaskCount(productId);

                return this.uploadStore.runUploads(productId, (runningTasks) => {
                    const count = totalTasks - runningTasks;
                    this.createNotification({
                        title: titleSaveSuccess,
                        message: this.$tc('sw-product.detail.messageUploadSuccess', 0, { count, total: totalTasks })
                    });
                });
            }).then(() => {
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
            productMedia.isLoading = true;
            productMedia.catalogId = this.product.catalogId;
            productMedia.type = 'product_media';

            if (this.product.media.length === 0) {
                productMedia.position = 0;
                this.product.coverId = productMedia.id;
            } else {
                productMedia.position = this.product.media.length + 1;
            }

            delete mediaItem.catalog;
            delete mediaItem.user;

            productMedia.media = mediaItem;
            productMedia.mediaId = mediaItem.id;
            productMedia.productId = this.product.id;

            productMedia.isLoading = false;
            this.product.media.push(productMedia);
        },

        _checkIfMediaIsAlreadyUsed(mediaId) {
            const index = this.product.media.findIndex((media) => {
                return media.mediaId === mediaId;
            });

            return index > -1;
        }
    }
});
