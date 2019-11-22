import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-sidebar-media-item.html.twig';
import './sw-sidebar-media-item.scss';

const { Component, StateDeprecated } = Shopware;

/**
 * @status ready
 * @description The <u>sw-sidebar-media-item</u> component is used everywhere you need media objects outside of the media
 * manager. Use the additional properties to filter the shown media.
 * Just pass a object created by the CriteriaFactory.
 * @example-type code-only
 * @component-example
 * <sw-sidebar-media-item :useAdditionalSearchCriteria="true"
 *                        :additionalSearchCriteria="getCriteria">
 *    <template slot="context-menu-items" slot-scope="media">
 *       <sw-context-menu-item @click="onAddItemToProduct(media.mediaItem)">
 *          Lorem ipsum dolor sit amet
 *       </sw-context-menu-item>
 *    </template>
 * </sw-sidebar-media-item>
 */
Component.register('sw-sidebar-media-item', {
    template,

    props: {
        initialFolderId: {
            type: String,
            required: false,
            default: null
        },
        isParentLoading: {
            type: Boolean,
            required: false,
            default: false
        },
        additionalSearchCriteria: {
            type: Object,
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
            return StateDeprecated.getStore('media');
        },

        mediaFolderStore() {
            return StateDeprecated.getStore('media_folder');
        },

        showMore() {
            return this.itemsLoaded < this.total;
        },

        itemsLoaded() {
            return this.mediaItems.length;
        },

        additionalEventListeners() {
            return this.$listeners;
        }
    },

    watch: {
        term() {
            this.page = 1;
            this.getList();
        },

        initialFolderId() {
            this.mediaFolderId = this.initialFolderId;
        },

        mediaFolderId() {
            this.initializeContent();
        },

        isParentLoading() {
            this.getList();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
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

        handleFolderGridItemDelete() {
            this.getSubFolders();
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
            if (this.isParentLoading === true) {
                return null;
            }

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
            const searchCriteria = [];

            if (!this.term.length) {
                searchCriteria.push(CriteriaFactory.equals('mediaFolderId', this.mediaFolderId));
            }

            if (this.additionalSearchCriteria) {
                searchCriteria.push(this.additionalSearchCriteria);
            }

            const params = {
                limit: this.limit,
                page: this.page,
                criteria: CriteriaFactory.multi('and', ...searchCriteria)
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
});
