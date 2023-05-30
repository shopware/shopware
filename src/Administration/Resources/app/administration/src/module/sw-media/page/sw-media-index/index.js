import template from './sw-media-index.html.twig';
import './sw-media-index.scss';

const { Context } = Shopware;

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'mediaService', 'acl'],

    props: {
        routeFolderId: {
            type: String,
            default: null,
        },

        fileAccept: {
            type: String,
            required: false,
            default: '*/*',
        },
    },

    data() {
        return {
            isLoading: false,
            selectedItems: [],
            uploads: [],
            term: this.$route.query ? this.$route.query.term : '',
            uploadTag: 'upload-tag-sw-media-index',
            parentFolder: null,
            currentFolder: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        mediaFolderRepository() {
            return this.repositoryFactory.create('media_folder');
        },
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },
        rootFolder() {
            const root = this.mediaFolderRepository.create(Context.api);
            root.name = this.$tc('sw-media.index.rootFolderName');
            root.id = null;
            return root;
        },
    },

    watch: {
        routeFolderId() {
            this.term = null;
            this.updateFolder();
        },
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.updateFolder();
        },

        async updateFolder() {
            if (!this.routeFolderId) {
                this.currentFolder = this.rootFolder;
                this.parentFolder = null;
            } else {
                this.currentFolder = await this.mediaFolderRepository.get(this.routeFolderId, Context.api);

                if (this.currentFolder && this.currentFolder.parentId) {
                    this.parentFolder = await this.mediaFolderRepository.get(this.currentFolder.parentId, Context.api);
                } else {
                    this.parentFolder = this.rootFolder;
                }
            }
        },

        destroyedComponent() {
            this.$root.$off('search', this.onSearch);
        },

        async onUploadsAdded() {
            await this.mediaService.runUploads(this.uploadTag);
            this.reloadList();
        },

        onUploadFinished({ targetId }) {
            this.uploads = this.uploads.filter((upload) => {
                return upload.id !== targetId;
            });
        },

        onUploadFailed({ targetId }) {
            this.uploads = this.uploads.filter((upload) => {
                return targetId !== upload.id;
            });
        },

        onChangeLanguage() {
            this.clearSelection();
        },

        onSearch(value) {
            this.term = value;
            this.clearSelection();
        },

        onItemsDeleted(ids) {
            this.onMediaFoldersDissolved(ids.folderIds);
        },

        onMediaFoldersDissolved(ids) {
            this.clearSelection();
            if (ids.includes(this.routeFolderId)) {
                let routeId = null;
                if (this.parentFolder) {
                    routeId = this.parentFolder.id;
                }

                this.$router.push({
                    name: 'sw.media.index',
                    params: {
                        folderId: routeId,
                    },
                });
                return;
            }

            this.reloadList();
        },

        reloadList() {
            this.$refs.mediaLibrary.refreshList();
        },

        clearSelection() {
            this.selectedItems.splice(0, this.selectedItems.length);
        },

        onMediaUnselect({ item }) {
            const index = this.selectedItems.findIndex((selected) => {
                return selected === item;
            });

            if (index > -1) {
                this.selectedItems.splice(index, 1);
            }
        },

        updateRoute(newFolderId) {
            this.term = this.$route.query ? this.$route.query.term : '';
            this.$router.push({
                name: 'sw.media.index',
                params: {
                    folderId: newFolderId,
                },
            });
        },
    },
};
