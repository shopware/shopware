import template from './sw-property-assignment.html.twig';
import './sw-property-assignment.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-property-assignment', {
    template,

    inject: ['repositoryFactory'],

    props: {
        propertyCollection: {
            type: Array,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            groups: [],
            displayTree: false,
            displaySearch: false,
            isLoading: false,
        };
    },

    computed: {
        groupWithOptions() {
            if (!this.groups) {
                return [];
            }

            return this.groups.reduce((acc, group) => {
                // set options to group
                group.options = this.properties.filter((property) => property.groupId === group.id);

                acc.push(group);
                return acc;
            }, []);
        },

        properties() {
            if (!this.propertyCollection) {
                return [];
            }

            return this.propertyCollection;
        },

        propertyRepository() {
            return this.repositoryFactory.create(
                this.propertyCollection.entity,
                this.propertyCollection.source,
            );
        },

        groupRepository() {
            return this.repositoryFactory.create('property_group');
        },
    },

    watch: {
        propertyCollection: {
            handler() {
                if (this.propertyCollection) {
                    this.groupProperties();
                    this.isLoading = false;
                    this.$emit('options-load');
                }
            },
            immediate: true,
        },
    },

    methods: {
        onSelectOption(selection) {
            const item = selection.item;

            // Check if it should be added or removed
            if (selection.selected === true) {
                // Add property
                this.propertyCollection.add(item);

                // update search field
                this.$refs.searchField.addOptionCount();
                this.$refs.searchField.refreshSelection();
            } else {
                // remove property
                this.propertyCollection.remove(item.id);
            }

            // update view
            this.groupProperties();
        },

        deleteOption(option) {
            this.propertyCollection.remove(option.id);
        },

        groupProperties() {
            // Get Ids
            const groupIds = this.propertyCollection.reduce((acc, property) => {
                if (acc.indexOf(property.groupId) < 0) {
                    acc.push(property.groupId);
                }
                return acc;
            }, []);

            if (groupIds.length <= 0) {
                this.groups = [];
                return false;
            }

            const groupSearchCriteria = new Criteria(1, 500);
            groupSearchCriteria.addFilter(
                Criteria.equalsAny('id', groupIds),
            );

            // Fetch groups with options
            this.groupRepository.search(groupSearchCriteria, Shopware.Context.api).then((res) => {
                this.groups = res;
            });

            return true;
        },
    },
});
