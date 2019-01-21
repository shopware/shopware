import { Mixin, State } from 'src/core/shopware';
import util, { fileReader } from 'src/core/service/util.service';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import { next1207 } from 'src/flag/feature_next1207';
import template from './sw-media-upload.html.twig';
import './sw-media-upload.less';

/**
 * @status ready
 * @description The <u>sw-media-upload</u> component is used wherever an upload is needed. It supports drag & drop-,
 * file- and url-upload and comes in various forms.
 * @example-type dynamic
 * @component-example
 * <sw-media-upload
 *     uploadTag="Lorem ipsum dolor sit amet"
 *     variant="regular"
 *     allowMultiSelect="false"
 *     variant="regular"
 *     autoUpload="true"
 *     label="My image-upload">
 * </sw-media-upload>
 */
export default {
    name: 'sw-media-upload',
    template,

    inject: ['mediaUploadService', 'mediaService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        variant: {
            type: String,
            required: true,
            validValues: ['compact', 'regular'],
            validator(value) {
                return ['compact', 'regular'].includes(value);
            },
            default: 'regular'
        },

        uploadTag: {
            required: false,
            type: String,
            default: util.createId()
        },

        allowMultiSelect: {
            required: false,
            type: Boolean,
            default: true
        },

        label: {
            required: false,
            type: String
        },

        scrollTarget: {
            required: false,
            type: HTMLElement,
            default: null
        },

        defaultFolder: {
            required: false,
            type: String,
            validator(value) {
                return value.length > 0;
            },
            default: null
        }
    },

    data() {
        return {
            multiSelect: this.allowMultiSelect,
            showUrlInput: false,
            previewMediaEntity: null,
            isDragActive: false,
            defaultFolderPromise: Promise.resolve(null),
            showDuplicatedMediaModal: false,
            errorFiles: [],
            retryBatchAction: null,
            localStorageKey: 'sw-duplicate-media-resolve-option'
        };
    },

    computed: {
        mediaItemStore() {
            return State.getStore('media');
        },

        uploadStore() {
            return State.getStore('upload');
        },

        defaultFolderStore() {
            return State.getStore('media_default_folder');
        },

        folderStore() {
            return State.getStore('media_folder');
        },

        folderConfigurationStore() {
            return State.getStore('media_folder_configuration');
        },

        showPreview() {
            return !this.multiSelect;
        },

        hasPreviewFile() {
            return this.previewMediaEntity !== null;
        },

        toggleButtonCaption() {
            return this.showUrlInput ?
                this.$tc('global.sw-media-upload.buttonSwitchToFileUpload') :
                this.$tc('global.sw-media-upload.buttonSwitchToUrlUpload');
        },

        hasOpenSidebarButtonListener() {
            return Object.keys(this.$listeners).includes('sw-media-upload-open-sidebar');
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
        }
    },

    created() {
        this.onCreated();
    },

    mounted() {
        this.onMounted();
    },

    beforeDestroy() {
        this.onBeforeDestroy();
    },

    methods: {
        onCreated() {
            if (this.defaultFolder !== null) {
                this.defaultFolderPromise = this.defaultFolderStore.getList({
                    limit: 1,
                    criteria: CriteriaFactory.equals('entity', this.defaultFolder)
                }).then((response) => {
                    if (response.total !== 1) {
                        return null;
                    }

                    return response.items[0];
                });
            }
        },

        onMounted() {
            if (this.$refs.dropzone) {
                ['dragover', 'drop'].forEach((event) => {
                    window.addEventListener(event, this.stopEventPropagation, false);
                });
                this.$refs.dropzone.addEventListener('drop', this.onDrop);

                window.addEventListener('dragenter', this.onDragEnter);
                window.addEventListener('dragleave', this.onDragLeave);
            }
        },

        onBeforeDestroy() {
            this.uploadStore.removeByTag(this.uploadTag);

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
            this.handleUpload(newMediaFiles);

            this.isDragActive = false;
        },

        onDragEnter() {
            if (this.scrollTarget !== null && !this.isElementInViewport(this.$refs.dropzone)) {
                this.scrollTarget.scrollIntoView();
            }

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
            this.$emit('sw-media-upload-open-sidebar');
        },

        /*
         * entry points
         */
        onUrlUpload({ url, fileExtension }) {
            if (!this.multiSelect) {
                this.uploadStore.removeByTag(this.uploadTag);
                this.createPreviewFromUrl(url);
            }

            this.getMediaEntityForUpload().then((mediaEntity) => {
                this.uploadStore.addUpload(
                    this.uploadTag,
                    this.buildUrlUpload(
                        url,
                        fileExtension,
                        mediaEntity
                    )
                );

                this.$emit('sw-media-upload-new-uploads-added',
                    { uploadTag: this.uploadTag, data: [{ entity: mediaEntity, src: url }] });
            });

            this.closeUrlModal();
        },

        onFileInputChange() {
            const newMediaFiles = Array.from(this.$refs.fileInput.files);
            this.handleUpload(newMediaFiles);
            this.$refs.fileForm.reset();
        },

        /*
         * Helper functions
         */
        isElementInViewport(el) {
            const rect = el.getBoundingClientRect();

            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        },

        handleUpload(newMediaFiles) {
            if (!this.multiSelect) {
                this.uploadStore.removeByTag(this.uploadTag);

                const fileToUpload = newMediaFiles.pop();
                this.createPreviewFromFile(fileToUpload);
                newMediaFiles = [fileToUpload];
            }

            const data = [];

            const p = newMediaFiles.reduce((promise, file) => {
                return promise.then(() => {
                    return this.getMediaEntityForUpload().then((mediaEntity) => {
                        this.uploadStore.addUpload(this.uploadTag, this.buildFileUpload(file, mediaEntity));
                        data.push({
                            entity: mediaEntity,
                            src: file
                        });
                    });
                });
            }, Promise.resolve());

            p.then(() => {
                this.$emit('sw-media-upload-new-uploads-added', { uploadTag: this.uploadTag, data });
            });
        },

        getMediaEntityForUpload() {
            const mediaItem = this.mediaItemStore.create();
            if (this.defaultFolder !== null && next1207()) {
                return this.getDefaultFolderId().then((folderId) => {
                    mediaItem.mediaFolderId = folderId;

                    return mediaItem;
                });
            }

            return Promise.resolve(mediaItem);
        },

        getDefaultFolderId() {
            return this.defaultFolderPromise.then((defaultFolder) => {
                if (defaultFolder === null) {
                    return Promise.resolve(null);
                }

                if (defaultFolder.folderId === null) {
                    const folder = this.createFolder(defaultFolder);
                    defaultFolder.folderId = folder.id;

                    return folder.save().then(() => {
                        return defaultFolder.folderId;
                    });
                }

                return Promise.resolve(defaultFolder.folderId);
            });
        },

        createFolder(defaultFolder) {
            const folder = this.folderStore.create();
            const entityNameIdentifier = `global.entities.${defaultFolder.entity}`;
            folder.name = `${this.$tc(entityNameIdentifier)} ${this.$tc('global.entities.media', 2)}`;
            const configuration = this.folderConfigurationStore.create();
            configuration.createThumbnails = true;
            configuration.keepProportions = true;
            configuration.thumbnailQuality = 80;
            folder.configuration = configuration;
            folder.useParentConfiguration = false;

            folder.getAssociation('defaultFolders').add(defaultFolder);
            folder.defaultFolders.push(defaultFolder);

            return folder;
        },

        buildFileUpload(file, mediaEntity, fileName = '') {
            const successMessage = this.$tc('global.sw-media-upload.notificationSuccess');
            const failureMessage = this.$tc('global.sw-media-upload.notificationFailure');

            return () => {
                return this.mediaUploadService.uploadFileToMedia(file, mediaEntity, fileName).then(() => {
                    this.notifyMediaUpload(mediaEntity, successMessage);
                }).catch((error) => {
                    this.handleError(error, mediaEntity, failureMessage, file);
                });
            };
        },

        buildUrlUpload(url, fileExtension, mediaEntity, fileName = '') {
            const successMessage = this.$tc('global.sw-media-upload.notificationSuccess');
            const failureMessage = this.$tc('global.sw-media-upload.notificationFailure');

            return () => {
                return this.mediaUploadService.uploadUrlToMedia(url, mediaEntity, fileExtension, fileName).then(() => {
                    this.notifyMediaUpload(mediaEntity, successMessage);
                }).catch((error) => {
                    return this.handleError(error, mediaEntity, failureMessage, url);
                });
            };
        },

        createPreviewFromFile(file) {
            fileReader.readAsDataURL(file).then((dataUrl) => {
                this.previewMediaEntity = {
                    dataUrl,
                    mimeType: file.type,
                    hasFile: true
                };
            });
        },

        createPreviewFromUrl(url) {
            this.previewMediaEntity = {
                url: url.href,
                mimeType: 'image/*',
                hasFile: true
            };
        },

        notifyMediaUpload(mediaEntity, message) {
            this.mediaItemStore.getByIdAsync(mediaEntity.id).then((media) => {
                this.createNotificationSuccess({ message });
                this.$emit('sw-media-upload-media-upload-success', media);
            });
        },

        /*
         * Handling for duplicated file names
         */
        handleError(error, mediaEntity, message, src) {
            if (this.hasDuplicateMediaError(error)) {
                this.handleDuplicateMediaError(mediaEntity, src);
            } else {
                this.createNotificationError({ message });
            }

            this.$emit('media-upload-failure', mediaEntity);
            this.previewMediaEntity = null;
            mediaEntity.delete(true);
        },

        handleDuplicateMediaError(mediaEntity, src) {
            mediaEntity.isLoading = false;

            if (this.retryBatchAction) {
                this.retrySingle(
                    this.retryBatchAction,
                    mediaEntity.fileName,
                    mediaEntity.fileExtension,
                    src
                );
            } else {
                this.errorFiles.push({
                    id: mediaEntity.id,
                    entity: mediaEntity,
                    src: src
                });

                this.showDuplicatedMediaModal = true;
            }
            this.$emit('sw-media-upload-media-upload-failure', mediaEntity);
            this.previewMediaEntity = null;
            mediaEntity.delete(true);
        },

        hasDuplicateMediaError(error) {
            return error.response.data.errors.some((err) => {
                return err.code === 'DUPLICATED_MEDIA_FILE_NAME_EXCEPTION';
            });
        },

        retryUpload({ action, id, saveSelection, entityToReplace, newName }) {
            window.localStorage.setItem(this.localStorageKey, action);

            if (action !== 'Skip') {
                const errorFile = this.errorFiles.find((error) => {
                    return error.id === id;
                });

                if (action === 'Replace') {
                    this.handleReplace(errorFile.entity.fileExtension, errorFile.src, entityToReplace);
                } else if (action === 'Rename') {
                    this.handleRename(errorFile.entity.fileExtension, errorFile.src, newName);
                }
            }

            this.errorFiles = this.errorFiles.filter((error) => {
                return error.id !== id;
            });

            this.closeDuplicateMediaModal(saveSelection);

            if (saveSelection) {
                if (!this.uploadStore.isTagMissing(this.uploadTag)) {
                    this.retryBatchAction = action;
                }
                this.retryBatch(action);
            }
        },

        handleReplace(fileExtension, src, entityToReplace) {
            if (src instanceof URL) {
                this.buildUrlUpload(src, fileExtension, entityToReplace)();
            } else {
                this.buildFileUpload(src, entityToReplace)();
            }

            this.$emit('sw-media-upload-media-replaced', entityToReplace);
        },

        handleRename(fileExtension, src, newName) {
            this.getMediaEntityForUpload().then((entity) => {
                entity.save().then(() => {
                    if (src instanceof URL) {
                        this.buildUrlUpload(src, fileExtension, entity, newName)();
                    } else {
                        this.buildFileUpload(src, entity, newName)();
                    }

                    this.$emit('sw-media-upload-new-uploads-added', {
                        uploadTag: this.uploadTag,
                        data: [{
                            entity: entity,
                            src: src
                        }]
                    });
                });
            });
        },

        retryBatch(action) {
            if (action !== 'Skip') {
                this.errorFiles.forEach((error) => {
                    if (action === 'Replace') {
                        this.replaceByFileName(error.entity.fileName, error.entity.fileExtension, error.src);
                    } else {
                        this.renameFile(error.entity.fileName, error.entity.fileExtension, error.src);
                    }
                });
            }

            this.errorFiles = [];
        },

        retrySingle(action, fileName, fileExtension, src) {
            if (action === 'Replace') {
                this.replaceByFileName(fileName, fileExtension, src);
            } else {
                this.renameFile(fileName, fileExtension, src);
            }
        },

        replaceByFileName(fileName, fileExtension, src) {
            this.mediaItemStore.getList({
                page: 1,
                limit: 1,
                criteria: CriteriaFactory.multi('AND',
                    CriteriaFactory.equals('fileName', fileName),
                    CriteriaFactory.equals('fileExtension', fileExtension))
            }).then((response) => {
                this.handleReplace(fileExtension, src, response.items[0]);
            });
        },

        renameFile(fileName, fileExtension, src) {
            this.mediaService.provideName(fileName, fileExtension)
                .then((response) => {
                    this.handleRename(fileExtension, src, response.fileName);
                });
        },

        cancelDuplicateMedia({ id }) {
            this.errorFiles = this.errorFiles.filter((error) => {
                return error.id !== id;
            });

            this.closeDuplicateMediaModal();
        },

        closeDuplicateMediaModal(stayClosed) {
            this.showDuplicatedMediaModal = false;

            if (this.errorFiles.length > 0 && !stayClosed) {
                util.debounce(() => {
                    this.showDuplicatedMediaModal = true;
                }, 300)();
            }
        },

        onUploadsFinished() {
            this.retryBatchAction = null;
        },

        getDefaultDuplicateMediaOption() {
            return window.localStorage.getItem(this.localStorageKey) || 'Replace';
        }
    }
};
