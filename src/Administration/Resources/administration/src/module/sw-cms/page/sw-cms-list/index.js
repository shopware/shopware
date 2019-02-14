import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-cms-list.html.twig';
import './sw-cms-list.scss';

Component.register('sw-cms-list', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            pages: [],
            isLoading: false
        };
    },

    computed: {
        pageStore() {
            return State.getStore('cms_page');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {},

        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.pages = [];

            return this.pageStore.getList(params).then((response) => {
                this.total = response.total;
                this.pages = response.items;
                this.isLoading = false;

                return this.pages;
            });
        },

        onChangeLanguage() {
            this.getList();
        },

        onListItemClick(page) {
            this.$router.push({ name: 'sw.cms.detail', params: { id: page.id } });
        }
    }
});
