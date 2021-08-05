import template from './sw-product-variants-configurator-restrictions.html.twig';
import './sw-product-variants-configurator-restrictions.scss';

const { Component } = Shopware;

Component.register('sw-product-variants-configurator-restrictions', {
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
            term: '',
            restrictionModalIsOpen: false,
            isLoading: true,
            groupsWithOptions: [],
            actualRestriction: {},
        };
    },

    computed: {
        restrictionColumns() {
            return [
                {
                    property: 'id',
                    label: 'sw-product.variations.configuratorModal.restrictedCombinations',
                    rawData: true,
                },
            ];
        },

        actualRestrictionValueLength() {
            return this.actualRestriction.values.length;
        },

        filteredRestrictions() {
            if (this.term.length <= 0) {
                return this.product.variantRestrictions;
            }

            // get restriction names for ids
            const namedRestriction = this.product.variantRestrictions.map((restriction) => {
                return this.getRestrictionsWithNaming(restriction.id);
            });

            // search for matching content
            const matchingIds = namedRestriction.reduce((acc, restriction) => {
                let termMatched = false;

                restriction.values.forEach((value) => {
                    if (value.group.toLowerCase().includes(this.term.toLowerCase())) {
                        termMatched = true;
                    }

                    value.options.find((option) => {
                        if (option.toLowerCase().includes(this.term.toLowerCase())) {
                            termMatched = true;
                            return true;
                        }
                        return false;
                    });
                });

                if (termMatched) {
                    acc.push(restriction.id);
                }
                return acc;
            }, []);

            // return only the restrictions with matching id
            return this.product.variantRestrictions.filter((restriction) => matchingIds.indexOf(restriction.id) >= 0);
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.filterEmptyValues();

            // Load the groups with the related options
            this.groupsWithOptions = this.selectedGroups.map((group) => {
                return {
                    group: group,
                    options: this.getOptionsForGroupId(group.id),
                };
            });

            this.isLoading = false;
        },

        getOptionsForGroupId(groupId) {
            return this.product.configuratorSettings.filter((element) => {
                return !element.isDeleted && element.option.groupId === groupId;
            });
        },

        getRestrictionsWithNaming(restrictionId) {
            // get the restriction from the param
            const cRestriction = this.product.variantRestrictions.find((actRestriction) => {
                return actRestriction.id === restrictionId;
            });

            // return the restriction with names
            return {
                id: cRestriction.id,
                values: cRestriction.values.map((value) => {
                    const actualGroup = this.selectedGroups.find((group) => {
                        return group.id === value.group;
                    });

                    // When no group was found
                    if (!actualGroup) {
                        return {
                            group: '',
                            options: [],
                        };
                    }

                    const optionNames = value.options.reduce((acc, optionId) => {
                        const idOfOption = optionId.optionId ? optionId.optionId : optionId;

                        const actualOption = this.product.configuratorSettings.find((sOption) => {
                            return idOfOption === sOption.optionId;
                        });

                        if (actualOption?.option) {
                            acc.push(actualOption.option.translated.name);
                        }
                        return acc;
                    }, []);

                    return {
                        group: actualGroup.translated.name,
                        options: optionNames,
                    };
                }),
            };
        },

        filterEmptyValues() {
            if (!this.product.variantRestrictions) {
                return false;
            }
            this.product.variantRestrictions = this.product.variantRestrictions.filter((restriction) => {
                restriction.values = restriction.values.filter((value) => {
                    value.options = value.options.filter((option) => {
                        return this.product.configuratorSettings.find((sOption) => {
                            return option === sOption.optionId;
                        });
                    });
                    return value.options.length > 0;
                });
                return restriction.values.length > 0;
            });
            return true;
        },

        addEmptyRestrictionCombination() {
            const uniqueId = String(new Date().valueOf()).split('').reverse().join('');
            this.actualRestriction = {
                id: uniqueId,
                values: [],
            };
            this.addEmptyRestriction();
            this.restrictionModalIsOpen = true;
        },

        addEmptyRestriction() {
            const uniqueId = String(new Date().valueOf()).split('').reverse().join('');
            const firstGroup = this.groupsWithOptions[0].group;
            this.actualRestriction.values.push({
                id: uniqueId,
                group: firstGroup.id,
                options: [],
            });
        },

        cancelAddRestriction() {
            this.actualRestriction = {};
            this.restrictionModalIsOpen = false;
        },

        saveAddRestriction() {
            if (this.product.variantRestrictions === null) {
                this.product.variantRestrictions = [];
            }

            const exists = this.product.variantRestrictions.some((restriction) => {
                return restriction.id === this.actualRestriction.id;
            });

            if (!exists) {
                this.product.variantRestrictions.push(this.actualRestriction);
            }

            this.actualRestriction = {};
            this.restrictionModalIsOpen = false;
        },

        editRestrictionCombination(restriction) {
            this.actualRestriction = restriction;
            this.restrictionModalIsOpen = true;
        },

        deleteRestrictionCombination(deleteRestriction) {
            this.product.variantRestrictions = this.product.variantRestrictions.filter((restriction) => {
                return restriction.id !== deleteRestriction.id;
            });
        },

        deleteRestriction(deleteRestriction) {
            this.actualRestriction.values = this.actualRestriction.values.filter((restriction) => {
                return restriction !== deleteRestriction;
            });
        },
    },
});
