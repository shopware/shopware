import { Component, State } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-media-modal.html.twig';
import './sw-media-modal.scss';

/**
 * @event sw-media-modal-selection-changed EntityProxy[]
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
            id: utils.createId()
        };
    },

    computed: {
        mediaStore() {
            return State.getStore('media');
        },

        mediaFolderStore() {
            return State.getStore('media_folder');
        },

        uploadStore() {
            return State.getStore('upload');
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
        this.fetchCurrentFolder();
        this.addResizeListener();
    },

    mounted() {
        this.getComponentWidth();
    },

    beforeDestroy() {
        this.removeOnResizeListener();
    },

    methods: {
        /*
         * Lifecycle methods
         */
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
            this.$emit('closeModal');
        },

        onEmitSelection() {
            // emit media items only
            const selectedMedia = this.selection.filter((selected) => {
                return selected.getEntityName() === 'media';
            });

            this.$emit('sw-media-modal-selection-changed', selectedMedia);
            this.$emit('closeModal');
        },

        /*
         * selection
         */
        refreshList() {
            this.$refs.mediaLibrary.refreshList();
        },

        onMediaUnselect({ item }) {
            if (this.uploads.length && this.uploads.includes(item)) {
                return;
            }

            const index = this.selection.findIndex((selectedItem) => {
                return item.id === selectedItem.id;
            });

            if (index === -1) {
                return;
            }

            this.selection.splice(index, 1);
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
                if (!this.uploads.some((upload) => {
                    return updatedMedia.id === upload.id;
                })) {
                    this.uploads.push(updatedMedia);
                }

                if (!this.selection.some((selectedItem) => {
                    return updatedMedia.id === selectedItem.id;
                })) {
                    this.selection.push(updatedMedia);
                }
            });
        },

        onUploadFailed(task) {
            this.uploads = this.uploads.filter((selectedUpload) => {
                return selectedUpload.id !== task.targetId;
            });
        }
    }
});
