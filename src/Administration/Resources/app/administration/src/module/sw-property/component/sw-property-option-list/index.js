import template from './sw-property-option-list.html.twig';
import './sw-property-option-list.scss';

const { Component, State } = Shopware;

Component.register('sw-property-option-list', {
    template,

    inject: [
        'repositoryFactory'
    ],

    props: {
        propertyGroup: {
            type: Object,
            required: true
        },
        optionRepository: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            isLoading: false,
            currentOption: null,
            term: null,
            naturalSorting: true,
            selection: null,
            deleteButtonDisabled: true,
            sortBy: 'name',
            sortDirection: 'ASC',
            showDeleteModal: false,
            limit: 10
        };
    },

    computed: {
        isSystemLanguage() {
            return State.get('context').api.systemLanguageId === this.currentLanguage;
        },

        currentLanguage() {
            return State.get('context').api.languageId;
        }
    },

    watch: {
        currentLanguage() {
            this.refreshOptionList();
        }
    },

    methods: {
        onSearch() {
            this.propertyGroup.options.criteria.setTerm(this.term);
            this.refreshOptionList();
        },

        onGridSelectionChanged(selection, selectionCount) {
            this.selection = selection;
            this.deleteButtonDisabled = selectionCount <= 0;
        },

        onOptionDelete(option) {
            if (option.isNew()) {
                this.propertyGroup.options.remove(option.id);
                return Promise.resolve();
            }
            return this.optionRepository.delete(option.id, Shopware.Context.api);
        },

        onDeleteOptions() {
            if (this.selection) {
                Object.values(this.selection).forEach((option) => {
                    this.onOptionDelete(option);
                });
                this.refreshOptionList();
            }
        },

        onAddOption() {
            if (!this.isSystemLanguage) {
                return false;
            }
            this.currentOption = this.optionRepository.create();

            return true;
        },

        onCancelOption() {
            this.currentOption = null;
        },

        onSaveOption() {
            if (this.propertyGroup.isNew()) {
                return this.saveGroupLocal();
            }

            return this.saveGroupRemote();
        },

        saveGroupLocal() {
            if (this.currentOption.isNew()) {
                if (!this.propertyGroup.options.has(this.currentOption.id)) {
                    this.propertyGroup.options.add(this.currentOption);
                }
                this.currentOption = null;
            }
            return Promise.resolve();
        },

        saveGroupRemote() {
            return this.optionRepository.save(this.currentOption, Shopware.Context.api).then(() => {
                this.currentOption = null;
                this.$refs.grid.load();
            });
        },

        refreshOptionList() {
            this.isLoading = true;

            this.$refs.grid.load().then(() => {
                this.isLoading = false;
            });
        },

        onOptionEdit(option) {
            const localCopy = this.optionRepository.create(Shopware.Context.api, option.id);
            Object.assign(localCopy, option);
            localCopy._isNew = false;

            this.currentOption = localCopy;
        },

        getGroupColumns() {
            return [{
                property: 'name',
                label: this.$tc('sw-property.detail.labelOptionName'),
                routerLink: 'sw.property.detail',
                inlineEdit: 'string',
                primary: true
            }, {
                property: 'colorHexCode',
                label: this.$tc('sw-property.detail.labelOptionColor')
            }, {
                property: 'position',
                label: this.$tc('sw-property.detail.labelOptionPosition')
            }];
        }
    }
});
