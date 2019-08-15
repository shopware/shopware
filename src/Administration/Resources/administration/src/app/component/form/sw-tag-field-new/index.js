import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-tag-field-new.html.twig';

const { Component } = Shopware;

Component.register('sw-tag-field-new', {
    template,

    inject: ['repositoryFactory', 'context'],

    props: {
        tagCollection: {
            type: Array,
            required: true
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false
        },

        label: {
            type: String,
            required: false,
            default: ''
        },

        size: {
            type: String,
            required: false,
            default: 'default',
            validValues: ['default', 'small'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['default', 'small'].includes(value);
            }
        }
    },

    computed: {
        tagRepository() {
            return this.repositoryFactory.create('tag');
        },

        selectedTagItemIds() {
            return this.tagCollection.getIds();
        }
    },

    data() {
        return {
            term: '',
            recentlyAdded: false,
            tagExists: false,
            options: []
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateOptions();
        },

        updateOptions() {
            return new Promise((resolve) => {
                this.tagRepository.search(new Criteria(1, 500), this.context).then((searchResult) => {
                    this.options = searchResult;
                    resolve();
                });
            });
        },

        onSearchTermChange(term) {
            this.term = term;
            this.recentlyAdded = false;
            this.tagExists = this.checkTagExists(term);
        },

        onClickAdd(event) {
            this.onEnter(event.item.index);
        },

        onEnter(index) {
            if (index !== -1) {
                return;
            }

            const swSelect = this.$refs.swTagSelect;
            const term = swSelect.searchTerm.trim();
            if (term === '') {
                return;
            }

            // create new tag
            const newTag = this.tagRepository.create(this.tagCollection.context);
            newTag.name = term;

            this.tagRepository.save(newTag, this.tagCollection.context).then(() => {
                swSelect.addItem({ item: newTag });
                swSelect.searchTerm = '';

                this.recentlyAdded = true;
            });
        },

        onArrowDown(currentIndex) {
            if (currentIndex === 0 && this.$refs.swTagSelect.currentOptions.length === 0) {
                this.$refs.swTagSelect.navigateUpResults();
            }
        },

        checkTagExists(term) {
            if (term.trim().length === 0) {
                this.tagExists = true;
                return;
            }

            const criteria = new Criteria();
            criteria.addFilter(
                Criteria.equals('name', term)
            );

            this.tagRepository.search(criteria, this.context).then((response) => {
                this.tagExists = response.total > 0;
            });
        },

        onSearchResultChange(items) {
            if (items.length > 0) {
                return;
            }

            this.$refs.swTagSelect.activeResultPosition = 0;
            this.$refs.swTagSelect.navigateUpResults();
        },

        emitInput(selected) {
            if (!selected) {
                selected = [];
            }

            // Remove items
            this.tagCollection.forEach((tagItem) => {
                if (selected && selected.indexOf(tagItem.id) < 0) {
                    this.tagCollection.remove(tagItem.id);
                }
            });

            // Add items
            this.updateOptions().then(() => {
                selected.forEach((selectionId) => {
                    if (!this.tagCollection.has(selectionId)) {
                        const newTag = this.options.get(selectionId);
                        this.tagCollection.add(newTag);
                    }
                });
                this.$emit('tags-changed');
            });

            this.$emit('input', selected);
        }
    }
});
