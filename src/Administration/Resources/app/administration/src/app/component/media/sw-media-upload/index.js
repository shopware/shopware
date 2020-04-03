import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-media-upload.html.twig';
import './sw-media-upload.scss';

const { Component, Mixin, StateDeprecated } = Shopware;
const { fileReader, debug } = Shopware.Utils;
const INPUT_TYPE_FILE_UPLOAD = 'file-upload';
const INPUT_TYPE_URL_UPLOAD = 'url-upload';

/**
 * @deprecated tag:v6.4.0
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

    deprecated: {
        version: '6.4.0',
        comment: 'Use sw-media-upload-v2 instead'
    },

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
        },

        error: {
            type: [Object],
            required: false,
            default() {
                return null;
            }
        },

        required: {
            type: Boolean,
            required: false,
            default: false
        },

        fileAccept: {
            type: String,
            required: false,
            default: 'image/*'
        },

        sourceContext: {
            type: Object,
            required: false,
            default: null
        }
    },

    data() {
        return {
            multiSelect: this.allowMultiSelect,
            inputType: INPUT_TYPE_FILE_UPLOAD,
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
        },

        swMediaUploadLabelClasses() {
            return {
                'is--required': this.required
            };
        },

        isUrlUpload() {
            return this.inputType === INPUT_TYPE_URL_UPLOAD;
        },

        isFileUpload() {
            return this.inputType === INPUT_TYPE_FILE_UPLOAD;
        },

        // @deprecated tag:v6.4.0
        showUrlInput: {
            get() {
                debug.warn(
                    'sw-media-upload',
                    'showUrlInput is deprecated and will be removed in 6.4.0. Use isFileUpload or isUrlUpload instead'
                );

                return this.isUrlUpload;
            },

            set(value) {
                debug.warn(
                    'sw-media-upload',
                    'showUrlInput is deprecated and will be removed in 6.4.0. Use useFileUpload or useUrlUpload instead'
                );

                if (value) {
                    this.useUrlUpload = value;
                } else {
                    this.useFileUpload = value;
                }
            }
        }
    },

    watch: {
        defaultFolder() {
            this.getDefaultFolderId().then((defaultFolderId) => {
                this.defaultFolderId = defaultFolderId;
            });
        },

        'source.id'() {
            if (this.error) {
                Shopware.State.dispatch(
                    'error/removeApiError',
                    { expression: this.error.selfLink }
                );
            }
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

        // @deprecated tag:v6.4.0
        closeUrlModal() {
            debug.warn(
                'sw-media-upload',
                'closeUrlModal is deprecated and will be removed in 6.4.0. Use useUrlUpload instead'
            );

            return this.useUrlUpload();
        },

        // @deprecated tag:v6.4.0
        openUrlModal() {
            debug.warn(
                'sw-media-upload',
                'openUrlModal is deprecated and will be removed in 6.4.0. Use useFileUpload instead'
            );

            return this.useFileUpload();
        },

        // @deprecated tag:v6.4.0
        toggleShowUrlFields() {
            debug.warn(
                'sw-media-upload',
                'toggleShowUrlFields is deprecated and will be removed in 6.4.0. Use useUrlUpload or useFileUpload instead'
            );

            if (this.isUrlUpload) {
                return this.useFileUpload();
            }

            return this.useUrlUpload();
        },

        useUrlUpload() {
            this.inputType = INPUT_TYPE_URL_UPLOAD;
        },

        useFileUpload() {
            this.inputType = INPUT_TYPE_FILE_UPLOAD;
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

                return items[0].folder.id;
            });
        },

        handleUploadStoreEvent({ action }) {
            if (action === 'media-upload-fail') {
                this.onRemoveMediaItem();
            }
        }
    }
});
