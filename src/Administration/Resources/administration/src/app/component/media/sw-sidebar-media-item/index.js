import { Component, State, Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-sidebar-media-item.html.twig';
import './sw-sidebar-media-item.less';

Component.register('sw-sidebar-media-item', {
    template,

    mixins: [Mixin.getByName('listing')],

    props: {
        catalogId: {
            type: String,
            required: true
        }
    },

    data() {
        return {
            isLoading: true,
            searchTopic: '',
            catalog: null,
            mediaItems: []
        };
    },

    computed: {
        catalogStore() {
            return State.getStore('catalog');
        },

        mediaStore() {
            return State.getStore('media');
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
        registerSidebarItem(item) {
            this.$parent.registerSidebarItem(item);
        },

        componentCreated() {
            this.initializeContent();
        },

        initializeContent() {
            if (this.catalogId) {
                this.catalog = this.catalogStore.getById(this.catalogId);
            } else {
                this.catalog = null;
            }

            this.getList();
        },

        handleMediaGridItemDelete() {
            this.getList();
        },

        addItemToProduct(item) {
            this.$emit('sw-sidebar-media-item-add-item-to-product', item);
        },

        getList() {
            this.isLoading = true;

            if (!this.catalog) {
                return new Promise(() => {
                    this.mediaItems = [];
                    this.total = 0;
                    this.isLoading = false;
                    return this.mediaItems;
                });
            }

            const params = this.getListingParams();
            params.criteria = CriteriaFactory.term('catalogId', this.catalog.id);

            return this.mediaStore.getList(params).then((response) => {
                this.mediaItems = response.items;
                this.total = response.total;
                this.isLoading = false;
                return this.mediaItems;
            });
        }
    }
});
