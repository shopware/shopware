/*
 * @package inventory
 */

import template from './sw-product-variants-delivery-media.html.twig';
import './sw-product-variants-delivery-media.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'mediaService'],

    props: {
        product: {
            type: Object,
            required: true,
        },

        selectedGroups: {
            type: Array,
            required: true,
        },
    },

    data() {
        return {
            activeGroup: {},
            isLoading: false,
        };
    },

    computed: {
        selectedGroupsSorted() {
            // prepare group sorting
            let sortedGroups = [];
            const selectedGroupsCopy = [...this.selectedGroups];

            // check if sorting exists on server
            if (this.product.variantListingConfig.configuratorGroupConfig
                && this.product.variantListingConfig.configuratorGroupConfig.length > 0) {
                // add server sorting to the sortedGroups
                sortedGroups = this.product.variantListingConfig.configuratorGroupConfig.reduce((acc, configGroup) => {
                    const relatedGroup = selectedGroupsCopy.find(group => group.id === configGroup.id);

                    if (relatedGroup) {
                        acc.push(relatedGroup);

                        // remove from original array
                        selectedGroupsCopy.splice(selectedGroupsCopy.indexOf(relatedGroup), 1);
                    }

                    return acc;
                }, []);
            }

            // add non sorted groups at the end of the sorted array
            sortedGroups = [...sortedGroups, ...selectedGroupsCopy];

            return sortedGroups;
        },

        optionColumns() {
            return [
                {
                    property: 'name',
                    label: 'sw-product.variations.deliveryModal.mediaOptions',
                    rawData: true,
                },
                {
                    property: 'option.media',
                    label: 'sw-product.variations.deliveryModal.media',
                    rawData: true,
                },
            ];
        },

        activeOptions() {
            return this.product.configuratorSettings.filter((element) => {
                return !element.isDeleted && element.option.groupId === this.activeGroup.id;
            });
        },
    },

    watch: {
        activeOptions() {
            // TODO: Replace it with prop when the sw-data-grid allows to deactivate the compact mode
            this.$nextTick().then(() => {
                if (this.$refs.variantsMedia) {
                    this.$refs.variantsMedia.compact = false;
                }
            });
        },

        activeGroup: {
            handler() {
                this.product.variantListingConfig.configuratorGroupConfig.find((group) => {
                    return group.id === this.activeGroup.id;
                });
            },
        },
    },

    methods: {
        async onUploadsAdded({ data }) {
            if (data.length === 0) {
                return;
            }

            const uploadData = data[0];
            const relatedOption = this.activeOptions.find((option) => option.id === uploadData.uploadTag);

            this.isLoading = true;

            data.forEach((upload) => {
                relatedOption.mediaId = upload.targetId;
            });

            await this.mediaService.runUploads(uploadData.uploadTag);
        },

        async successfulUpload() {
            this.isLoading = false;

            this.$forceUpdate();
        },

        removeMedia(option) {
            option.mediaId = null;
        },

        setMedia(selection, optionId) {
            const relatedOption = this.activeOptions.find((option) => option.id === optionId);
            relatedOption.mediaId = selection[0].id;
        },

        onChangeGroupListing(value) {
            const existingIndex = this.product.variantListingConfig.configuratorGroupConfig
                .findIndex((group) => group.id === this.activeGroup.id);

            if (existingIndex >= 0) {
                const existingConfig = this.product.variantListingConfig.configuratorGroupConfig[existingIndex];

                this.product.variantListingConfig.configuratorGroupConfig[existingIndex] = {
                    id: existingConfig.id,
                    expressionForListings: value,
                    representation: existingConfig.representation,
                };
            } else {
                this.product.variantListingConfig.configuratorGroupConfig = [
                    ...this.product.variantListingConfig.configuratorGroupConfig, {
                        id: this.activeGroup.id,
                        expressionForListings: value,
                        representation: 'box',
                    }];
            }
        },
    },
};
