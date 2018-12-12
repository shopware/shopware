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

    props: {
        routeFolderId: {
            type: String
        }
    },

    data() {
        return {
            isLoading: false,
            subFolders: [],
            mediaItems: [],
            uploadedItems: [],
            sortType: ['createdAt', 'dsc'],
            presentation: 'medium-preview',
            isLoadingMore: false,
            itemsLeft: 0,
            page: 1,
            limit: 50,
            term: '',
            total: 0,
            currentFolder: null,
            parentFolder: null
        };
    },

    computed: {
        mediaItemStore() {
            return State.getStore('media');
        },

        mediaFolderStore() {
            return State.getStore('media_folder');
        },

        uploadStore() {
            return State.getStore('upload');
        },

        selectableItems() {
            return [].concat(this.subFolders, this.uploadedItems, this.mediaItems);
        },

        mediaFolderId() {
            return this.routeFolderId || null;
        },

        parentFolderName() {
            return this.parentFolder ? this.parentFolder.name : this.$tc('sw-media.index.rootFolderName');
        },

        currentFolderName() {
            return this.currentFolder ? this.currentFolder.name : this.$tc('sw-media.index.rootFolderName');
        },

        dragSelectorClass() {
            return 'sw-media-entity';
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

        routeFolderId() {
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
            this.getList();
            this.getFolderEntities();
        },

        destroyedComponent() {
            this.$root.$off('search', this.onSearch);
        },

        debounceDisplayItems() {
            Utils.debounce(() => {
                if (this.$refs.mediaGrid) {
                    this.$refs.mediaGrid.$el.scrollTop = 0;
                }
            }, 100)();
        },

        showDetails(mediaItem) {
            this._showDetails(mediaItem, false);
        },

        onUploadsAdded({ uploadTag, data }) {
            data.forEach((upload) => {
                upload.entity.mediaFolderId = this.mediaFolderId;
                this.uploadedItems.unshift(upload.entity);
            });

            this.mediaItemStore.sync().then(() => {
                this.uploadStore.runUploads(uploadTag);
            });
        },

        getFolderEntities() {
            this.mediaFolderStore.getByIdAsync(this.mediaFolderId).then((folder) => {
                this.currentFolder = folder;

                this.mediaFolderStore.getByIdAsync(this.currentFolder.parentId).then((parent) => {
                    this.parentFolder = parent;
                }).catch(() => {
                    this.parentFolder = null;
                });
            }).catch(() => {
                this.currentFolder = null;
                this.parentFolder = null;
            });
        },

        getList() {
            this.clearSelection();
            this.isLoading = true;

            Promise.all([
                this.getSubFolders(),
                this.getMediaItemList()
            ]).then(() => {
                this.isLoading = false;
            });
        },

        getSubFolders() {
            return this.mediaFolderStore.getList({
                limit: 50,
                sortBy: 'name',
                criteria: CriteriaFactory.equals('parentId', this.mediaFolderId)
            }).then((response) => {
                this.subFolders = response.items;
            });
        },

        getMediaItemList() {
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
            if (this.$route.query.mediaId) {
                return {
                    criteria: CriteriaFactory.equals('id', this.$route.query.mediaId)
                };
            }

            return {
                limit: this.limit,
                page: this.page,
                sortBy: this.sortType[0],
                sortDirection: this.sortType[1],
                term: this.term,
                criteria: CriteriaFactory.multi('and', this.folderQuery(), ...this.getQueries())
            };
        },

        folderQuery() {
            return CriteriaFactory.equals('mediaFolderId', this.mediaFolderId);
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

        onMediaGridItemsDeleted(ids) {
            this.uploadedItems = this.uploadedItems.filter((uploadedItem) => {
                return ids.includes(uploadedItem.item);
            });
            this.getList();
        },

        onDragSelection({ originalDomEvent, item }) {
            item.selectItem(originalDomEvent);
        },

        onDragDeselection({ originalDomEvent, item }) {
            item.removeFromSelection(originalDomEvent);
        },

        scrollContainer() {
            return this.$refs.scrollContainer;
        },

        itemContainer() {
            return this.$refs.mediaGrid;
        },

        createFolder() {
            const newFolder = this.mediaFolderStore.create();

            newFolder.name = '';
            newFolder.parentId = this.mediaFolderId;

            this.subFolders.unshift(newFolder);
        },

        onMediaFoldersDeleted(ids) {
            this.subFolders = this.subFolders.filter((folder) => {
                return !ids.includes(folder.id);
            });
        }
    }
});
