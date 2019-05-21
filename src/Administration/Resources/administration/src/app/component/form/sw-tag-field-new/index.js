import { Component } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-tag-field-new.html.twig';

Component.register('sw-tag-field-new', {
    template,

    inject: ['repositoryFactory', 'context'],

    props: {
        entity: {
            type: Object,
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

        tagItems() {
            return Object.values(this.entity.items).map(item => item.id);
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
        this.onCreated();
    },

    methods: {
        onCreated() {
            this.updateOptions();
        },

        updateOptions() {
            return new Promise((resolve) => {
                this.tagRepository.search(new Criteria(1, 500), this.context).then((res) => {
                    this.options = Object.values(res.items);
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
            const newTag = this.tagRepository.create(this.entity.context);
            newTag.name = term;

            this.tagRepository.save(newTag, this.entity.context).then(() => {
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
                return;
            }

            // Remove items
            this.tagItems.forEach((tagItemId) => {
                if (selected && selected.indexOf(tagItemId) < 0) {
                    this.entity.remove(tagItemId);
                }
            });

            // Add items
            this.updateOptions().then(() => {
                selected.forEach((selectionId) => {
                    if (!this.entity.has(selectionId)) {
                        const newTag = this.options.find((option) => option.id === selectionId);
                        this.entity.add(newTag, this.entity.context);
                    }
                });
            });

            this.$emit('input', selected);
        }
    }
});
