import template from './sw-media-modal.html.twig';
import './sw-media-modal.scss';

const { Component, StateDeprecated } = Shopware;
const utils = Shopware.Utils;

/**
 * @event media-modal-selection-change EntityProxy[]
 * @event closeModal (void)
 */
Component.register('sw-media-modal', {
    template,

    props: {
        initialFolderId: {
            type: String,
            required: false,
            default: null
        },

        entityContext: {
            type: String,
            required: false,
            default: null
        },

        defaultTab: {
            type: String,
            required: false,
            validValues: ['upload', 'library'],
            default: 'library',
            validator(value) {
                return ['upload', 'library'].includes(value);
            }
        },

        allowMultiSelect: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    data() {
        return {
            selection: [],
            uploads: [],
            folderId: this.initialFolderId,
            currentFolder: null,
            compact: false,
            term: '',
            id: utils.createId(),
            selectedMediaItem: {}
        };
    },

    computed: {
        mediaStore() {
            return StateDeprecated.getStore('media');
        },

        mediaFolderStore() {
            return StateDeprecated.getStore('media_folder');
        },

        uploadStore() {
            return StateDeprecated.getStore('upload');
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
            return `sw-media-modal--${this.id}`;
        }
    },

    watch: {
        folderId() {
            this.fetchCurrentFolder();
        }
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    beforeDestroy() {
        this.beforeDestroyComponent();
    },

    methods: {
        createdComponent() {
            this.fetchCurrentFolder();
            this.addResizeListener();
        },

        mountedComponent() {
            this.getComponentWidth();
        },

        beforeDestroyComponent() {
            this.removeOnResizeListener();
        },

        fetchCurrentFolder() {
            if (!this.folderId) {
                this.currentFolder = null;
                return;
            }

            this.currentFolder = this.mediaFolderStore.getById(this.folderId);
        },

        addResizeListener() {
            window.addEventListener('resize', this.getComponentWidth);
        },

        removeOnResizeListener() {
            window.removeEventListener('resize', this.getComponentWidth);
        },

        getComponentWidth() {
            const componentWidth = this.$el.getBoundingClientRect().width;
            this.compact = componentWidth <= 900;
        },

        /*
         * v-model
         */
        onEmitModalClosed() {
            this.$emit('modal-close');
        },

        onEmitSelection() {
            // emit media items only
            const selectedMedia = this.selection.filter((selected) => {
                return selected.getEntityName() === 'media';
            });

            this.$emit('media-modal-selection-change', selectedMedia);
            this.$emit('modal-close');
        },

        /*
         * selection
         */
        refreshList() {
            this.$refs.mediaLibrary.refreshList();
        },

        onMediaRemoveSelected({ item }) {
            const index = this.selection.findIndex((selectedItem) => {
                return item.id === selectedItem.id;
            });
            if (index === -1) {
                return;
            }

            this.selection.splice(index, 1);
        },

        onMediaAddSelected({ item }) {
            if (this.selection.includes(item)) {
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

            if (folderIds.some((dissolvedId) => {
                return dissolvedId === this.currentFolder.id;
            })) {
                this.folderId = this.currentFolder.parentId;
            }

            this.$refs.mediaLibrary.refreshList();
        },

        /*
         * Media uploads
         */
        onUploadsAdded({ data }) {
            this.mediaStore.sync().then(() => {
                data.forEach((uploadTask) => {
                    this.uploads.push(this.mediaStore.getById(uploadTask.targetId));
                });
                this.uploadStore.runUploads(this.uploadTag);
            });
        },

        onUploadFinished({ targetId }) {
            this.mediaStore.getByIdAsync(targetId).then((updatedMedia) => {
                this.selectedMediaItem = updatedMedia;
                if (!this.uploads.some((upload) => {
                    return updatedMedia.id === upload.id;
                })) {
                    this.uploads.push(updatedMedia);
                }

                if (this.allowMultiSelect) {
                    const foundSelectedItem = this.selection.some((selectedItem) => {
                        return updatedMedia.id === selectedItem.id;
                    });

                    if (!foundSelectedItem) {
                        this.selection.push(updatedMedia);
                    }
                } else {
                    this.selection = [updatedMedia];
                }
            });
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
                return this.selection.includes(upload);
            }

            return upload.id === this.selectedMediaItem.id;
        }
    }
});
