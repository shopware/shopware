import { Component } from 'src/core/shopware';
import { format } from 'src/core/service/util.service';
import domUtils from 'src/core/service/utils/dom.utils';
import '../../sw-mediamanager-collapse';
import template from './sw-mediamanager-quickinfo.html.twig';
import './sw-mediamanager-quickinfo.less';

Component.register('sw-mediamanager-quickinfo', {
    template,

    props: {
        item: {
            required: false,
            type: Object,
            validator(value) {
                return value.type === 'media';
            }
        }
    },

    computed: {
        itemIsAvailable() {
            return this.item !== undefined && this.item !== null;
        },
        getMetadata() {
            if (!this.itemIsAvailable) {
                return {};
            }

            return {
                fileName: this.item.name,
                mimeType: this.item.mimeType,
                fileSize: this.item.fileSize,
                createdAt: format.date(this.item.createdAt),
                url: this.item.links.url
            };
        }
    },

    methods: {
        emitQuickAction(originalDomEvent, action) {
            this.$emit(`sw-mediamanager-sidebar-quickaction-${action}`, {
                originalDomEvent,
                item: this.item
            });
        },
        copyLinkToClipboard() {
            if (this.itemIsAvailable) {
                domUtils.copyToClipboard(this.item.links.url);
            }
        }
    }
});
