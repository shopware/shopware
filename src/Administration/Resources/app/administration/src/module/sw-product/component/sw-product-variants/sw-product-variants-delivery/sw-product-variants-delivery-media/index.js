import template from './sw-product-variants-delivery-media.html.twig';
import './sw-product-variants-delivery-media.scss';

const { Component, StateDeprecated } = Shopware;

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
            isActiveGroupInListing: false,
            isLoading: false
        };
    },

    computed: {
        mediaStore() {
            return StateDeprecated.getStore('media');
        },

        uploadStore() {
            return StateDeprecated.getStore('upload');
        },

        selectedGroupsSorted() {
            // prepare group sorting
            let sortedGroups = [];
            const selectedGroupsCopy = [...this.selectedGroups];

            // check if sorting exists on server
            if (this.product.configuratorGroupConfig && this.product.configuratorGroupConfig.length > 0) {
                // add server sorting to the sortedGroups
                sortedGroups = this.product.configuratorGroupConfig.reduce((acc, configGroup) => {
                    const relatedGroup = selectedGroupsCopy.find(group => group.id === configGroup.id);

                    if (relatedGroup) {
                        acc.push(relatedGroup);

                        // remove from orignal array
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
                    rawData: true
                },
                {
                    property: 'option.media',
                    label: 'sw-product.variations.deliveryModal.media',
                    rawData: true
                }
            ];
        },

        activeOptions() {
            return this.product.configuratorSettings.filter((element) => {
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
        },

        activeGroup: {
            handler() {
                if (!this.product.configuratorGroupConfig) {
                    return;
                }

                const activeGroupConfig = this.product.configuratorGroupConfig.find((group) => {
                    return group.id === this.activeGroup.id;
                });

                this.isActiveGroupInListing = activeGroupConfig ? activeGroupConfig.expressionForListings : false;
            }
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
        },

        onChangeGroupListing(value) {
            let configuratorGroupConfig = this.product.configuratorGroupConfig;

            if (!configuratorGroupConfig) {
                configuratorGroupConfig = [];
            }

            const existingIndex = configuratorGroupConfig.findIndex((group) => group.id === this.activeGroup.id);

            if (existingIndex >= 0) {
                const existingConfig = this.product.configuratorGroupConfig[existingIndex];

                this.product.configuratorGroupConfig[existingIndex] = {
                    id: existingConfig.id,
                    expressionForListings: value,
                    representation: existingConfig.representation
                };
            } else {
                this.product.configuratorGroupConfig = [...configuratorGroupConfig, {
                    id: this.activeGroup.id,
                    expressionForListings: value,
                    representation: 'box'
                }];
            }

            this.isActiveGroupInListing = value;
        }

    }
});
