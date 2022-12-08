/* eslint-disable vue/require-default-prop */
import template from './sw-file-input.html.twig';
import './sw-file-input.scss';

const { Component, Mixin } = Shopware;
const { fileSize } = Shopware.Utils.format;
const utils = Shopware.Utils;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
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
        Mixin.getByName('notification'),
    ],

    model: {
        prop: 'value',
        event: 'change',
    },

    props: {
        maxFileSize: {
            type: Number,
            required: false,
            default: null,
        },

        allowedMimeTypes: {
            type: Array,
            required: false,
            default: null,
        },

        label: {
            type: String,
            required: false,
            default: null,
        },

        // FIXME: add property type and prop default value
        // eslint-disable-next-line vue/require-prop-types
        value: {
            required: false,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            selectedFile: null,
            utilsId: utils.createId(),
            isDragActive: false,
        };
    },

    computed: {
        id() {
            return `sw-file-input--${this.utilsId}`;
        },

        isDragActiveClass() {
            return {
                'is--active': this.isDragActive,
            };
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
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

        onChooseButtonClick() {
            this.$refs.fileInput.click();
        },

        onRemoveIconClick() {
            this.setSelectedFile(null);
        },

        onFileInputChange() {
            const newFiles = Array.from(this.$refs.fileInput.files);

            if (newFiles.length) {
                const newFile = newFiles[0];
                if (this.checkFileSize(newFile) && this.checkFileType(newFile)) {
                    this.setSelectedFile(newFile);
                }
            }
            this.$refs.fileForm.reset();
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
                title: this.$tc('global.default.error'),
                message: this.$tc('global.sw-file-input.notification.invalidFileSize.message', 0, {
                    name: file.name,
                    limit: fileSize(this.maxFileSize),
                }),
            });
            return false;
        },

        checkFileType(file) {
            if (!this.allowedMimeTypes || !this.allowedMimeTypes.length || this.allowedMimeTypes.indexOf(file.type) >= 0) {
                return true;
            }

            this.createNotificationError({
                title: this.$tc('global.default.error'),
                message: this.$tc('global.sw-file-input.notification.invalidFileType.message', 0, {
                    name: file.name,
                    supportedTypes: this.allowedMimeTypes.join(', '),
                }),
            });
            return false;
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

            if (target.closest('.sw-file-input__dropzone')) {
                return;
            }

            this.isDragActive = false;
        },

        stopEventPropagation(event) {
            event.preventDefault();
            event.stopPropagation();
        },

        onDrop(event) {
            if (this.disabled) {
                return;
            }

            const newFiles = Array.from(event.dataTransfer.files);
            this.isDragActive = false;

            if (newFiles.length === 0) {
                return;
            }

            const newFile = newFiles[0];

            if (this.checkFileSize(newFile) && this.checkFileType(newFile)) {
                this.setSelectedFile(newFile);
            }

            this.$refs.fileForm.reset();
        },
    },
});
