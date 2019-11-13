import template from './sw-cms-layout-modal.html.twig';
import './sw-cms-layout-modal.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-cms-layout-modal', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            selected: null,
            isLoading: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            term: null,
            total: null,
            pages: []
        };
    },

    computed: {
        pageRepository() {
            return this.repositoryFactory.create('cms_page');
        }
    },

    methods: {
        getList() {
            this.isLoading = true;
            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('previewMedia')
                .addAssociation('sections')
                .addAssociation('categories')
                .addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            if (this.term !== null) {
                criteria.setTerm(this.term);
            }

            return this.pageRepository.search(criteria, Shopware.Context.api).then((searchResult) => {
                this.total = searchResult.total;
                this.pages = searchResult;
                this.isLoading = false;
                return this.pages;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        selectLayout() {
            this.$emit('modal-layout-select', this.selected);
            this.closeModal();
        },

        selectItem(layoutId) {
            this.selected = layoutId;
        },

        onSearch(value) {
            if (!value.length || value.length <= 0) {
                this.term = null;
            } else {
                this.term = value;
            }

            this.page = 1;
            this.getList();
        },

        onSelection(layoutId) {
            this.selected = layoutId;
        },

        closeModal() {
            this.$emit('modal-close');
            this.selected = null;
            this.term = null;
        }
    }
});
