import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-media-upload.html.twig';
import './sw-media-upload.scss';

const { Component, Mixin, StateDeprecated } = Shopware;
const { fileReader } = Shopware.Utils;

/**
 * @status ready
 * @description The <u>sw-media-upload</u> component is used wherever an upload is needed. It supports drag & drop-,
 * file- and url-upload and comes in various forms.
 * @example-type code-only
 * @component-example
 * <sw-media-upload
 *     uploadTag="my-upload-tag"
 *     variant="regular"
 *     allowMultiSelect="false"
 *     variant="regular"
 *     autoUpload="true"
 *     label="My image-upload">
 * </sw-media-upload>
 */
Component.register('sw-media-upload', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        source: {
            type: [Object, String],
            required: false,
            default: null
        },

        variant: {
            type: String,
            required: false,
            validValues: ['compact', 'regular'],
            validator(value) {
                return ['compact', 'regular'].includes(value);
            },
            default: 'regular'
        },

        uploadTag: {
            type: String,
            required: true
        },

        allowMultiSelect: {
            type: Boolean,
            required: false,
            default: true
        },

        label: {
            type: String,
            required: false
        },

        defaultFolder: {
            type: String,
            required: false,
            validator(value) {
                return value.length > 0;
            },
            default: null
        },

        targetFolderId: {
            type: String,
            required: false,
            default: null
        },

        helpText: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
            multiSelect: this.allowMultiSelect,
            showUrlInput: false,
            preview: null,
            isDragActive: false,
            defaultFolderId: null
        };
    },

    computed: {
        mediaItemStore() {
            return StateDeprecated.getStore('media');
        },

        uploadStore() {
            return StateDeprecated.getStore('upload');
        },

        defaultFolderStore() {
            return StateDeprecated.getStore('media_default_folder');
        },

        folderStore() {
            return StateDeprecated.getStore('media_folder');
        },

        folderConfigurationStore() {
            return StateDeprecated.getStore('media_folder_configuration');
        },

        thumbnailSizesStore() {
            return StateDeprecated.getStore('media_thumbnail_size');
        },

        showPreview() {
            return !this.multiSelect;
        },

        hasPreviewFile() {
            return this.preview !== null;
        },

        toggleButtonCaption() {
            return this.showUrlInput ?
                this.$tc('global.sw-media-upload.buttonSwitchToFileUpload') :
                this.$tc('global.sw-media-upload.buttonSwitchToUrlUpload');
        },

        hasOpenMediaButtonListener() {
            return Object.keys(this.$listeners).includes('media-upload-sidebar-open');
        },

        previewClass() {
            return {
                'has--preview': this.showPreview
            };
        },

        isDragActiveClass() {
            return {
                'is--active': this.isDragActive,
                'is--multi': this.variant === 'regular' && !!this.multiSelect
            };
        },

        mediaFolderId() {
            return this.defaultFolderId || this.targetFolderId;
        }
    },

    watch: {
        defaultFolder() {
            this.getDefaultFolderId().then((defaultFolderId) => {
                this.defaultFolderId = defaultFolderId;
            });
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
            this.uploadStore.addListener(this.uploadTag, this.handleUploadStoreEvent);
            if (this.mediaFolderId) {
                return;
            }

            if (this.defaultFolder) {
                this.getDefaultFolderId().then((defaultFolderId) => {
                    this.defaultFolderId = defaultFolderId;
                });
            }
        },

        mountedComponent() {
            if (this.$refs.dropzone) {
                ['dragover', 'drop'].forEach((event) => {
                    window.addEventListener(event, this.stopEventPropagation, false);
                });
                this.$refs.dropzone.addEventListener('drop', this.onDrop);

                window.addEventListener('dragenter', this.onDragEnter);
                window.addEventListener('dragleave', this.onDragLeave);
            }
        },

        beforeDestroyComponent() {
            this.uploadStore.removeByTag(this.uploadTag);
            this.uploadStore.removeListener(this.uploadTag, this.handleUploadStoreEvent);

            ['dragover', 'drop'].forEach((event) => {
                window.addEventListener(event, this.stopEventPropagation, false);
            });

            window.removeEventListener('dragenter', this.onDragEnter);
            window.removeEventListener('dragleave', this.onDragLeave);
        },

        /*
         * Drop Handler
         */
        onDrop(event) {
            const newMediaFiles = Array.from(event.dataTransfer.files);
            this.isDragActive = false;

            if (newMediaFiles.length === 0) {
                return;
            }

            this.handleUpload(newMediaFiles);
        },

        onDropMedia(dragData) {
            this.$emit('media-drop', dragData.mediaItem);
        },

        onDragEnter() {
            this.isDragActive = true;
        },

        onDragLeave(event) {
            if (event.screenX === 0 && event.screenY === 0) {
                this.isDragActive = false;
            }
        },

        stopEventPropagation(event) {
            event.preventDefault();
            event.stopPropagation();
        },

        /*
         * Click handler
         */
        onClickUpload() {
            this.$refs.fileInput.click();
        },

        openUrlModal() {
            this.showUrlInput = true;
        },

        closeUrlModal() {
            this.showUrlInput = false;
        },

        toggleShowUrlFields() {
            this.showUrlInput = !this.showUrlInput;
        },

        onClickOpenMediaSidebar() {
            this.$emit('media-upload-sidebar-open');
        },

        onRemoveMediaItem() {
            this.preview = null;
            this.$emit('media-upload-remove-image');
        },

        /*
         * entry points
         */
        onUrlUpload({ url, fileExtension }) {
            if (!this.multiSelect) {
                this.uploadStore.removeByTag(this.uploadTag);
                this.preview = url;
            }

            const fileInfo = fileReader.getNameAndExtensionFromUrl(url);
            if (fileExtension) {
                fileInfo.extension = fileExtension;
            }

            const targetEntity = this.getMediaEntityForUpload();
            targetEntity.save().then(() => {
                this.uploadStore.addUpload(this.uploadTag, { src: url, targetId: targetEntity.id, ...fileInfo });
            });

            this.closeUrlModal();
        },

        onFileInputChange() {
            const newMediaFiles = Array.from(this.$refs.fileInput.files);

            if (newMediaFiles.length) {
                this.handleUpload(newMediaFiles);
            }
            this.$refs.fileForm.reset();
        },

        /*
         * Helper functions
         */
        handleUpload(newMediaFiles) {
            if (!this.multiSelect) {
                this.uploadStore.removeByTag(this.uploadTag);
                newMediaFiles = [newMediaFiles.pop()];
                this.preview = newMediaFiles[0];
            }

            const uploadData = newMediaFiles.map((fileHandle) => {
                const { fileName, extension } = fileReader.getNameAndExtensionFromFile(fileHandle);
                const targetEntity = this.getMediaEntityForUpload();

                return { src: fileHandle, targetId: targetEntity.id, fileName, extension };
            });

            this.mediaItemStore.sync().then(() => {
                this.uploadStore.addUploads(this.uploadTag, uploadData);
            });
        },

        getMediaEntityForUpload() {
            const mediaItem = this.mediaItemStore.create();
            mediaItem.mediaFolderId = this.mediaFolderId;

            return mediaItem;
        },

        getDefaultFolderId() {
            this.defaultFolderStore.removeAll();
            return this.defaultFolderStore.getList({
                limit: 1,
                criteria: CriteriaFactory.equals('entity', this.defaultFolder),
                associations: {
                    folder: {}
                }
            }).then(({ items }) => {
                if (items.length !== 1) {
                    return null;
                }
                const defaultFolder = items[0];
                return defaultFolder.folder.id;
            });
        },

        handleUploadStoreEvent({ action }) {
            if (action === 'media-upload-fail') {
                this.onRemoveMediaItem();
            }
        }
    }
});
