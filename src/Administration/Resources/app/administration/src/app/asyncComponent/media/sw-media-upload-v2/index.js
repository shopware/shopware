import template from './sw-media-upload-v2.html.twig';
import './sw-media-upload-v2.scss';

const { Mixin, Context } = Shopware;
const { fileReader } = Shopware.Utils;
const { fileSize } = Shopware.Utils.format;
const { Criteria } = Shopware.Data;
const INPUT_TYPE_FILE_UPLOAD = 'file-upload';
const INPUT_TYPE_URL_UPLOAD = 'url-upload';

/**
 * @status ready
 * @description The <u>sw-media-upload-v2</u> component is used wherever an upload is needed. It supports drag & drop-,
 * file- and url-upload and comes in various forms.
 * @package content
 * @example-type code-only
 * @component-example
 * <sw-media-upload-v2
 *     upload-tag="my-upload-tag"
 *     variant="regular"
 *     :allow-multi-select="false"
 *     label="My image-upload"
 * ></sw-media-upload-v2>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'mediaService', 'configService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        source: {
            type: [Object, String, File],
            required: false,
            default: null,
        },

        variant: {
            type: String,
            required: false,
            validValues: ['compact', 'regular', 'small'],
            validator(value) {
                return ['compact', 'regular', 'small'].includes(value);
            },
            default: 'regular',
        },

        uploadTag: {
            type: String,
            required: true,
        },

        allowMultiSelect: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        addFilesOnMultiselect: {
            type: Boolean,
            required: false,
            default: false,
        },

        // eslint-disable-next-line vue/require-default-prop
        label: {
            type: String,
            required: false,
            default: null,
        },

        buttonLabel: {
            type: String,
            required: false,
            default: '',
        },

        defaultFolder: {
            type: String,
            required: false,
            validator(value) {
                return value.length > 0;
            },
            default: null,
        },

        targetFolderId: {
            type: String,
            required: false,
            default: null,
        },

        helpText: {
            type: String,
            required: false,
            default: null,
        },

        sourceContext: {
            type: Object,
            required: false,
            default: null,
        },

        fileAccept: {
            type: String,
            required: false,
            default: 'image/*',
        },

        maxFileSize: {
            type: Number,
            required: false,
            default: null,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        privateFilesystem: {
            type: Boolean,
            required: false,
            default: false,
        },

        useFileData: {
            type: Boolean,
            required: false,
            default: false,
        },

        required: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            multiSelect: this.allowMultiSelect,
            inputType: INPUT_TYPE_FILE_UPLOAD,
            preview: null,
            isDragActive: false,
            defaultFolderId: null,
            isUploadUrlFeatureEnabled: false,
        };
    },

    computed: {
        defaultFolderRepository() {
            return this.repositoryFactory.create('media_default_folder');
        },

        mediaRepository() {
            return this.repositoryFactory.create('media', '', {
                keepApiErrors: true,
            });
        },

        showPreview() {
            return !this.multiSelect;
        },

        hasOpenMediaButtonListener() {
            return Object.keys(this.$listeners).includes('media-upload-sidebar-open');
        },

        isDragActiveClass() {
            return {
                'is--active': this.isDragActive,
                'is--multi': this.variant === 'regular' && !!this.multiSelect,
                'is--small': this.variant === 'small',
            };
        },

        mediaFolderId() {
            return this.defaultFolderId || this.targetFolderId;
        },

        isUrlUpload() {
            return this.inputType === INPUT_TYPE_URL_UPLOAD;
        },

        isFileUpload() {
            return this.inputType === INPUT_TYPE_FILE_UPLOAD;
        },

        uploadUrlFeatureEnabled() {
            return this.isUploadUrlFeatureEnabled;
        },

        swFieldLabelClasses() {
            return {
                'is--required': this.required,
            };
        },

        buttonFileUploadLabel() {
            if (this.buttonLabel === '') {
                return this.$tc('global.sw-media-upload-v2.buttonFileUpload');
            }

            return this.buttonLabel;
        },
    },

    watch: {
        async defaultFolder() {
            this.defaultFolderId = await this.getDefaultFolderId();
        },

        disabled(newValue) {
            if (newValue) {
                this.isDragActive = false;
            }
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
        async createdComponent() {
            this.mediaService.addListener(this.uploadTag, this.handleMediaServiceUploadEvent);
            if (this.mediaFolderId) {
                return;
            }

            if (this.defaultFolder) {
                this.defaultFolderId = await this.getDefaultFolderId();
            }

            this.configService.getConfig().then((result) => {
                this.isUploadUrlFeatureEnabled = result.settings.enableUrlFeature;
            });
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
            this.mediaService.removeByTag(this.uploadTag);
            this.mediaService.removeListener(this.uploadTag, this.handleMediaServiceUploadEvent);

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
            if (this.disabled) {
                return;
            }

            const newMediaFiles = Array.from(event.dataTransfer.files);
            this.isDragActive = false;

            if (newMediaFiles.length === 0) {
                return;
            }

            this.handleFileCheck(newMediaFiles);
        },

        onDropMedia(dragData) {
            if (this.disabled) {
                return;
            }

            this.$emit('media-drop', dragData.mediaItem);
        },

        onDragEnter() {
            if (this.disabled) {
                return;
            }

            this.isDragActive = true;
        },

        onDragLeave(event) {
            if (event.screenX === 0 && event.screenY === 0) {
                this.isDragActive = false;
                return;
            }

            const target = event.target;

            if (target.closest('.sw-media-upload-v2__dropzone')) {
                return;
            }

            this.isDragActive = false;
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
            if (this.disabled) {
                return;
            }

            this.preview = null;
            this.$emit('media-upload-remove-image');
        },

        /*
         * entry points
         */
        async onUrlUpload({ url, fileExtension }) {
            if (!this.multiSelect) {
                this.mediaService.removeByTag(this.uploadTag);
                this.preview = url;
            }

            let fileInfo;

            try {
                fileInfo = fileReader.getNameAndExtensionFromUrl(url);
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('global.sw-media-upload-v2.notification.invalidUrl.message'),
                });

                return;
            }

            if (fileExtension) {
                fileInfo.extension = fileExtension;
            }

            const targetEntity = this.getMediaEntityForUpload();

            await this.mediaRepository.save(targetEntity, Context.api);
            this.mediaService.addUpload(this.uploadTag, {
                src: url,
                targetId: targetEntity.id,
                isPrivate: targetEntity.private,
                ...fileInfo,
            });

            this.useFileUpload();
        },

        onFileInputChange() {
            const newMediaFiles = Array.from(this.$refs.fileInput.files);

            if (!newMediaFiles.length) {
                return;
            }

            this.handleFileCheck(newMediaFiles);

            this.$refs.fileForm.reset();
        },

        /*
         * Helper functions
         */
        async handleUpload(newMediaFiles) {
            if (!this.multiSelect) {
                this.mediaService.removeByTag(this.uploadTag);
                newMediaFiles = [newMediaFiles.pop()];
                this.preview = newMediaFiles[0];
            } else {
                if (!this.preview) {
                    this.preview = [];
                }

                if (this.addFilesOnMultiselect) {
                    this.preview = [...this.preview, ...newMediaFiles];
                } else {
                    this.preview = newMediaFiles;
                }
            }

            const syncEntities = [];

            const uploadData = newMediaFiles.map((fileHandle) => {
                const { fileName, extension } = fileReader.getNameAndExtensionFromFile(fileHandle);
                const targetEntity = this.getMediaEntityForUpload();
                syncEntities.push(targetEntity);

                return { src: fileHandle, targetId: targetEntity.id, fileName, extension, isPrivate: targetEntity.private };
            });

            await this.mediaRepository.saveAll(syncEntities, Context.api);
            await this.mediaService.addUploads(this.uploadTag, uploadData);
        },

        getMediaEntityForUpload() {
            const mediaItem = this.mediaRepository.create();
            mediaItem.mediaFolderId = this.mediaFolderId;
            mediaItem.private = this.privateFilesystem;

            return mediaItem;
        },

        async getDefaultFolderId() {
            const criteria = new Criteria(1, 1)
                .addFilter(Criteria.equals('entity', this.defaultFolder));

            const items = await this.defaultFolderRepository.search(criteria, Context.api);
            if (items.length !== 1) {
                return null;
            }
            const defaultFolder = items[0];

            if (defaultFolder.folder?.id) {
                return defaultFolder.folder.id;
            }

            return null;
        },

        handleMediaServiceUploadEvent({ action }) {
            if (action === 'media-upload-fail') {
                this.onRemoveMediaItem();
            }
        },

        checkFileSize(file) {
            if (this.maxFileSize === null || file.size <= this.maxFileSize || file.fileSize <= this.maxFileSize) {
                return true;
            }

            this.createNotificationError({
                title: this.$tc('global.default.error'),
                message: this.$tc('global.sw-media-upload-v2.notification.invalidFileSize.message', 0, {
                    name: file.name || file.fileName,
                    limit: fileSize(this.maxFileSize),
                }),
            });
            return false;
        },

        checkFileType(file) {
            if (!this.fileAccept || this.fileAccept === '*/*') {
                return true;
            }

            const fileTypes = this.fileAccept.replaceAll(' ', '').split(',');

            const isCorrectFileType = fileTypes.some(fileType => {
                const fileAcceptType = fileType.split('/');
                const currentFileType = file?.type?.split('/') || file?.mimeType?.split('/');

                if (fileAcceptType[0] !== currentFileType[0] && fileAcceptType[0] !== '*') {
                    return false;
                }

                if (fileAcceptType[1] === '*') {
                    return true;
                }

                return fileAcceptType[1] === currentFileType[1];
            });

            if (isCorrectFileType) {
                return true;
            }

            this.createNotificationError({
                title: this.$tc('global.default.error'),
                message: this.$tc('global.sw-media-upload-v2.notification.invalidFileType.message', 0, {
                    name: file.name || file.fileName,
                    supportedTypes: this.fileAccept,
                }),
            });
            return false;
        },

        handleFileCheck(files) {
            const checkedFiles = files.filter((file) => {
                return this.checkFileSize(file) && this.checkFileType(file);
            });


            if (this.useFileData) {
                this.preview = !this.multiSelect ? checkedFiles[0] : null;
                this.$emit('media-upload-add-file', checkedFiles);
            } else {
                this.handleUpload(checkedFiles);
            }
        },
    },
};
