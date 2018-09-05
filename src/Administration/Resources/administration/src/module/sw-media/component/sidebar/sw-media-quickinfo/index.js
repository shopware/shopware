import { Component } from 'src/core/shopware';
import { format } from 'src/core/service/util.service';
import domUtils from 'src/core/service/utils/dom.utils';
import '../../sw-media-collapse';
import template from './sw-media-quickinfo.html.twig';
import './sw-media-quickinfo.less';
import '../sw-media-quickinfo-metadata-item';

Component.register('sw-media-quickinfo', {
    template,

    props: {
        item: {
            required: false,
            type: Object,
            validator(value) {
                return value.type === 'media';
            }
        },

        autoplay: {
            required: false,
            type: Boolean,
            default: false
        }
    },

    computed: {
        getUrl() {
            if (this.item === null) {
                return '';
            }

            return this.item.url;
        },

        getFileSize() {
            return format.fileSize(this.item.fileSize);
        },

        getCreatedAt() {
            return format.date(this.item.createdAt);
        }
    },

    methods: {
        emitQuickAction(originalDomEvent, action) {
            this.$emit(`sw-media-sidebar-quickaction-${action}`, {
                originalDomEvent,
                item: this.item
            });
        },

        copyLinkToClipboard() {
            if (this.item) {
                domUtils.copyToClipboard(this.item.url);
            }
        }
    }
});
