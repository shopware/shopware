import { Component, State } from 'src/core/shopware';
import { fileReader } from 'src/core/service/util.service';
import find from 'lodash/find';
import template from './sw-product-file-upload.html.twig';
import './sw-product-file-upload.less';

Component.register('sw-product-file-upload', {
    template,

    inject: ['mediaService'],

    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        }
    },

    data() {
        return {
            mediaItems: this.product.media,
            uploads: [],
            previews: []
        };
    },

    computed: {
        productMediaStore() {
            return this.product.getAssociation('media');
        },

        mediaStore() {
            return State.getStore('media');
        },

        uploadStore() {
            return State.getStore('upload');
        }
    },

    methods: {
        handleFileUploads() {
            const uploadedFiles = Array.from(this.$refs.fileInput.files);

            this.uploads = uploadedFiles.map((file) => {
                const productMedia = this.createEntities(file);

                const uploadTask = this.uploadStore.addUpload(this.product.id, () => {
                    return fileReader.readAsArrayBuffer(file).then((arrayBuffer) => {
                        return this.mediaService.uploadMediaById(
                            productMedia.media.id,
                            file.type,
                            arrayBuffer,
                            file.name.split('.').pop()
                        );
                    }).catch(() => {
                        // Delete the corresponding media entities when the upload fails
                        this.product.getAssociation('media').getByIdAsync(productMedia.id).then((productMediaEntity) => {
                            if (!productMediaEntity) {
                                return;
                            }

                            if (productMediaEntity.media && productMediaEntity.media.id) {
                                State.getStore('media').getByIdAsync(productMediaEntity.media.id).then((mediaEntity) => {
                                    mediaEntity.delete(true);
                                });
                            }

                            productMediaEntity.delete(true);
                        });
                    });
                });

                return { mediaId: productMedia.mediaId, uploadId: uploadTask.id };
            });
        },

        createEntities(file) {
            const productMedia = this.productMediaStore.create();
            productMedia.isLoading = true;
            productMedia.isCover = this.mediaItems === [];
            productMedia.catalogId = this.product.catalogId;

            if (productMedia.isCover || this.mediaItems.length <= 0) {
                productMedia.position = 0;
            } else {
                productMedia.position = this.mediaItems[this.mediaItems.length - 1].position + 1;
            }

            const mediaEntity = this.mediaStore.create();

            delete mediaEntity.catalog;
            delete mediaEntity.user;
            mediaEntity.catalogId = this.product.catalogId;
            mediaEntity.name = file.name;

            fileReader.readAsDataURL(file).then((dataURL) => {
                this.previews[mediaEntity.id] = dataURL;
                productMedia.isLoading = false;

                this.$forceUpdate();
            });

            productMedia.media = mediaEntity;
            productMedia.mediaId = mediaEntity.id;
            this.product.media.push(productMedia);

            return productMedia;
        },

        getPreviewForMedia(mediaEntity) {
            if (mediaEntity.isLocal) {
                return mediaEntity.id in this.previews ? this.previews[mediaEntity.id] : '';
            }
            return mediaEntity.extensions.links.url;
        },

        addFiles() {
            this.$refs.fileInput.click();
        },

        removeFile(key) {
            const item = find(this.mediaItems, (e) => e.mediaId === key);
            const upload = find(this.uploads, (e) => e.mediaId === key);

            if (upload) {
                this.uploadStore.removeUpload(upload.uploadId);
            }

            this.mediaItems = this.mediaItems.filter((e) => e.mediaId !== key);
            item.delete();
        },

        markMediaAsCover(mediaItem) {
            this.removeIsCoverFlag();
            mediaItem.isCover = true;
        },

        removeIsCoverFlag() {
            this.mediaItems.filter(item => item.isCover === true).forEach((item) => {
                item.isCover = false;
            });
        }
    }
});
