import template from './sw-duplicated-media-v2.html.twig';
import './sw-duplicated-media-v2.scss';

const { Component, Context, Filter } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 */

const LOCAL_STORAGE_KEY_OPTION = 'sw-duplicate-media-resolve-option';
const LOCAL_STORAGE_SAVE_SELECTION = 'sw-duplicate-media-resolve-save-selection';

Component.register('sw-duplicated-media-v2', {
    template,

    inject: ['repositoryFactory', 'mediaService'],

    data() {
        return {
            isLoading: false,
            shouldSaveSelection: false,
            selectedOption: 'Replace',
            suggestedName: '',
            existingMedia: null,
            targetEntity: null,
            failedUploadTasks: [],
            postponedFailedUploads: [],
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
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
            return this.$tc(`global.sw-duplicated-media-v2.button${this.selectedOption}`);
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
                this.dateFilter(new Date(), { month: 'long' }),
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
                    name: this.$tc('global.sw-duplicated-media-v2.labelOptionReplace'),
                },
                {
                    value: 'Rename',
                    name: this.$tc('global.sw-duplicated-media-v2.labelOptionRename'),
                },
                {
                    value: 'Skip',
                    name: this.$tc('global.sw-duplicated-media-v2.labelOptionSkip'),
                },
            ];
        },
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
        },
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

            this.mediaService.addDefaultListener(this.handleMediaServiceUploadEvent);
        },

        beforeDestroyComponent() {
            this.mediaService.removeDefaultListener(this.handleMediaServiceUploadEvent);
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

        handleMediaServiceUploadEvent({ action, payload }) {
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

        async updatePreviewData() {
            if (!this.currentTask) {
                this.existingMedia = null;
                this.suggestedName = '';
                return;
            }

            const criteria = new Criteria(1, 1)
                .addFilter(
                    Criteria.multi(
                        'AND',
                        [
                            Criteria.equals('fileName', this.currentTask.fileName),
                            Criteria.equals('fileExtension', this.currentTask.extension),
                        ],
                    ),
                );

            const searchResult = await this.mediaRepository.search(criteria, Context.api);
            if (searchResult?.[0]) {
                this.existingMedia = searchResult[0];
            }

            const provided = await this.mediaService.provideName(this.currentTask.fileName, this.currentTask.extension);
            this.suggestedName = provided.fileName;
        },

        solveDuplicate() {
            if (!this.currentTask) {
                this.isLoading = false;
                return;
            }

            this.isLoading = true;

            switch (this.selectedOption) {
                case 'Rename':
                    this.renameFile(this.currentTask);
                    break;
                case 'Replace':
                    this.replaceFile(this.currentTask);
                    break;
                case 'Skip':
                default:
                    this.skipFile(this.currentTask);
                    break;
            }

            this.failedUploadTasks.splice(0, 1);

            if (!this.currentTask) {
                this.isLoading = false;
            } else {
                this.solveDuplicate();
            }
        },

        async renameFile(uploadTask) {
            const newTask = Object.assign({}, uploadTask);

            const { fileName } = await this.mediaService.provideName(uploadTask.fileName, uploadTask.extension);
            newTask.fileName = fileName;

            this.mediaService.addUpload(newTask.uploadTag, newTask);
            await this.mediaService.runUploads(newTask.uploadTag);
        },

        skipAll() {
            this.isLoading = true;

            this.skipFile(this.currentTask);
            this.failedUploadTasks.splice(0, 1);

            if (!this.currentTask) {
                this.isLoading = false;
            } else {
                this.skipAll();
            }
        },

        skipCurrentFile() {
            this.isLoading = true;
            this.skipFile(this.currentTask);

            this.failedUploadTasks.splice(0, 1);
            this.isLoading = false;
        },

        async skipFile(uploadTask) {
            const oldTarget = await this.mediaRepository.get(uploadTask.targetId, Context.api);
            if (!oldTarget.hasFile) {
                await this.mediaRepository.delete(oldTarget.id, Context.api);
            }

            this.mediaService.cancelUpload(uploadTask.uploadTag, uploadTask);
        },

        async replaceFile(uploadTask) {
            const criteria = new Criteria(1, 1)
                .addFilter(Criteria.multi('AND',
                    [
                        Criteria.equals('fileName', uploadTask.fileName),
                        Criteria.equals('fileExtension', uploadTask.extension),
                    ]));

            const searchResult = await this.mediaRepository.search(criteria, Context.api);
            const newTarget = searchResult[0];
            const oldTargetId = uploadTask.targetId;
            uploadTask.targetId = newTarget.id;

            this.mediaService.addUpload(uploadTask.uploadTag, uploadTask);

            await this.mediaService.runUploads(uploadTask.uploadTag);
            const oldTarget = await this.mediaRepository.get(oldTargetId, Context.api);

            if (!oldTarget.hasFile) {
                await this.mediaRepository.delete(oldTargetId, Context.api);
            }

            await this.mediaRepository.get(uploadTask.targetId, Context.api);
        },
    },
});
