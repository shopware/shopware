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
            isDropUploadActive: false,
            selectedItems: [],
            uploads: [],
            successfulUploads: [],
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
            root.name = this.$t('sw-media.index.rootFolderName');
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

    mounted() {
        this.mountedComponent();
    },

    unmounted() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.updateFolder();
        },

        mountedComponent() {
            window.addEventListener('dragenter', this.onFileDragEnter);
            window.addEventListener('dragleave', this.onFileDragLeave);
            window.addEventListener('drop', this.onFileDrop);
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
            window.removeEventListener('dragenter', this.onFileDragEnter);
            window.removeEventListener('dragleave', this.onFileDragLeave);
            window.removeEventListener('drop', this.onFileDrop);
        },

        isFileDrag(event) {
            return Array.from(event?.dataTransfer?.types ?? []).includes('Files');
        },

        onFileDragEnter(event) {
            if (!this.acl.can('media.creator') || !this.isFileDrag(event)) {
                return;
            }

            this.isDropUploadActive = true;
        },

        onFileDragLeave(event) {
            if (event.screenX === 0 && event.screenY === 0) {
                this.isDropUploadActive = false;
            }
        },

        onFileDrop() {
            this.isDropUploadActive = false;
        },

        async onUploadsAdded({ data } = {}) {
            if (Array.isArray(data) && data.length > 0) {
                if (this.pendingUploadsCount === 0) {
                    this.successfulUploads = [];
                }

                this.pendingUploadsCount += data.length;
            }

            await this.mediaService.runUploads(this.uploadTag);
        },

        async onUploadFinished({ targetId, originalTargetId } = {}) {
            if (targetId || originalTargetId) {
                this.uploads = this.uploads.filter((upload) => {
                    return upload.id !== targetId && upload.id !== originalTargetId;
                });
            }

            if (targetId) {
                await this.addSuccessfulUpload(targetId);
            }

            await this.decrementPendingUploads();
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
                this.successfulUploads = [];
            }
        },

        async onChangeLanguage() {
            const selectionSnapshot = this.getSelectionSnapshot();

            await this.reloadList();
            await this.restoreSelection(selectionSnapshot);
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
            return this.$refs.mediaLibrary.refreshList();
        },

        async decrementPendingUploads() {
            if (this.pendingUploadsCount > 0) {
                this.pendingUploadsCount -= 1;
            }

            if (this.pendingUploadsCount === 0) {
                await this.reloadList();
                this.selectSuccessfulUploads();
            }
        },

        async addSuccessfulUpload(targetId) {
            try {
                const uploadedMedia = await this.mediaRepository.get(targetId, Context.api);
                if (!uploadedMedia) {
                    return;
                }

                this.successfulUploads.push(uploadedMedia);
            } catch {
                // Failed uploads are handled separately and should not affect the selection.
            }
        },

        selectSuccessfulUploads() {
            if (this.successfulUploads.length === 0) {
                return;
            }

            const selectableItems = this.$refs.mediaLibrary?.selectableItems ?? [];

            this.selectedItems = this.successfulUploads.reduce((selection, uploadedMedia) => {
                const visibleMedia = selectableItems.find((item) => item.id === uploadedMedia.id);

                if (visibleMedia) {
                    selection.push(visibleMedia);
                    return selection;
                }

                if (this.shouldSelectUploadFallback(uploadedMedia)) {
                    selection.push(uploadedMedia);
                }

                return selection;
            }, []);

            if (this.$refs.mediaLibrary?.setListSelectionStartItem) {
                this.$refs.mediaLibrary.setListSelectionStartItem(this.selectedItems[0] ?? null);
            }

            this.successfulUploads = [];
        },

        shouldSelectUploadFallback(uploadedMedia) {
            const activeTypeFilters = this.$refs.mediaLibrary?.normalizedTypeFilter ?? [];

            return (
                activeTypeFilters.length === 0 &&
                !this.term &&
                (uploadedMedia.mediaFolderId ?? null) === (this.routeFolderId ?? null)
            );
        },

        clearSelection() {
            this.selectedItems.splice(0, this.selectedItems.length);
        },

        getSelectionSnapshot() {
            return this.selectedItems
                .filter((item) => item?.id && item?.getEntityName)
                .map((item) => ({
                    id: item.id,
                    entityName: item.getEntityName(),
                }));
        },

        async restoreSelection(selectionSnapshot) {
            if (!selectionSnapshot.length) {
                return;
            }

            const restoredSelection = await Promise.all(selectionSnapshot.map((item) => this.resolveSelectionItem(item)));

            this.selectedItems = restoredSelection.filter(Boolean);

            if (this.$refs.mediaLibrary?.setListSelectionStartItem) {
                this.$refs.mediaLibrary.setListSelectionStartItem(this.selectedItems[0] ?? null);
            }
        },

        async resolveSelectionItem({ id, entityName }) {
            const selectableItems = this.$refs.mediaLibrary?.selectableItems ?? [];
            const visibleItem = selectableItems.find((item) => {
                return item?.id === id && item?.getEntityName?.() === entityName;
            });

            if (visibleItem) {
                return visibleItem;
            }

            try {
                if (entityName === 'media') {
                    return await this.mediaRepository.get(id, Context.api);
                }

                if (entityName === 'media_folder') {
                    return await this.mediaFolderRepository.get(id, Context.api);
                }
            } catch {
                return null;
            }

            return null;
        },

        onMediaUnselect({ item }) {
            const index = this.selectedItems.findIndex((selected) => {
                return selected?.id === item?.id && selected?.getEntityName() === item?.getEntityName();
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
