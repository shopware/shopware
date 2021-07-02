import template from './sw-product-properties.html.twig';
import './sw-product-properties.scss';

const { Component, Context } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState, mapGetters } = Component.getComponentHelper();

Component.register('sw-product-properties', {
    template,

    inject: ['repositoryFactory', 'acl'],

    data() {
        return {
            groupIds: [],
            properties: [],
            isPropertiesLoading: false,
            searchTerm: null,
            showAddPropertiesModal: false,
            newProperties: [],
        };
    },

    computed: {
        propertyGroupRepository() {
            return this.repositoryFactory.create('property_group');
        },

        propertyGroupCriteria() {
            const criteria = new Criteria(1, 10);

            criteria.addSorting(
                Criteria.sort('name', 'ASC', false),
            );
            criteria.addFilter(
                Criteria.equalsAny('id', this.groupIds),
            );

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            const optionIds = this.productProperties.getIds();

            criteria.getAssociation('options').addFilter(Criteria.equalsAny('id', optionIds));
            criteria.addFilter(Criteria.equalsAny('options.id', optionIds));

            return criteria;
        },

        propertyColumns() {
            return [
                {
                    property: 'name',
                    label: 'sw-product.properties.columnProperty',
                    sortable: false,
                    routerLink: 'sw.property.detail',
                },
                {
                    property: 'values',
                    label: 'sw-product.properties.columnValue',
                    sortable: false,
                },
            ];
        },

        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading',
            'isChild',
        ]),

        productProperties() {
            return this.isChild && this.product?.properties?.length <= 0
                ? this.parentProduct.properties
                : this.product.properties;
        },
    },

    watch: {
        productProperties: {
            immediate: true,
            handler(newValue) {
                if (!newValue) {
                    return;
                }

                this.getGroupIds();
                this.getProperties();
            },
        },
    },

    methods: {
        getGroupIds() {
            if (!this.product?.id) {
                return;
            }

            this.groupIds = this.productProperties.reduce((accumulator, { groupId }) => {
                if (accumulator.indexOf(groupId) < 0) {
                    accumulator.push(groupId);
                }

                return accumulator;
            }, []);
        },

        getProperties() {
            if (!this.product?.id || this.groupIds.length <= 0) {
                this.properties = [];
                return Promise.reject();
            }

            this.isPropertiesLoading = true;
            return this.propertyGroupRepository.search(this.propertyGroupCriteria, Context.api)
                .then((properties) => {
                    this.properties = properties;
                })
                .catch(() => {
                    this.properties = [];
                })
                .finally(() => {
                    this.isPropertiesLoading = false;
                });
        },

        onDeletePropertyValue(propertyValue) {
            this.productProperties.remove(propertyValue.id);
        },

        onDeleteProperty(property) {
            this.$refs.entityListing.deleteId = null;

            this.$nextTick(() => {
                this.productProperties
                    .filter(({ groupId }) => {
                        return groupId === property.id;
                    })
                    .forEach(({ id }) => {
                        this.productProperties.remove(id);
                    });
            });
        },

        onDeleteProperties() {
            this.$refs.entityListing.showBulkDeleteModal = false;

            this.$nextTick(() => {
                const properties = { ...this.$refs.entityListing.selection };

                Object.values(properties).forEach((property) => {
                    property.options.forEach((value) => {
                        this.productProperties.remove(value.id);
                    });
                });
            });
        },

        onChangeSearchTerm(searchTerm) {
            this.searchTerm = searchTerm;
            this.getProperties();
        },

        turnOnAddPropertiesModal() {
            this.updateNewProperties();
            this.showAddPropertiesModal = true;
        },

        turnOffAddPropertiesModal() {
            this.showAddPropertiesModal = false;
            this.updateNewProperties();
        },

        updateNewProperties() {
            this.newProperties = this.productProperties.map((productProperty) => {
                return {
                    property: productProperty,
                    selected: true,
                };
            });
        },

        updateNewPropertiesItem({ index, selected }) {
            this.newProperties[index].selected = selected;
        },

        addNewPropertiesItem({ property, selected }) {
            this.newProperties.push({ property, selected });
        },

        onCancelAddPropertiesModal() {
            this.turnOffAddPropertiesModal();
        },

        onSaveAddPropertiesModal(newProperties) {
            this.turnOffAddPropertiesModal();

            if (newProperties.length <= 0) {
                return;
            }

            newProperties.forEach(({ property, selected }) => {
                if (selected === true && this.productProperties.has(property.id)) {
                    return;
                }

                if (selected === true && !this.productProperties.has(property.id)) {
                    this.productProperties.add(property);
                    return;
                }

                this.productProperties.remove(property.id);
            });
        },
    },
});
