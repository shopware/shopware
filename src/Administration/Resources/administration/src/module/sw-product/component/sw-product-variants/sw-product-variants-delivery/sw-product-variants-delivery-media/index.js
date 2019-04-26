import { Component, State } from 'src/core/shopware';
import template from './sw-product-variants-delivery-media.html.twig';
import './sw-product-variants-delivery-media.scss';

Component.register('sw-product-variants-delivery-media', {
    template,

    props: {
        product: {
            type: Object,
            required: true
        },

        selectedGroups: {
            type: Array,
            required: true
        }
    },

    data() {
        return {
            activeGroup: {},
            isLoading: false
        };
    },

    computed: {
        mediaStore() {
            return State.getStore('media');
        },

        uploadStore() {
            return State.getStore('upload');
        },

        optionColumns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('sw-product.variations.deliveryModal.mediaOptions'),
                    rawData: true
                },
                {
                    property: 'option.media',
                    label: this.$tc('sw-product.variations.deliveryModal.media'),
                    rawData: true
                }
            ];
        },

        activeOptions() {
            return Object.values(this.product.configuratorSettings.items).filter((element) => {
                return !element.isDeleted && element.option.groupId === this.activeGroup.id;
            });
        }
    },

    watch: {
        activeOptions() {
            // TODO: Replace it with prop when the sw-data-grid allows to deactivate the compact mode
            this.$nextTick().then(() => {
                if (this.$refs.variantsMedia) {
                    this.$refs.variantsMedia.compact = false;
                }
            });
        }
    },

    methods: {
        onUploadsAdded({ data }) {
            if (data.length === 0) {
                return;
            }

            const uploadData = data[0];
            const relatedOption = this.activeOptions.find((option) => option.id === uploadData.uploadTag);

            relatedOption.isLoading = true;
            this.mediaStore.sync().then(() => {
                data.forEach((upload) => {
                    relatedOption.mediaId = upload.targetId;
                });
                relatedOption.isLoading = false;
                this.uploadStore.runUploads(relatedOption.id);
            });
        },

        successfulUpload({ targetId }) {
            this.mediaStore.getByIdAsync(targetId).then(() => {
                this.$forceUpdate();
            });
        },

        removeMedia(option) {
            option.mediaId = null;
        },

        setMedia(selection, optionId) {
            const relatedOption = this.activeOptions.find((option) => option.id === optionId);
            relatedOption.mediaId = selection[0].id;
        }

    }
});
