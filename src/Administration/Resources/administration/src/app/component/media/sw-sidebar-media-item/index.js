import { Component, State } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-sidebar-media-item.html.twig';
import './sw-sidebar-media-item.less';

/**
 * @status ready
 * @description The <u>sw-sidebar-media-item</u> component is used everywhere you need media objects outside of the media
 * manager.
 * @example-type code-only
 * @component-example
 * <sw-sidebar-media-item>
 *    <template slot="context-menu-items" slot-scope="media">
 *       <sw-context-menu-item @click="onAddItemToProduct(media.mediaItem)">
 *          Lorem ipsum dolor sit amet
 *       </sw-context-menu-item>
 *    </template>
 * </sw-sidebar-media-item>
 */
Component.register('sw-sidebar-media-item', {
    template,

    data() {
        return {
            isLoading: true,
            mediaItems: [],
            page: 1,
            limit: 25,
            total: 0,
            term: ''
        };
    },

    computed: {
        catalogStore() {
            return State.getStore('catalog');
        },

        mediaStore() {
            return State.getStore('media');
        },

        showMore() {
            return this.itemsLoaded < this.total;
        },

        itemsLoaded() {
            return this.mediaItems.length;
        }
    },

    watch: {
        catalogId(newCatalogId) {
            this.catalogId = newCatalogId;
            this.initializeContent();
        }
    },

    created() {
        this.componentCreated();
    },

    methods: {
        componentCreated() {
            this.initializeContent();
        },

        initializeContent() {
            this.page = 1;
            this.term = '';
            this.mediaItems = [];

            this.getList();
        },

        onSearchInput(searchTopic) {
            this.doListSearch(searchTopic);
        },

        doListSearch: utils.debounce(function debouncedSearch(searchTopic) {
            const searchTerm = searchTopic || '';
            this.term = searchTerm.trim();
            this.page = 1;
            this.getList();
        }, 400),

        handleMediaGridItemDelete() {
            const pages = this.page;
            this.page = 1;
            this.getList().then(() => {
                while (this.page < pages) {
                    this.page += 1;
                    this.extendList();
                }
            });
        },

        onLoadMore() {
            this.page += 1;
            this.extendList();
        },

        extendList() {
            const params = this.getListingParams();

            return this.mediaStore.getList(params).then((response) => {
                this.mediaItems = this.mediaItems.concat(response.items);
                return this.mediaItems;
            });
        },

        getList() {
            this.isLoading = true;

            const params = this.getListingParams();

            return this.mediaStore.getList(params).then((response) => {
                this.mediaItems = response.items;
                this.total = response.total;
                this.isLoading = false;

                return this.mediaItems;
            });
        },

        getListingParams() {
            const params = {
                limit: this.limit,
                page: this.page
            };

            if (this.term && this.term.length) {
                params.term = this.term;
            }

            return params;
        }
    }
});
