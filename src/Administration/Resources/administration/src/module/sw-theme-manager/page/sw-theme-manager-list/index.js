import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-theme-manager-list.html.twig';

Component.register('sw-theme-manager-list', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('listing')
    ],

    data() {
        return {
            themes: [],
            showDeleteModal: false,
            isLoading: false,
            total: 0
        };
    },

    computed: {
        themeStore() {
            return State.getStore('theme');
        }
    },

    methods: {
        onRefresh() {
            this.getList();
        },

        onChangeLanguage() {
            this.getList();
        },

        getList() {
            this.isLoading = true;
            return this.themeStore.getList({
                page: 1,
                limit: 500
            }).then((response) => {
                this.isLoading = false;
                this.themes = Object.values(this.themeStore.store);
                return response.items;
            });
        }

    }

});
