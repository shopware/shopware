import { Component } from 'src/core/shopware';
import { format } from 'src/core/service/util.service';
import domUtils from 'src/core/service/utils/dom.utils';
import '../../sw-media-collapse';
import template from './sw-media-quickinfo.html.twig';
import './sw-media-quickinfo.less';

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
        getMetadata() {
            if (this.item === null) {
                return {};
            }

            return {
                fileName: this.item.name,
                mimeType: this.item.mimeType,
                fileSize: this.item.fileSize,
                createdAt: format.date(this.item.createdAt),
                url: this.item.url
            };
        },

        getUrl() {
            if (this.item === null) {
                return '';
            }

            return this.item.url;
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
