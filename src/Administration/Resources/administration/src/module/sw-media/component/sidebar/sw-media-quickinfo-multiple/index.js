import { Component } from 'src/core/shopware';
import '../../sw-media-collapse';
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
            return this.items.reduce((value, items) => {
                return value + items.fileSize;
            }, 0);
        },

        getFileSizeLabel() {
            return `${this.$tc('sw-media.sidebar.metadata.fileCount', this.items.length, { count: this.items.length })}, 
                    ${this.$tc('sw-media.sidebar.metadata.totalSize')}: 
                    ${this.getFileSize}byte`;
        }
    },

    methods: {
        emitQuickAction(originalDomEvent, action) {
            this.$emit(`sw-media-sidebar-${action}`, {
                originalDomEvent,
                item: this.items
            });
        }
    }
});
