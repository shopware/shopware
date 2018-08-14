import { Component, Mixin, State } from 'src/core/shopware';
import { fileReader } from 'src/core/service/util.service';
import template from './sw-product-detail.html.twig';
import './sw-product-detail.less';

Component.register('sw-product-detail', {
    template,

    inject: ['mediaService'],

    mixins: [
        Mixin.getByName('notification')
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

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.productId = this.$route.params.id;
                this.product = this.productStore.getById(this.productId);

                this.product.getAssociationStore('media').getList({
                    page: 1,
                    limit: 50,
                    sortBy: 'position',
                    sortDirection: 'ASC'
                });

                this.product.getAssociationStore('categories').getList({
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
        },

        onRemoveImageToUpload(item) {
            this.mediaItems = this.mediaItems.filter((e) => { return e.mediaEntity.id !== item.id; });
        },

        onSave() {
            const productName = this.product.name;
            const titleSaveSuccess = this.$tc('sw-product.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-product.detail.messageSaveSuccess', 0, { name: productName });

            this.product.save().then(() => {
                const newProductMediaItems = this.product.media.filter(media => media.isNew);
                let counter = 0;
                const count = newProductMediaItems.length;

                return Promise.all(newProductMediaItems.map((productMedia) => {
                    productMedia.isLoading = true;

                    const upload = this.uploadStore.getUploadsForEntity(productMedia.media.id)[0];
                    const file = upload.file;
                    this.uploadStore.removeUpload(upload.id);

                    return fileReader.readAsArrayBuffer(file).then((arrayBuffer) => {
                        return this.mediaService.uploadMediaById(productMedia.media.id, file.type, arrayBuffer);
                    }).then(() => {
                        counter += 1;
                        productMedia.isLoading = false;

                        this.createNotification({
                            title: titleSaveSuccess,
                            message: `Uploaded ${counter}/${count} images`
                        });
                    }).catch(() => {
                        productMedia.media.delete(true);
                        productMedia.delete(true);

                        this.createNotificationWarning({
                            title: titleSaveSuccess,
                            message: `Failed to upload ${productMedia.media.name}`
                        });
                    });
                }));
            }).then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            });
        }
    }
});
