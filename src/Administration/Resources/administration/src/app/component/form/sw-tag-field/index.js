import { Component, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-tag-field.html.twig';
import './sw-tag-field.scss';

Component.register('sw-tag-field', {
    template,

    props: {
        entity: {
            type: Object,
            required: true
        }
    },

    computed: {
        tagStore() {
            return State.getStore('tag');
        },

        associationStore() {
            return this.entity.getAssociation('tags');
        }
    },

    data() {
        return {
            term: '',
            recentlyAdded: false,
            tagExists: false
        };
    },

    methods: {
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

            const newTag = this.tagStore.create();
            newTag.name = term;

            newTag.save().then(() => {
                swSelect.addSelection({ item: newTag });
            });

            this.recentlyAdded = true;
        },

        onArrowDown(currentIndex) {
            if (currentIndex === 0 && this.$refs.swTagSelect.results.length === 0) {
                this.$refs.swTagSelect.navigateUpResults();
            }
        },

        checkTagExists(term) {
            if (term.trim().length === 0) {
                this.tagExists = true;
                return;
            }

            const criteria = CriteriaFactory.equals('name', term);
            this.tagStore.getList({ page: 1, limit: 1, criteria }).then((response) => {
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
            this.$emit('input', selected);
        }
    }
});
