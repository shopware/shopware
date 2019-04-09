import { Component, Mixin } from 'src/core/shopware';
import template from './sw-property-option-list.html.twig';
import './sw-property-option-list.scss';

Component.register('sw-property-option-list', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            limit: 10,
            options: [],
            isLoading: false,
            currentOption: null,
            searchTerm: null,
            sortedOptions: [],
            deleteButtonDisabled: true,
            disableRouteParams: true,
            sortings: []
        };
    },

    props: {
        group: {
            type: Object,
            required: true
        }
    },

    computed: {
        optionStore() {
            return this.group.getAssociation('options');
        }
    },

    methods: {
        setSorting() {
            if (this.group.sortingType === 'alphanumeric') {
                this.sortings = [{
                    field: 'property_group_option.name',
                    order: 'ASC',
                    naturalSorting: false
                }];
            } else if (this.group.sortingType === 'numeric') {
                this.sortings = [{
                    field: 'property_group_option.name',
                    order: 'ASC',
                    naturalSorting: true
                }];
            } else if (this.group.sortingType === 'position') {
                this.sortings = [{
                    field: 'property_group_option.position',
                    order: 'ASC',
                    naturalSorting: false
                }];
            }
        },

        onSearch(value) {
            if (!this.hasExistingOptions()) {
                this.term = '';
                return;
            }

            this.term = value;

            this.page = 1;
            this.getList();
        },

        hasExistingOptions() {
            const optionCount = Object.values(this.optionStore.store).filter((item) => {
                return !item.isLocal;
            });

            return optionCount.length > 0;
        },

        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            if (this.sortings.length <= 0) {
                this.setSorting();
            }
            params.sortings = this.sortings;

            this.options = [];
            return this.optionStore.getList(params, true).then((response) => {
                this.total = response.total;
                this.options = response.items;
                this.isLoading = false;

                this.buildGridArray();

                return this.options;
            });
        },

        selectionChanged() {
            const selection = this.$refs.grid.getSelection();
            this.deleteButtonDisabled = Object.keys(selection).length <= 0;
        },

        newItems() {
            const items = [];
            this.optionStore.forEach((item) => {
                if (item.isLocal) {
                    items.push(item);
                }
            });
            return items;
        },

        onOptionDelete(option) {
            option.delete();

            if (option.isLocal) {
                this.optionStore.removeById(option.id);

                this.options.forEach((item, index) => {
                    if (item.id === option.id) {
                        this.options.splice(index, 1);
                    }
                });

                this.buildGridArray();
            }
        },

        onDeleteOptions() {
            const selection = this.$refs.grid.getSelection();

            Object.values(selection).forEach((option) => {
                this.onOptionDelete(option);
                this.$refs.grid.selectItem(false, option);
            });
        },

        onAddOption() {
            const option = this.optionStore.create();
            this.optionStore.removeById(option.id);
            this.onOptionEdit(option);
        },

        onCancelOption() {
            this.currentOption = null;
        },

        onSaveOption() {
            if (!this.optionStore.hasId(this.currentOption.id)) {
                this.optionStore.add(this.currentOption);
                this.buildGridArray();
            }

            this.currentOption = null;
        },

        onOptionResetDelete(option) {
            option.isDeleted = false;
        },

        onInlineEditCancel(option) {
            option.discardChanges();
        },

        onOptionEdit(option) {
            this.currentOption = option;
        },

        buildGridArray() {
            this.options = this.options.filter((value) => {
                return value.isLocal === false;
            });
            this.options.splice(0, 0, ...this.newItems());
        }
    }
});
