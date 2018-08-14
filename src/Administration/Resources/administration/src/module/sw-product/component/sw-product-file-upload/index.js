import { Component, State } from 'src/core/shopware';
import { fileReader } from 'src/core/service/util.service';
import template from './sw-product-file-upload.html.twig';
import './sw-product-file-upload.less';

Component.register('sw-product-file-upload', {
    template,

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
            previews: []
        };
    },

    computed: {
        productMediaStore() {
            return this.product.getAssociationStore('media');
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

            uploadedFiles.forEach((file) => {
                const mediaEntity = this.createEntities(file);
                this.uploadStore.createUpload(mediaEntity.media.id, file);
            });
        },

        createEntities(file) {
            const productMedia = this.productMediaStore.create();
            productMedia.isLoading = true;
            productMedia.isCover = this.mediaItems === [];
            productMedia.catalogId = this.product.catalogId;

            productMedia.position = productMedia.isCover ? 0 : this.mediaItems.slice(-1)[0].position + 1;
            const mediaEntity = this.mediaStore.create();
            this.mediaStore.addAddition(mediaEntity);

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
            if (mediaEntity.isNew) {
                return mediaEntity.id in this.previews ? this.previews[mediaEntity.id] : '';
            }
            return mediaEntity.extensions.links.url;
        },

        addFiles() {
            this.$refs.fileInput.click();
        },

        removeFile(key) {
            const item = this.mediaItems.filter(e => e.mediaId === key)[0];
            this.mediaItems = this.mediaItems.filter(e => e.mediaId !== key);
            this.uploadStore.removeUploadsForEntity(item.media.id);
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
