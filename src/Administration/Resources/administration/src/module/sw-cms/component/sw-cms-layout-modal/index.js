import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-cms-layout-modal.html.twig';
import './sw-cms-layout-modal.scss';

Component.register('sw-cms-layout-modal', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            selected: null,
            isLoading: false,
            total: null,
            pages: []
        };
    },

    computed: {
        pageStore() {
            return State.getStore('cms_page');
        }
    },

    created() {
        this.componentCreated();
    },

    methods: {
        componentCreated() {
            this.getList();
        },

        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            params.term = this.term;

            if (this.criteria) {
                params.criteria = this.criteria;
            }

            return this.pageStore.getList(params).then((response) => {
                this.total = response.total;
                this.pages = response.items;
                this.isLoading = false;
                return this.pages;
            });
        },

        selectLayout() {
            this.$emit('modal-layout-select', this.selected);
            this.closeModal();
        },

        selectItem(layout) {
            this.selected = layout;
        },

        onSearch(value) {
            this.term = value;
            this.getList();
        },

        onSelection(selection) {
            this.selected = selection;
        },

        closeModal() {
            this.$emit('modal-close');
            this.selected = null;
            this.term = null;
        }
    }
});
