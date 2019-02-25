import { State, Filter } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-duplicated-media.html.twig';
import './sw-duplicated-media.scss';

export default {
    name: 'sw-duplicated-media',
    template,

    inject: [
        'mediaService'
    ],

    data() {
        return {
            isLoading: false,
            localStorageKeyOption: 'sw-duplicate-media-resolve-option',
            localStorageKeySaveSelection: 'sw-duplicate-media-resolve-save-selection',
            uploadTag: 'sw-duplicate-media-resolver',
            shouldSaveSelection: false,
            selectedOption: 'Replace',
            duplicateName: '',
            existingMedia: null,
            targetEntity: null,
            failedUploadTasks: [],
            postponedFailedUploads: [],
            options: [
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
            ]
        };
    },

    computed: {
        mediaStore() {
            return State.getStore('media');
        },

        uploadStore() {
            return State.getStore('upload');
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
        this.componentCreated();
        this.uploadStore.addListener(this.uploadTag, this.handleUploadStoreEvent);
    },

    methods: {
        componentCreated() {
            this.loadDefaultOption();
            this.updatePreviewData();
        },

        loadDefaultOption() {
            this.shouldSaveSelection = localStorage.getItem(this.localStorageKeySaveSelection || false);
            if (this.shouldSaveSelection) {
                this.defaultOption = localStorage.getItem(this.localStorageKeyOption || 'Replace');
            }
            this.selectedOption = this.defaultOption || 'Replace';
        },

        saveDefaultOption() {
            localStorage.setItem(this.localStorageKeySaveSelection, this.shouldSaveSelection);
            if (this.shouldSaveSelection) {
                localStorage.setItem(this.localStorageKeyOption, this.defaultOption);
            }
        },

        handleUploadStoreEvent({ action, payload }) {
            if (action !== 'sw-media-upload-failed') {
                return;
            }

            if (this.isLoading) {
                this.postponedFailedUploads.push(payload);
                return;
            }

            this.failedUploadTasks.push(payload);
        },

        updatePreviewData() {
            if (!this.currentTask) {
                this.existingMedia = null;
                this.duplicateName = '';
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
                this.duplicateName = response.fileName;
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
                solvingPromise = this.skipCurrentFile();
                break;
            }

            return solvingPromise.then(() => {
                this.removeCurrentTask();
                if (this.shouldSaveSelection) {
                    return this.solveDuplicate();
                }
                this.isLoading = false;
                return Promise.resolve();
            }).catch(() => {
                this.isLoading = false;
            });
        },

        removeCurrentTask() {
            this.failedUploadTasks.splice(0, 1);
        },

        renameFile(uploadTask) {
            const newTask = Object.assign({}, uploadTask);
            return this.mediaService.provideName(uploadTask.fileName, uploadTask.extension).then(({ fileName }) => {
                newTask.fileName = fileName;
                this.uploadStore.addUpload(newTask.uploadTag, newTask);
                return this.uploadStore.runUploads(newTask.uploadTag);
            });
        },

        skipCurrentFile() {
            this.skipFile(this.currentTask).then(() => {
                this.failedUploadTasks.splice(0, 1);
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
};
