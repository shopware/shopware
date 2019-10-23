import template from './sw-property-option-list.html.twig';
import './sw-property-option-list.scss';

const { Component, Mixin, StateDeprecated } = Shopware;

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
            sortings: [],
            isSystemLanguage: false,
            fieldData: null
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
        },

        languageStore() {
            return StateDeprecated.getStore('language');
        }
    },

    methods: {
        checkSystemLanguage() {
            // check if you are in the system language
            this.isSystemLanguage = this.languageStore.getCurrentId() === this.languageStore.systemLanguageId;
        },

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

            this.checkSystemLanguage();
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
            if (!this.isSystemLanguage) {
                return false;
            }

            const option = this.optionStore.create();
            this.optionStore.removeById(option.id);
            this.onOptionEdit(option);

            return true;
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
            // replace values with the previous values
            const prevOption = this.fieldData;

            option.name = prevOption.name;
            option.colorHexCode = prevOption.colorHexCode;
            option.position = prevOption.position;

            // reset previous option
            this.fieldData = null;
        },

        onInlineEditStart(option) {
            // save current values
            this.fieldData = {
                name: option.name,
                colorHexCode: option.colorHexCode,
                position: option.position
            };
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
