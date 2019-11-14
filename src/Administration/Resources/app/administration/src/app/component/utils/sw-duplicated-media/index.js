import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-duplicated-media.html.twig';
import './sw-duplicated-media.scss';

const { Component, StateDeprecated, Filter } = Shopware;

/**
 * @private
 */

const LOCAL_STORAGE_KEY_OPTION = 'sw-duplicate-media-resolve-option';
const LOCAL_STORAGE_SAVE_SELECTION = 'sw-duplicate-media-resolve-save-selection';

Component.register('sw-duplicated-media', {
    template,

    inject: [
        'mediaService'
    ],

    data() {
        return {
            isLoading: false,
            shouldSaveSelection: false,
            selectedOption: 'Replace',
            suggestedName: '',
            existingMedia: null,
            targetEntity: null,
            failedUploadTasks: [],
            postponedFailedUploads: []
        };
    },

    computed: {
        mediaStore() {
            return StateDeprecated.getStore('media');
        },

        uploadStore() {
            return StateDeprecated.getStore('upload');
        },

        additionalErrorCount() {
            return this.failedUploadTasks.length - 1;
        },

        hasAdditionalErrors() {
            return this.additionalErrorCount > 0;
        },

        currentTask() {
            return this.failedUploadTasks[0];
        },

        buttonLabel() {
            return this.$tc(`global.sw-duplicated-media.button${this.selectedOption}`);
        },

        dateFilter() {
            return Filter.getByName('date');
        },

        fileSizeFilter() {
            return Filter.getByName('fileSize');
        },

        currentTaskDetails() {
            if (!this.currentTask) {
                return '';
            }
            const metadata = [
                this.dateFilter(new Date(), { month: 'long' })
            ];

            if (this.currentTask.src instanceof File) {
                metadata.push(this.fileSizeFilter(this.currentTask.src.size));
            }

            return metadata.join(', ');
        },

        showModal() {
            return this.failedUploadTasks.length > 0 && !this.isWorkingOnMultipleTasks;
        },

        isWorkingOnMultipleTasks() {
            return this.isLoading && this.shouldSaveSelection;
        },

        options() {
            return [
                {
                    value: 'Replace',
                    name: this.$tc('global.sw-duplicated-media.labelOptionReplace')
                },
                {
                    value: 'Rename',
                    name: this.$tc('global.sw-duplicated-media.labelOptionRename')
                },
                {
                    value: 'Skip',
                    name: this.$tc('global.sw-duplicated-media.labelOptionSkip')
                }
            ];
        }
    },

    watch: {
        currentTask() {
            this.updatePreviewData();
        },

        showModal(newVal) {
            if (newVal) {
                this.loadDefaultOption();
                return;
            }

            this.saveDefaultOption();
        },

        isLoading(newVal) {
            if (newVal) {
                return;
            }

            this.failedUploadTasks.push(...this.postponedFailedUploads.splice(0, this.postponedFailedUploads.length));
        }
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.beforeDestroyComponent();
    },

    methods: {
        createdComponent() {
            this.loadDefaultOption();
            this.updatePreviewData();

            this.uploadStore.addDefaultListener(this.handleUploadStoreEvent);
        },

        beforeDestroyComponent() {
            this.uploadStore.removeDefaultListener(this.handleUploadStoreEvent);
        },

        loadDefaultOption() {
            this.shouldSaveSelection = localStorage.getItem(LOCAL_STORAGE_SAVE_SELECTION) || false;
            if (this.shouldSaveSelection) {
                this.defaultOption = localStorage.getItem(LOCAL_STORAGE_KEY_OPTION) || 'Replace';
            }
            this.selectedOption = this.defaultOption || 'Replace';
        },

        saveDefaultOption() {
            localStorage.setItem(LOCAL_STORAGE_SAVE_SELECTION, this.shouldSaveSelection);
            if (this.shouldSaveSelection) {
                localStorage.setItem(LOCAL_STORAGE_KEY_OPTION, this.defaultOption);
            }
        },

        handleUploadStoreEvent({ action, payload }) {
            if (action !== 'media-upload-fail') {
                return;
            }

            if (!this.isDuplicatedNameError(payload.error)) {
                return;
            }

            if (this.isLoading) {
                this.postponedFailedUploads.push(payload);
                return;
            }

            this.failedUploadTasks.push(payload);
        },

        isDuplicatedNameError(error) {
            return error.response.data.errors.some((err) => {
                return err.code === 'CONTENT__MEDIA_DUPLICATED_FILE_NAME';
            });
        },

        updatePreviewData() {
            if (!this.currentTask) {
                this.existingMedia = null;
                this.suggestedName = '';
                return;
            }

            this.mediaStore.getList({
                page: 1,
                limit: 1,
                criteria: CriteriaFactory.multi('AND',
                    CriteriaFactory.equals('fileName', this.currentTask.fileName),
                    CriteriaFactory.equals('fileExtension', this.currentTask.extension))
            }).then((response) => {
                this.existingMedia = response.items[0];
            });

            this.mediaService.provideName(this.currentTask.fileName, this.currentTask.extension).then((response) => {
                this.suggestedName = response.fileName;
            });
        },

        solveDuplicate() {
            if (!this.currentTask) {
                this.isLoading = false;
                return Promise.resolve();
            }

            this.isLoading = true;
            let solvingPromise = null;
            switch (this.selectedOption) {
                case 'Rename':
                    solvingPromise = this.renameFile(this.currentTask);
                    break;
                case 'Replace':
                    solvingPromise = this.replaceFile(this.currentTask);
                    break;
                case 'Skip':
                default:
                    solvingPromise = this.skipFile(this.currentTask);
                    break;
            }

            return solvingPromise.then(() => {
                this.failedUploadTasks.splice(0, 1);
                if (this.shouldSaveSelection) {
                    return this.solveDuplicate();
                }
                this.isLoading = false;
                return Promise.resolve();
            }).catch((cause) => {
                this.isLoading = false;
                return Promise.reject(cause);
            });
        },

        renameFile(uploadTask) {
            const newTask = Object.assign({}, uploadTask);
            return this.mediaService.provideName(uploadTask.fileName, uploadTask.extension).then(({ fileName }) => {
                newTask.fileName = fileName;
                this.uploadStore.addUpload(newTask.uploadTag, newTask);
                return this.uploadStore.runUploads(newTask.uploadTag);
            });
        },

        skipAll() {
            this.isLoading = true;

            return this.skipFile(this.currentTask).then(() => {
                this.failedUploadTasks.splice(0, 1);
                if (this.currentTask) {
                    return this.skipAll();
                }

                this.isLoading = false;
                return Promise.resolve();
            }).catch(() => {
                this.isLoading = false;
                return Promise.reject();
            });
        },

        skipCurrentFile() {
            this.isLoading = true;
            this.skipFile(this.currentTask).then(() => {
                this.failedUploadTasks.splice(0, 1);
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        skipFile(uploadTask) {
            return this.mediaStore.getByIdAsync(uploadTask.targetId).then((oldTarget) => {
                if (!oldTarget.hasFile) {
                    oldTarget.delete(true);
                }
            });
        },

        replaceFile(uploadTask) {
            return this.mediaStore.getList({
                page: 1,
                limit: 1,
                criteria: CriteriaFactory.multi('AND',
                    CriteriaFactory.equals('fileName', uploadTask.fileName),
                    CriteriaFactory.equals('fileExtension', uploadTask.extension))
            }).then((response) => {
                const newTarget = response.items[0];
                const oldTargetId = uploadTask.targetId;
                uploadTask.targetId = newTarget.id;

                this.uploadStore.addUpload(uploadTask.uploadTag, uploadTask);
                return this.uploadStore.runUploads(uploadTask.uploadTag).then(() => {
                    return this.mediaStore.getByIdAsync(oldTargetId).then((oldTarget) => {
                        if (!oldTarget.hasFile) {
                            oldTarget.delete(true);
                        }
                        return this.mediaStore.getByIdAsync(uploadTask.targetId);
                    });
                });
            });
        }
    }
});
