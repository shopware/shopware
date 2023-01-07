/*
 * @package inventory
 */

import template from './sw-product-add-properties-modal.html.twig';
import './sw-product-add-properties-modal.scss';

const { Context } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    props: {
        newProperties: {
            type: Array,
            required: true,
        },
        propertiesAvailable: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    data() {
        return {
            properties: [],
            /**
             * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
             */
            propertiesTotal: 0,
            /**
             * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
             */
            isPropertiesLoading: false,
            /**
             * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
             */
            selectedProperty: null,
            /**
             * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
             */
            propertyValues: [],
            /**
             * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
             */
            propertyValuesTotal: 0,
            /**
             * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
             */
            isPropertyValuesLoading: false,
            /**
             * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
             */
            propertiesPage: 1,
            /**
             * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
             */
            propertiesLimit: 10,
            /**
             * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
             */
            propertyValuesPage: 1,
            /**
             * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
             */
            propertyValuesLimit: 10,
            /**
             * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
             */
            isSelectable: true,
            /**
             * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
             */
            searchTerm: null,
        };
    },

    computed: {
        /**
         * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
         */
        propertyGroupRepository() {
            return this.repositoryFactory.create('property_group');
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
         */
        propertyGroupCriteria() {
            const criteria = new Criteria(this.propertiesPage, this.propertiesLimit);

            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            return criteria;
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
         */
        propertyGroupOptionRepository() {
            return this.repositoryFactory.create(
                this.selectedProperty.options.entity,
                this.selectedProperty.options.source,
            );
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
         */
        propertyGroupOptionCriteria() {
            const criteria = new Criteria(this.propertyValuesPage, this.propertyValuesLimit);

            criteria.addSorting(Criteria.sort('name', 'ASC', true));

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            return criteria;
        },

        showSaveButton() {
            return this.propertiesAvailable;
        },
    },

    methods: {
        /**
         * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
         */
        getProperties() {
            this.isPropertiesLoading = true;

            return this.propertyGroupRepository.search(this.propertyGroupCriteria, Context.api)
                .then((properties) => {
                    this.properties = properties;
                    this.propertiesTotal = properties.total;
                })
                .catch(() => {
                    this.properties = [];
                })
                .finally(() => {
                    this.isPropertiesLoading = false;
                });
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
         */
        setSelectedPropertiesCount() {
            this.properties.forEach((property) => {
                const selectedProperties = this.newProperties.filter((newProperty) => {
                    return newProperty.property.groupId === property.id && newProperty.selected === true;
                });

                this.$set(property, 'selectedPropertiesCount', selectedProperties.length);
            });
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
         */
        onSelectProperty(property) {
            this.$refs.propertiesListing.selectAll(false);
            this.$refs.propertiesListing.selectItem(true, property);

            this.selectedProperty = property;
            this.propertyValuesPage = 1;
            this.getPropertyValues();
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
         */
        getPropertyValues() {
            this.isPropertyValuesLoading = true;

            return this.propertyGroupOptionRepository.search(this.propertyGroupOptionCriteria, Context.api)
                .then((propertyValues) => {
                    this.propertyValues = propertyValues;
                    this.propertyValuesTotal = propertyValues.total;
                })
                .then(() => {
                    this.$refs.propertyValuesListing.selectAll(false);

                    this.isSelectable = false;
                    this.newProperties.forEach(({ property, selected }) => {
                        this.$refs.propertyValuesListing.selectItem(selected, property);
                    });
                    this.isSelectable = true;
                })
                .catch(() => {
                    this.propertyValues = [];
                })
                .finally(() => {
                    this.isPropertyValuesLoading = false;
                });
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
         */
        onSelectPropertyValue(assignedPropertyValues, property, selected) {
            if (!this.isSelectable) {
                return;
            }

            const index = this.newProperties.map((newProperty) => {
                return newProperty.property.id;
            }).indexOf(property.id);

            if (index >= 0) {
                this.$emit('update-new-properties-item', { index, selected });
            } else {
                this.$emit('add-new-properties-item', { property, selected });
            }

            this.setSelectedPropertiesCount();
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
         */
        onChangePageProperties({ page, limit }) {
            this.propertiesPage = page;
            this.propertiesLimit = limit;
            this.getProperties();
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
         */
        onChangePagePropertyValues({ page, limit }) {
            this.propertyValuesPage = page;
            this.propertyValuesLimit = limit;
            this.getPropertyValues();
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
         */
        onChangeSearchTerm(searchTerm) {
            if (this.$refs.propertiesListing && this.selectedProperty) {
                this.$refs.propertiesListing.selectItem(false, this.selectedProperty);
                this.selectedProperty = null;
            }

            this.searchTerm = searchTerm;
            if (searchTerm) {
                this.propertiesPage = 1;
            }

            this.getProperties();
        },

        onCancel() {
            this.$emit('modal-cancel');
        },

        onSave() {
            this.$emit('modal-save', this.newProperties);
        },

        onOpenProperties() {
            this.$emit('modal-cancel');

            this.$nextTick(() => {
                this.$router.push({ name: 'sw.property.index' });
            });
        },

        onSelectOption(selection) {
            const item = selection.item;

            if (selection.selected === true) {
                this.newProperties.add(item);
            } else {
                this.newProperties.remove(item.id);
            }
        },
    },
};
