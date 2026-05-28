import template from './sw-media-modal-v2.html.twig';
import './sw-media-modal-v2.scss';

const { Context, Utils } = Shopware;

const MEDIA_LIBRARY_PREFERENCES_KEY = 'media.library.preferences';

/**
 * @event media-modal-selection-change EntityProxy[]
 * @event closeModal (void)
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'mediaService',
        'userConfigService',
    ],

    emits: [
        'modal-close',
        'media-modal-selection-change',
    ],

    props: {
        isOpen: {
            type: Boolean,
            required: false,
            default: true,
        },

        initialFolderId: {
            type: String,
            required: false,
            default: null,
        },

        entityContext: {
            type: String,
            required: false,
            default: null,
        },

        defaultTab: {
            type: String,
            required: false,
            validValues: [
                'upload',
                'library',
            ],
            default: 'library',
            validator(value) {
                return [
                    'upload',
                    'library',
                ].includes(value);
            },
        },

        allowMultiSelect: {
            type: Boolean,
            required: false,
            default: true,
        },

        fileAccept: {
            type: String,
            required: false,
            default: 'image/*',
        },
    },

    data() {
        return {
            selection: [],
            uploads: [],
            folderId: this.initialFolderId,
            currentFolder: null,
            compact: false,
            term: '',
            id: Utils.createId(),
            selectedMediaItem: {},
            isDropUploadActive: false,
            userPreferences: {},
            isApplyingUserPreferences: false,
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },
        mediaFolderRepository() {
            return this.repositoryFactory.create('media_folder');
        },
        tabNameUpload() {
            return 'upload';
        },

        tabNameLibrary() {
            return 'library';
        },

        hasUploads() {
            return this.uploads.length > 0;
        },

        uploadTag() {
            return `sw-media-modal-v2--${this.id}`;
        },
    },

    watch: {
        folderId() {
            this.fetchCurrentFolder();
            this.saveLastFolderPreference();
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    beforeUnmount() {
        this.beforeDestroyComponent();
    },

    methods: {
        async createdComponent() {
            await this.loadUserPreferences();
            this.fetchCurrentFolder();
            this.addResizeListener();
        },

        mountedComponent() {
            this.getComponentWidth();
            window.addEventListener('dragenter', this.onFileDragEnter);
            window.addEventListener('dragleave', this.onFileDragLeave);
            window.addEventListener('drop', this.onFileDrop);
        },

        beforeDestroyComponent() {
            this.removeOnResizeListener();
            window.removeEventListener('dragenter', this.onFileDragEnter);
            window.removeEventListener('dragleave', this.onFileDragLeave);
            window.removeEventListener('drop', this.onFileDrop);
        },

        async fetchCurrentFolder() {
            if (!this.folderId) {
                this.currentFolder = null;
                return;
            }

            try {
                this.currentFolder = await this.mediaFolderRepository.get(this.folderId, Context.api);
            } catch {
                this.currentFolder = null;
                this.folderId = null;
            }
        },

        async loadUserPreferences() {
            if (!this.userConfigService?.search) {
                return;
            }

            this.isApplyingUserPreferences = true;

            try {
                const response = await this.userConfigService.search([MEDIA_LIBRARY_PREFERENCES_KEY]);
                const userPreferences = response?.data?.[MEDIA_LIBRARY_PREFERENCES_KEY];

                if (!userPreferences || typeof userPreferences !== 'object') {
                    return;
                }

                this.userPreferences = userPreferences;

                if (!this.initialFolderId && typeof userPreferences.lastFolderId === 'string') {
                    this.folderId = userPreferences.lastFolderId;
                }
            } catch {
                this.userPreferences = {};
            } finally {
                await this.$nextTick();
                this.isApplyingUserPreferences = false;
            }
        },

        async getStoredUserPreferences() {
            try {
                const response = await this.userConfigService.search([MEDIA_LIBRARY_PREFERENCES_KEY]);
                const userPreferences = response?.data?.[MEDIA_LIBRARY_PREFERENCES_KEY];

                if (!userPreferences || typeof userPreferences !== 'object') {
                    return {};
                }

                return userPreferences;
            } catch {
                return this.userPreferences;
            }
        },

        async saveLastFolderPreference() {
            if (this.isApplyingUserPreferences || !this.userConfigService?.upsert) {
                return Promise.resolve();
            }

            const storedUserPreferences = await this.getStoredUserPreferences();

            this.userPreferences = {
                ...storedUserPreferences,
                lastFolderId: this.folderId,
            };

            return this.userConfigService
                .upsert({
                    [MEDIA_LIBRARY_PREFERENCES_KEY]: this.userPreferences,
                })
                .catch(() => {});
        },

        addResizeListener() {
            window.addEventListener('resize', this.getComponentWidth);
        },

        removeOnResizeListener() {
            window.removeEventListener('resize', this.getComponentWidth);
        },

        isFileDrag(event) {
            return Array.from(event?.dataTransfer?.types ?? []).includes('Files');
        },

        onFileDragEnter(event) {
            if (!this.isFileDrag(event)) {
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

        getComponentWidth() {
            // during teleportation the $el doesn't have a bounding client rect yet
            const componentWidth = this.$el.getBoundingClientRect?.().width;
            if (!componentWidth) {
                return;
            }

            this.compact = componentWidth <= 900;
        },

        /*
         * v-model
         */
        onModalRootChange(isOpen) {
            if (!isOpen) {
                this.$emit('modal-close');
            }
        },

        onEmitModalClosed() {
            this.$emit('modal-close');
        },

        onEmitSelection() {
            // emit media items only
            const selectedMedia = this.selection.filter((selected) => {
                return selected.getEntityName() === 'media';
            });

            this.$emit('media-modal-selection-change', selectedMedia);
            this.onEmitModalClosed();
        },

        /*
         * selection
         */
        refreshList() {
            return this.$refs.mediaLibrary?.refreshList?.() ?? Promise.resolve();
        },

        onMediaRemoveSelected({ item }) {
            const index = this.selection.findIndex((selectedItem) => {
                return this.isSameMediaItem(item, selectedItem);
            });
            if (index === -1) {
                return;
            }

            this.selection.splice(index, 1);
        },

        onMediaAddSelected({ item }) {
            if (this.selection.some((selectedItem) => this.isSameMediaItem(item, selectedItem))) {
                return;
            }

            this.selection.push(item);
        },

        onMediaItemSelect({ item }) {
            if (!this.allowMultiSelect) {
                this.selection = [item];
                this.selectedMediaItem = item;
            }
        },

        resetSelection() {
            this.selection.splice(0, this.selection.length);
        },

        onItemsDeleted(ids) {
            this.onMediaFoldersDissolved(ids.folderIds);
        },

        onMediaFoldersDissolved(folderIds) {
            if (!this.currentFolder) {
                return;
            }

            if (
                folderIds.some((dissolvedId) => {
                    return dissolvedId === this.currentFolder.id;
                })
            ) {
                this.folderId = this.currentFolder.parentId;
            }

            this.refreshList();
        },

        /*
         * Media uploads
         */
        async onUploadsAdded() {
            await this.mediaService.runUploads(this.uploadTag);
        },

        async onUploadFinished({ targetId }) {
            const updatedMedia = await this.mediaRepository.get(targetId, Context.api);
            this.selectedMediaItem = updatedMedia;

            if (
                !this.uploads.some((upload) => {
                    return updatedMedia.id === upload.id;
                })
            ) {
                this.uploads.push(updatedMedia);
            }

            await this.refreshList();
            this.selectUploadedMedia(updatedMedia);
        },

        selectUploadedMedia(updatedMedia) {
            if (this.allowMultiSelect) {
                const foundSelectedItem = this.selection.some((selectedItem) => {
                    return this.isSameMediaItem(updatedMedia, selectedItem);
                });
                if (!foundSelectedItem) {
                    this.selection.push(updatedMedia);
                }
            } else {
                this.selection = [updatedMedia];
            }

            this.$refs.mediaLibrary?.setListSelectionStartItem?.(this.selection[0] ?? null);
        },

        onUploadFailed(task) {
            this.uploads = this.uploads.filter((selectedUpload) => {
                return selectedUpload.id !== task.targetId;
            });
        },

        selectMediaItem(upload) {
            if (this.allowMultiSelect) {
                return;
            }

            this.selectedMediaItem = upload;
            this.selection = [upload];
        },

        checkMediaItem(upload) {
            if (this.allowMultiSelect) {
                return this.selection.some((selectedItem) => this.isSameMediaItem(upload, selectedItem));
            }

            return this.isSameMediaItem(upload, this.selectedMediaItem);
        },

        isSameMediaItem(item, itemToCompare) {
            return item?.id === itemToCompare?.id && item?.getEntityName?.() === itemToCompare?.getEntityName?.();
        },

        onSearchTermChange(searchTerm) {
            this.term = searchTerm;
        },
    },
};
