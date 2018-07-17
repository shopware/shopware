import { Component, State } from 'src/core/shopware';
import template from './sw-mediamanager-index.html.twig';
import './sw-mediamanager-index.less';

Component.register('sw-mediamanager-index', {
    template,

    data() {
        return {
            isLoading: false,
            catalogs: [],
            lastEditedMediaItems: [],
            lastAddedMediaItems: []
        };
    },

    computed: {
        catalogStore() {
            return State.getStore('catalog');
        },
        mediaItemStore() {
            return State.getStore('media');
        }
    },

    created() {
        this.createComponent();
    },

    methods: {
        createComponent() {
            this.isLoading = true;

            this.catalogStore.getList({ offset: 0, limit: 7 }).then((response) => {
                this.catalogs = response.items;
            });

            this.mediaItemStore.getList({
                offset: 0,
                limit: 15,
                sortBy: 'createdAt',
                sortDirection: 'desc'
            }).then((response) => {
                this.lastAddedMediaItems = response.items;
            });
            this.isLoading = false;
        }
    }
});
