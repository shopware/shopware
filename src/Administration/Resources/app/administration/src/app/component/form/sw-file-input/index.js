import template from './sw-file-input.html.twig';
import './sw-file-input.scss';

const { Component, Mixin } = Shopware;
const { fileSize } = Shopware.Utils.format;
const utils = Shopware.Utils;

/**
 * @description The <u>sw-file-input</u> component can be used wherever a file input is needed.
 * @example-type code-only
 * @component-example
 * <sw-file-input
 *     v-model="selectedFile"
 *     label="My file input"
 *     :allowedMimeTypes="['text/csv','text/xml']"
 *     :maxFileSize="8*1024*1024">
 * </sw-file-input>
 */
Component.register('sw-file-input', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    model: {
        prop: 'value',
        event: 'change'
    },

    props: {
        maxFileSize: {
            type: Number,
            required: false,
            default: null
        },

        allowedMimeTypes: {
            type: Array,
            required: false
        },

        label: {
            type: String,
            required: false
        },

        value: {
            required: false
        }
    },

    data() {
        return {
            selectedFile: null,
            utilsId: utils.createId(),
            fileDragover: false
        };
    },

    computed: {
        id() {
            return `sw-file-input--${this.utilsId}`;
        },

        dropzoneClasses() {
            return {
                'sw-file-input__dropzone-dragover': this.fileDragover
            };
        },

        fileSize() {
            if (!this.selectedFile || !this.selectedFile.size) {
                return '';
            }

            return fileSize(this.selectedFile.size);
        }
    },

    methods: {
        onChooseButtonClick() {
            this.$refs.fileInput.click();
        },

        onRemoveIconClick() {
            this.setSelectedFile(null);
        },

        onFileInputChange() {
            const newFiles = Array.from(this.$refs.fileInput.files);
            this.setFile(newFiles);
            this.$refs.fileForm.reset();
        },

        setFile(files) {
            if (files.length <= 0) {
                return;
            }

            const newFile = files[0];
            if (this.checkFileSize(newFile) && this.checkFileType(newFile)) {
                this.setSelectedFile(newFile);
            }
        },

        setSelectedFile(newFile) {
            this.selectedFile = newFile;
            this.$emit('change', this.selectedFile);
        },

        checkFileSize(file) {
            if (this.maxFileSize === null || file.size <= this.maxFileSize) {
                return true;
            }

            this.createNotificationError({
                title: this.$tc('global.sw-file-input.notification.invalidFileSize.title'),
                message: this.$tc('global.sw-file-input.notification.invalidFileSize.message', 0, {
                    name: file.name,
                    limit: fileSize(this.maxFileSize)
                })
            });
            return false;
        },

        checkFileType(file) {
            if (!this.allowedMimeTypes || !this.allowedMimeTypes.length || this.allowedMimeTypes.indexOf(file.type) >= 0) {
                return true;
            }

            this.createNotificationError({
                title: this.$tc('global.sw-file-input.notification.invalidFileType.title'),
                message: this.$tc('global.sw-file-input.notification.invalidFileType.message', 0, {
                    name: file.name,
                    supportedTypes: this.allowedMimeTypes.join(', ')
                })
            });
            return false;
        },

        onDropFile(event) {
            this.fileDragover = false;

            if (!event || !event.dataTransfer || !event.dataTransfer.files) {
                return;
            }

            this.setFile(event.dataTransfer.files);
        },

        onDragEnter() {
            this.fileDragover = true;
        },

        onDragLeave() {
            console.log('onDragLeave');
            this.fileDragover = false;
        }
    }
});
