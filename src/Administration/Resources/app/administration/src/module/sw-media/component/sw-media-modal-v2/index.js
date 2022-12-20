import template from './sw-media-modal-v2.html.twig';
import './sw-media-modal-v2.scss';

const { Context, Utils } = Shopware;

/**
 * @event media-modal-selection-change EntityProxy[]
 * @event closeModal (void)
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'mediaService'],

    props: {
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
            validValues: ['upload', 'library'],
            default: 'library',
            validator(value) {
                return ['upload', 'library'].includes(value);
            },
        },

        allowMultiSelect: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
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
        },
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

        async fetchCurrentFolder() {
            if (!this.folderId) {
                this.currentFolder = null;
                return;
            }

            this.currentFolder = await this.mediaFolderRepository.get(this.folderId, Context.api);
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

            this.refreshList();
        },

        /*
         * Media uploads
         */
        async onUploadsAdded({ data }) {
            await this.mediaService.runUploads(this.uploadTag);

            await Promise.all(data.map(({ targetId }) => {
                return new Promise((resolve) => {
                    this.mediaRepository.get(targetId, Context.api).then((media) => {
                        this.uploads.push(media);
                        resolve();
                    });
                });
            }));
        },

        async onUploadFinished({ targetId }) {
            const updatedMedia = await this.mediaRepository.get(targetId, Context.api);
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
        },
    },
};
