import { Component, State } from 'src/core/shopware';
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
            lastAddedMediaItems: [],
            lastSelectedItem: null,
            selectionToDelete: null
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

            this.catalogStore.getList({ page: 1, limit: 10 }).then((response) => {
                this.catalogs = response.items;
            });
            this.loadList();
        },

        getLastSelectedItem() {
            const selection = this.$refs.gridLastAdded.selection;

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

        handleSidebarRemoveItem({ item }) {
            this.selectionToDelete = [item];
        },

        handleSidebarRemoveBatchRequest() {
            this.selectionToDelete = this.$refs.gridLastAdded.selection;
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
                this.loadList();
            });
        },

        loadList() {
            this.mediaItemStore.getList({
                page: 1,
                limit: 10,
                sortBy: 'createdAt',
                sortDirection: 'desc'
            }).then((response) => {
                this.lastAddedMediaItems = response.items;
            });
            this.isLoading = false;
        }
    }
});
