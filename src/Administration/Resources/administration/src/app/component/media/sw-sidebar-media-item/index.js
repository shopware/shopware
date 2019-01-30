import { State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-sidebar-media-item.html.twig';
import './sw-sidebar-media-item.scss';

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
export default {
    name: 'sw-sidebar-media-item',
    template,

    props: {
        initialFolderId: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
            isLoading: true,
            mediaFolderId: this.initialFolderId,
            mediaItems: [],
            subFolders: [],
            page: 1,
            limit: 25,
            total: 0,
            term: ''
        };
    },

    computed: {
        mediaStore() {
            return State.getStore('media');
        },

        mediaFolderStore() {
            return State.getStore('media_folder');
        },

        showMore() {
            return this.itemsLoaded < this.total;
        },

        itemsLoaded() {
            return this.mediaItems.length;
        }
    },

    watch: {
        term() {
            this.page = 1;
            this.getList();
        },

        mediaFolderId() {
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
            this.getSubFolders();
            this.getList();
        },

        getSubFolders() {
            return this.mediaFolderStore.getList({
                page: 1,
                limit: 50,
                criteria: CriteriaFactory.equals('parentId', this.mediaFolderId)
            }).then((response) => {
                this.subFolders = response.items;
            });
        },

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
                page: this.page,
                criteria: CriteriaFactory.equals('mediaFolderId', this.mediaFolderId)
            };

            if (this.term) {
                params.term = this.term;
            }

            return params;
        },

        openContent() {
            this.$refs.sidebarItem.openContent();
        },

        onNavigateToFolder(folderId) {
            this.mediaFolderId = folderId;
        }
    }
};
