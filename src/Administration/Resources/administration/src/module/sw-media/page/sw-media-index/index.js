import { Component, State } from 'src/core/shopware';
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
            lastSelectedItem: null
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
        }
    }
});
