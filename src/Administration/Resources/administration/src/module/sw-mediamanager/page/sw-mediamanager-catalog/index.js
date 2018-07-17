import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-mediamanager-catalog.html.twig';
import './sw-mediamanager-catalog.less';

Component.register('sw-mediamanager-catalog', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            isLoading: false,
            previewType: 'media-grid-preview-as-grid',
            catalogs: [],
            mediaItems: [],
            total: 0,
            offset: 0,
            limit: 50
        };
    },

    computed: {
        mediaItemStore() {
            return State.getStore('media');
        },
        catalogStore() {
            return State.getStore('catalog');
        }
    },

    created() {
        this.onComponentCreated();
    },

    methods: {
        onComponentCreated() {
            this.isLoading = true;

            this.catalogStore.getList({
                offset: 0,
                limit: 0
            }).then((response) => {
                this.catalogs = response.items;
            });

            this.getList();

            this.isLoading = false;
        },
        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.mediaItems = [];

            return this.mediaItemStore.getList(params).then((response) => {
                this.total = response.total;
                this.mediaItems = response.items;
                this.isLoading = false;

                return this.mediaItems;
            });
        },
        switchToGridView() {
            this.previewType = 'media-grid-preview-as-grid';
        },
        switchToListView() {
            this.previewType = 'media-grid-preview-as-list';
        }
    }
});
