import template from './sw-tag-field.html.twig';
import './sw-tag-field.scss';

const { Component, StateDeprecated } = Shopware;
const { CriteriaFactory } = Shopware.DataDeprecated;

/**
 * @public
 * @status deprecated 6.1
 * @example-type code-only
 */
Component.register('sw-tag-field', {
    template,

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

        showLabel: {
            type: Boolean,
            required: false,
            default: true
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
        tagStore() {
            return StateDeprecated.getStore('tag');
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
