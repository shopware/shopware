import template from './sw-sidebar-media-item.html.twig';
import './sw-sidebar-media-item.scss';

const { Context } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @status ready
 * @description The <u>sw-sidebar-media-item</u> component is used everywhere you need media objects outside the media
 * manager. Use the additional properties to filter the shown media.
 * @package content
 * @example-type code-only
 * @component-example
 * <sw-sidebar-media-item>
 *    <template #context-menu-items="media">
 *       <sw-context-menu-item @click="onAddItemToProduct(media.mediaItem)">
 *          Lorem ipsum dolor sit amet
 *       </sw-context-menu-item>
 *    </template>
 * </sw-sidebar-media-item>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    props: {
        initialFolderId: {
            type: String,
            required: false,
            default: null,
        },
        isParentLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
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
            term: '',
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        mediaFolderRepository() {
            return this.repositoryFactory.create('media_folder');
        },

        showMore() {
            return this.itemsLoaded < this.total;
        },

        itemsLoaded() {
            return this.mediaItems.length;
        },

        additionalEventListeners() {
            return this.$listeners;
        },
    },

    watch: {
        initialFolderId() {
            this.mediaFolderId = this.initialFolderId;
        },

        mediaFolderId() {
            this.initializeContent();
        },

        isParentLoading() {
            this.getList();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initializeContent();
        },

        onSearchTermChange(searchTerm) {
            this.term = searchTerm;
            this.page = 1;
            this.getList();
        },

        initializeContent() {
            if (this.disabled) {
                return;
            }
            this.page = 1;
            this.term = '';
            this.mediaItems = [];
            this.getSubFolders();
            this.getList();
        },

        async getSubFolders() {
            const criteria = new Criteria(1, 50);
            criteria.addFilter(Criteria.equals('parentId', this.mediaFolderId));

            const folder = await this.mediaFolderRepository.search(criteria, Context.api);
            this.subFolders = folder;
            return folder;
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

        async extendList() {
            const criteria = this.getListingCriteria();

            const searchResult = await this.mediaRepository.search(criteria, Context.api);
            this.mediaItems = this.mediaItems.concat(searchResult);

            return this.mediaItems;
        },

        async getList() {
            if (this.isParentLoading === true) {
                return null;
            }

            this.isLoading = true;

            const criteria = this.getListingCriteria();

            this.mediaItems = await this.mediaRepository.search(criteria, Context.api);
            this.total = this.mediaItems.total;
            this.isLoading = false;

            return this.mediaItems;
        },

        getListingCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            if (!this.term.length) {
                criteria.addFilter(Criteria.equals('mediaFolderId', this.mediaFolderId));
            }

            if (this.term) {
                criteria.term = this.term;
            }

            criteria.addSorting(
                Criteria.sort('uploadedAt', 'DESC'),
            );

            return criteria;
        },

        openContent() {
            this.$refs.sidebarItem.openContent();
        },

        onNavigateToFolder(folderId) {
            this.mediaFolderId = folderId;
        },
    },
};
