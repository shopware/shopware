import { Component, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import '../../component/sw-media-modal-delete';
import mediaMediaGridListener from '../../mixin/mediagrid.listener.mixin';
import mediaSidebarListener from '../../mixin/sibebar.listener.mixin';
import template from './sw-media-index.html.twig';
import './sw-media-index.less';

Component.register('sw-media-index', {
    template,

    mixins: [
        mediaMediaGridListener,
        mediaSidebarListener
    ],

    data() {
        return {
            isLoading: false,
            catalogs: [],
            mediaItems: [],
            lastSelectedItem: null,
            selectionToDelete: null,
            mediaItemToReplace: null,
            searchId: this.$route.query.mediaId
        };
    },

    computed: {
        catalogStore() {
            return State.getStore('catalog');
        },

        mediaItemStore() {
            return State.getStore('media');
        },

        isSearch() {
            return this.searchId !== null && this.searchId !== undefined;
        }
    },

    created() {
        this.createComponent();
    },

    beforeRouteUpdate(to, from, next) {
        if (to.query.mediaId) {
            this.searchId = to.query.mediaId;
        } else {
            this.searchId = null;
        }
        this.loadMedia();
        next();
    },

    methods: {
        createComponent() {
            this.isLoading = true;

            this.catalogStore.getList({ page: 1, limit: 10 }).then((response) => {
                this.catalogs = response.items;
            });
            this.loadMedia();
        },

        getLastSelectedItem() {
            const selection = this.$refs.mediaGrid.selection;

            if (selection.length === 0) {
                this.lastSelectedItem = null;
                return;
            }

            this.lastSelectedItem = selection[selection.length - 1];
        },

        handleMediaGridSelectionRemoved() {
            this.getLastSelectedItem();
        },

        handleMediaGridItemSelected() {
            this.getLastSelectedItem();
        },

        handleMediaGridItemUnselected() {
            this.getLastSelectedItem();
        },

        handleMediaGridItemReplace({ item }) {
            this.mediaItemToReplace = item;
        },

        handleMediaGridItemShowDetails({ item }) {
            this.lastSelectedItem = item;
            this.$refs.mediaSidebar.showQuickInfo();
        },

        handleSidebarRemoveItem({ item }) {
            this.selectionToDelete = [item];
        },

        handleSidebarRemoveBatchRequest() {
            this.selectionToDelete = this.$refs.mediaGrid.selection;
        },

        handleMediaGridItemDelete({ item }) {
            this.selectionToDelete = [item];
        },

        closeDeleteModal() {
            this.selectionToDelete = null;
        },

        deleteSelection() {
            const mediaItemsDeletion = [];
            this.isLoading = true;

            this.selectionToDelete.forEach((element) => {
                mediaItemsDeletion.push(this.mediaItemStore.getById(element.id).delete(true));
            });

            Promise.all(mediaItemsDeletion).then(() => {
                this.selectionToDelete = null;
                this.loadMedia();
            });
        },

        handleSidebarReplaceItem({ item }) {
            this.mediaItemToReplace = item;
        },

        closeReplaceModal() {
            this.mediaItemToReplace = null;
        },

        handleItemReplaced(replacementPromise) {
            this.closeReplaceModal();

            replacementPromise.then(() => {
                this.loadMedia();
            });
        },

        loadMedia() {
            if (this.isSearch) {
                this.loadSearchedMedia();
                this.isLoading = false;
                return;
            }

            this.loadLastAddedMedia();
            this.isLoading = false;
        },

        loadLastAddedMedia() {
            this.mediaItemStore.getList({
                page: 1,
                limit: 10,
                sortBy: 'createdAt',
                sortDirection: 'desc'
            }).then((response) => {
                this.mediaItems = response.items;
            });
        },

        loadSearchedMedia() {
            const params = {
                limit: 1,
                page: 1,
                criteria: CriteriaFactory.term('id', this.searchId),
                sortBy: 'createdAt',
                sortDirection: 'desc'
            };

            this.mediaItemStore.getList(params).then((response) => {
                this.mediaItems = response.items;
                this.lastSelectedItem = this.mediaItems[0];
                this.handleMediaGridItemShowDetails({ item: this.mediaItems[0] });
            });
        }
    }
});
