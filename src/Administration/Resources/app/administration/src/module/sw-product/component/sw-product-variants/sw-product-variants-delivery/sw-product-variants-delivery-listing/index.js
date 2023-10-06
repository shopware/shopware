/*
 * @package inventory
 */

import template from './sw-product-variants-delivery-listing.html.twig';
import './sw-product-variants-delivery-listing.scss';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

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
            searchTerm: '',
        };
    },

    computed: {
        listingModeOptions() {
            return [
                {
                    value: 'single',
                    name: this.$tc('sw-product.variations.deliveryModal.listingLabelModeSingle'),
                },
                {
                    value: 'expanded',
                    name: this.$tc('sw-product.variations.deliveryModal.listingLabelModeExpanded'),
                },
            ];
        },

        listingMode() {
            return this.mainVariant || this.product.variantListingConfig.displayParent === true
                ? 'single' : 'expanded';
        },

        mainVariantModeOptions() {
            return [
                {
                    value: true,
                    name: this.$tc('sw-product.variations.deliveryModal.listingLabelModeDisplayParent'),
                },
                {
                    value: false,
                    name: this.$tc('sw-product.variations.deliveryModal.listingLabelMainVariant'),
                },
            ];
        },

        mainVariant() {
            return this.product.variantListingConfig.mainVariantId;
        },

        variantCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('product.parentId', this.product.id));
            criteria.addAssociation('options.group');

            if (typeof this.searchTerm === 'string' && this.searchTerm.length > 0) {
                criteria.addQuery(Criteria.contains('product.options.name', this.searchTerm), 500);
            }

            return criteria;
        },

        context() {
            const context = { ...Shopware.Context.api, inheritance: true };

            return context;
        },

        selectedGroupsSorted() {
            // prepare group sorting
            let sortedGroups = [];
            const selectedGroupsCopy = [...this.selectedGroups];

            // check if sorting exists on server
            if (this.product.variantListingConfig.configuratorGroupConfig
                && this.product.variantListingConfig.configuratorGroupConfig.length > 0) {
                // add server sorting to the sortedGroups
                sortedGroups = this.product.variantListingConfig.configuratorGroupConfig
                    .reduce((acc, configGroup) => {
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
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateListingMode(this.listingMode);
        },

        updateListingMode(value) {
            if (value === 'expanded') {
                this.product.variantListingConfig.displayParent = true;
            }

            this.product.listingMode = value;
        },

        updateVariantMode(value) {
            this.product.variantListingConfig.displayParent = value;
        },

        updateMainVariant(value) {
            this.product.variantListingConfig.mainVariantId = value;
        },

        isActiveGroupInListing(groupId) {
            const configuratorGroupConfig = this.product.variantListingConfig?.configuratorGroupConfig || [];

            if (!configuratorGroupConfig.length) {
                return false;
            }

            const activeGroupConfig = this.product.variantListingConfig.configuratorGroupConfig.find((group) => {
                return group.id === groupId;
            });

            return activeGroupConfig ? activeGroupConfig.expressionForListings : false;
        },

        onChangeGroupListing(value, groupId) {
            const configuratorGroupConfig = this.product.variantListingConfig?.configuratorGroupConfig || [];
            const existingGroup = configuratorGroupConfig.find((group) => group.id === groupId);

            if (existingGroup) {
                existingGroup.expressionForListings = value;
                return;
            }

            configuratorGroupConfig.push({
                id: groupId,
                expressionForListings: value,
                representation: 'box',
            });

            this.product.variantListingConfig.configuratorGroupConfig = configuratorGroupConfig;
        },

        isActiveListingMode(mode) {
            return mode === this.product.listingMode;
        },

        isDisabledListingMode(mode) {
            return !this.isActiveListingMode(mode);
        },

        isSelected(item) {
            return this.mainVariant === item.id;
        },

        onSearchTermChange(value) {
            this.searchTerm = value;
        },

        onSelectCollapsed() {
            this.searchTerm = '';
        },
    },
};
