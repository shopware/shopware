import { Component } from 'src/core/shopware';
import { format } from 'src/core/service/util.service';
import template from './sw-media-quickinfo-multiple.html.twig';
import './sw-media-quickinfo-multiple.less';

Component.register('sw-media-quickinfo-multiple', {
    template,

    props: {
        items: {
            required: false,
            type: Array
        }
    },

    computed: {
        itemsIsAvailable() {
            return this.items !== undefined && this.items !== null && this.items.length > 0;
        },

        getFileSize() {
            const sizeInByte = this.items.reduce((value, items) => {
                return value + items.fileSize;
            }, 0);

            return format.fileSize(sizeInByte);
        },

        getFileSizeLabel() {
            return `${this.$tc('sw-media.sidebar.metadata.fileCount', this.items.length, { count: this.items.length })}, 
                    ${this.$tc('sw-media.sidebar.metadata.totalSize')}: 
                    ${this.getFileSize}`;
        },

        hasFolder() {
            return this.items.some((item) => {
                return item.entityName === 'media_folder';
            });
        },

        hasMedia() {
            return this.items.some((item) => {
                return item.entityName === 'media';
            });
        }
    },

    methods: {
        emitOpenModalDelete() {
            this.$emit('sw-media-quickinfo-open-modal-delete');
        },

        emitOpenFolderDissolve() {
            this.$emit('sw-media-quickinfo-open-folder-dissolve');
        }
    }
});
