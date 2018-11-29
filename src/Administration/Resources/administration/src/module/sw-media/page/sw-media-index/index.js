import { Component, State, Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import Utils from 'src/core/service/util.service';
import template from './sw-media-index.html.twig';
import './sw-media-index.less';

Component.register('sw-media-index', {
    template,

    mixins: [
        Mixin.getByName('media-grid-listener'),
        Mixin.getByName('drag-selector')
    ],

    data() {
        return {
            isLoading: false,
            previewType: 'media-grid-preview-as-grid',
            mediaItems: [],
            uploadedItems: [],
            displayedItems: [],
            sortType: ['createdAt', 'dsc'],
            catalogIconSize: 200,
            presentation: 'medium-preview',
            isLoadingMore: false,
            itemsLeft: 0,
            page: 1,
            limit: 50,
            term: '',
            total: 0
        };
    },

    computed: {
        mediaItemStore() {
            return State.getStore('media');
        },

        mediaSidebar() {
            return this.$refs.mediaSidebar;
        },

        selectableItems() {
            return this.mediaItems;
        }
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    watch: {
        uploadedItems() {
            this.debounceDisplayItems();
        },

        mediaItems(value) {
            this.displayedItems = this.uploadedItems.concat(value);
        }
    },

    methods: {
        createdComponent() {
            this.getList();
        },

        destroyedComponent() {
            this.$root.$off('search', this.onSearch);
        },

        debounceDisplayItems() {
            Utils.debounce(() => {
                this.displayedItems = this.uploadedItems.concat(this.mediaItems);
                if (this.$refs.mediaGrid) {
                    this.$refs.mediaGrid.$el.scrollTop = 0;
                }
            }, 100)();
        },

        showDetails(mediaItem) {
            this._showDetails(mediaItem, false);
        },

        onNewUpload(mediaEntity) {
            this.uploadedItems.unshift(mediaEntity);
        },

        getList() {
            this.isLoading = true;
            this.clearSelection();
            const params = this.getListingParams();

            return this.mediaItemStore.getList(params, true).then((response) => {
                this.total = response.total;
                this.mediaItems = response.items;
                this.isLoading = false;
                this.itemsLeft = this.calcItemsLeft();

                return this.mediaItems;
            });
        },

        onLoadMore() {
            this.page += 1;
            this.extendList();
        },

        onSearch(value) {
            this.term = value;

            this.page = 1;
            this.getList();
        },

        extendList() {
            const params = this.getListingParams();
            this.isLoadingMore = true;

            return this.mediaItemStore.getList(params).then((response) => {
                this.mediaItems = this.mediaItems.concat(response.items);
                this.itemsLeft = this.calcItemsLeft();
                this.isLoadingMore = false;

                return this.mediaItems;
            });
        },

        getListingParams() {
            return {
                limit: this.limit,
                page: this.page,
                sortBy: this.sortType[0],
                sortDirection: this.sortType[1],
                term: this.term,
                criteria: CriteriaFactory.multi('and', ...this.getQueries())
            };
        },

        getQueries() {
            return this.uploadedItems.map((item) => {
                return CriteriaFactory.not('and', CriteriaFactory.equals('id', item.id));
            });
        },

        calcItemsLeft() {
            return this.total - this.mediaItems.length;
        },

        sortMediaItems(event) {
            this.sortType = event.split(':');
            this.getList();
        },

        getCatalogId() {
            return this.$route.params.id;
        },

        handleMediaGridItemDelete() {
            this.getList();
        },

        onDragSelection({ originalDomEvent, item }) {
            item.selectItem(originalDomEvent);
        },

        onDragDeselection({ originalDomEvent, item }) {
            item.removeFromSelection(originalDomEvent);
        },

        dragSelectorClass() {
            return 'sw-media-grid-media-item';
        },

        scrollContainer() {
            return this.$refs.scrollContainer;
        },

        itemContainer() {
            return this.$refs.mediaGrid;
        }
    }
});
