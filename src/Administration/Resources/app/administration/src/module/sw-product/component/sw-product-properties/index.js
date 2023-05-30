/*
 * @package inventory
 */

import template from './sw-product-properties.html.twig';
import './sw-product-properties.scss';

const { Component, Context } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;
const { mapState, mapGetters } = Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
        isAssociation: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
        showInheritanceSwitcher: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    data() {
        return {
            groupIds: [],
            properties: [],
            isPropertiesLoading: false,
            searchTerm: null,
            showAddPropertiesModal: false,
            newProperties: [],
            propertiesAvailable: true,
        };
    },

    computed: {
        propertyGroupRepository() {
            return this.repositoryFactory.create('property_group');
        },


        propertyOptionRepository() {
            return this.repositoryFactory.create('property_group_option');
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

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.checkIfPropertiesExists();
        },

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
                return Promise.resolve();
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

                this.$refs.entityListing.resetSelection();
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

                this.$refs.entityListing.resetSelection();
            });
        },

        onChangeSearchTerm(searchTerm) {
            this.searchTerm = searchTerm;
            return this.getProperties();
        },

        turnOnAddPropertiesModal() {
            if (!this.propertiesAvailable) {
                this.$router.push({ name: 'sw.property.index' });
            } else {
                this.updateNewProperties();
                this.showAddPropertiesModal = true;
            }
        },

        turnOffAddPropertiesModal() {
            this.showAddPropertiesModal = false;
            this.updateNewProperties();
        },

        updateNewProperties() {
            this.newProperties = new EntityCollection(
                this.productProperties.source,
                this.productProperties.entity,
                this.productProperties.context,
                Criteria.fromCriteria(this.productProperties.criteria),
                this.productProperties,
                this.productProperties.total,
                this.productProperties.aggregations,
            );
        },

        onCancelAddPropertiesModal() {
            this.turnOffAddPropertiesModal();
        },

        onSaveAddPropertiesModal(newProperties) {
            this.turnOffAddPropertiesModal();

            if (newProperties.length <= 0) {
                return;
            }

            this.productProperties.splice(0, this.productProperties.length, ...newProperties);
        },

        checkIfPropertiesExists() {
            this.propertyOptionRepository.search(new Criteria(1, 1)).then((res) => {
                this.propertiesAvailable = res.total > 0;
            });
        },
    },
};
