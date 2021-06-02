import template from './sw-product-add-properties-modal.html.twig';
import './sw-product-add-properties-modal.scss';

const { Component, Context } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-product-add-properties-modal', {
    template,

    inject: ['repositoryFactory'],

    props: {
        newProperties: {
            type: Array,
            required: true,
        },
    },

    data() {
        return {
            properties: [],
            propertiesTotal: 0,
            isPropertiesLoading: false,
            selectedProperty: null,
            propertyValues: [],
            propertyValuesTotal: 0,
            isPropertyValuesLoading: false,
            propertiesPage: 1,
            propertiesLimit: 10,
            propertyValuesPage: 1,
            propertyValuesLimit: 10,
            isSelectable: true,
            searchTerm: null,
        };
    },

    computed: {
        propertyGroupRepository() {
            return this.repositoryFactory.create('property_group');
        },

        propertyGroupCriteria() {
            const criteria = new Criteria();

            criteria.setPage(this.propertiesPage);
            criteria.setLimit(this.propertiesLimit);
            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            return criteria;
        },

        propertyGroupOptionRepository() {
            return this.repositoryFactory.create(
                this.selectedProperty.options.entity,
                this.selectedProperty.options.source,
            );
        },

        propertyGroupOptionCriteria() {
            const criteria = new Criteria();

            criteria.setPage(this.propertyValuesPage);
            criteria.setLimit(this.propertyValuesLimit);
            criteria.addSorting(Criteria.sort('name', 'ASC', true));

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getProperties();
        },

        getProperties() {
            this.isPropertiesLoading = true;

            return this.propertyGroupRepository.search(this.propertyGroupCriteria, Context.api)
                .then((properties) => {
                    this.properties = properties;
                    this.propertiesTotal = properties.total;
                })
                .then(() => {
                    this.setSelectedPropertiesCount();
                })
                .catch(() => {
                    this.properties = [];
                })
                .finally(() => {
                    this.isPropertiesLoading = false;
                });
        },

        setSelectedPropertiesCount() {
            this.properties.forEach((property) => {
                const selectedProperties = this.newProperties.filter((newProperty) => {
                    return newProperty.property.groupId === property.id && newProperty.selected === true;
                });

                this.$set(property, 'selectedPropertiesCount', selectedProperties.length);
            });
        },

        onSelectProperty(property) {
            this.$refs.propertiesListing.selectAll(false);
            this.$refs.propertiesListing.selectItem(true, property);

            this.selectedProperty = property;
            this.propertyValuesPage = 1;
            this.getPropertyValues();
        },

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

        onChangePageProperties({ page, limit }) {
            this.propertiesPage = page;
            this.propertiesLimit = limit;
            this.getProperties();
        },

        onChangePagePropertyValues({ page, limit }) {
            this.propertyValuesPage = page;
            this.propertyValuesLimit = limit;
            this.getPropertyValues();
        },

        onChangeSearchTerm(searchTerm) {
            if (this.$refs.propertiesListing && this.selectedProperty) {
                this.$refs.propertiesListing.selectItem(false, this.selectedProperty);
                this.selectedProperty = null;
            }

            this.searchTerm = searchTerm;
            this.getProperties();
        },

        onCancel() {
            this.$emit('modal-cancel');
        },

        onSave() {
            this.$emit('modal-save', this.newProperties);
        },
    },
});
