import { Component, State } from 'src/core/shopware';
import template from './sw-catalog-list.html.twig';
import './sw-catalog-list.less';

Component.register('sw-catalog-list', {
    template,

    data() {
        return {
            catalogs: [],
            isLoading: false
        };
    },

    computed: {
        catalogStore() {
            return State.getStore('catalog');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            this.catalogStore.getList({
                page: 1,
                limit: 100,
                sortBy: 'createdAt',
                sortDirection: 'asc'
            }).then((response) => {
                this.catalogs = response.items;
                this.isLoading = false;
            });
        }
    }
});
