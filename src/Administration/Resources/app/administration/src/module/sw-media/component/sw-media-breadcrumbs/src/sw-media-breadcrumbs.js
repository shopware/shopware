import template from './sw-media-breadcrumbs.html.twig';
import './sw-media-breadcrumbs.scss';
import { featureService, getApiContext, getAssetFilter, repositoryFactoryService } from '../dependencies';

/**
 * @sw-package discovery
 *
 * @public
 */
export default {
    template,

    inject: [repositoryFactoryService, featureService],

    emits: ['update:currentFolderId'],

    props: {
        currentFolderId: {
            type: String,
            required: false,
            default: null,
        },

        small: {
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
            currentFolder: null,
            parentFolder: null,
        };
    },

    computed: {
        mediaFolderRepository() {
            return this.repositoryFactory.create('media_folder');
        },
        rootFolder() {
            const root = this.mediaFolderRepository.create(getApiContext());
            root.name = this.$t('sw-media.index.rootFolderName');
            root.id = null;
            return root;
        },

        swMediaBreadcrumbsClasses() {
            return {
                'is--small': this.small,
            };
        },

        assetFilter() {
            return getAssetFilter();
        },
    },

    watch: {
        currentFolderId() {
            this.updateFolder();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateFolder();
        },

        async updateFolder() {
            if (!this.currentFolderId) {
                this.currentFolder = this.rootFolder;
                this.parentFolder = null;
            } else {
                this.currentFolder = await this.mediaFolderRepository.get(this.currentFolderId, getApiContext());

                if (this.currentFolder && this.currentFolder.parentId) {
                    this.parentFolder = await this.mediaFolderRepository.get(this.currentFolder.parentId, getApiContext());
                } else {
                    this.parentFolder = this.rootFolder;
                }
            }
        },

        onBreadcrumbsItemClicked(id) {
            if (this.disabled) {
                return;
            }

            this.$emit('update:currentFolderId', id);
        },
    },
};
