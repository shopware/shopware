import template from './sw-media-index.html.twig';
import './sw-media-index.scss';

const { Context, Filter } = Shopware;

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'mediaService',
        'acl',
        'feature',
    ],

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
            pendingUploadsCount: 0,
            term: this.$route.query?.term ?? '',
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

        assetFilter() {
            return Filter.getByName('asset');
        },
    },

    watch: {
        routeFolderId() {
            this.term = '';
            this.updateFolder();
        },
    },

    created() {
        this.createdComponent();
    },

    unmounted() {
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

        destroyedComponent() {},

        async onUploadsAdded({ data } = {}) {
            if (Array.isArray(data) && data.length > 0) {
                this.pendingUploadsCount += data.length;
            }

            await this.mediaService.runUploads(this.uploadTag);
        },

        onUploadFinished({ targetId } = {}) {
            if (targetId) {
                this.uploads = this.uploads.filter((upload) => {
                    return upload.id !== targetId;
                });
            }

            this.decrementPendingUploads();
        },

        onUploadFailed({ targetId } = {}) {
            if (targetId) {
                this.uploads = this.uploads.filter((upload) => {
                    return targetId !== upload.id;
                });
            }

            this.decrementPendingUploads();
        },

        onUploadCanceled({ data } = {}) {
            if (Array.isArray(data) && data.length > 0) {
                this.pendingUploadsCount = Math.max(0, this.pendingUploadsCount - data.length);
            }

            if (this.pendingUploadsCount === 0) {
                this.reloadList();
            }
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

        decrementPendingUploads() {
            if (this.pendingUploadsCount > 0) {
                this.pendingUploadsCount -= 1;
            }

            if (this.pendingUploadsCount === 0) {
                this.reloadList();
            }
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
            this.term = this.$route.query?.term ?? this.term ?? '';
            this.$router.push({
                name: 'sw.media.index',
                params: {
                    folderId: newFolderId,
                },
            });
        },
    },
};
