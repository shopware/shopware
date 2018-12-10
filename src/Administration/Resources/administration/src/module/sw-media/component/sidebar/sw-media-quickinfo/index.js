import { Component, Mixin } from 'src/core/shopware';
import { format } from 'src/core/service/util.service';
import domUtils from 'src/core/service/utils/dom.utils';
import template from './sw-media-quickinfo.html.twig';
import './sw-media-quickinfo.less';

Component.register('sw-media-quickinfo', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        item: {
            required: false,
            type: Object,
            validator(value) {
                return ['media', 'media_folder'].includes(value.entityName);
            }
        },

        autoplay: {
            required: false,
            type: Boolean,
            default: false
        }
    },

    computed: {
        url() {
            if (this.item === null) {
                return '';
            }

            return this.item.url;
        },

        isMediaObject() {
            return this.item.type === 'media';
        },

        fileSize() {
            return format.fileSize(this.item.fileSize);
        },

        createdAt() {
            let date = this.item.createdAt;
            if (this.item.entityName === 'media' && this.item.uploadedAt) {
                date = this.item.uploadedAt;
            }

            return format.date(date);
        }
    },

    methods: {
        copyLinkToClipboard() {
            if (this.item) {
                domUtils.copyToClipboard(this.item.url);
                this.createNotificationSuccess({ message: this.$tc('sw-media.general.notificationUrlCopied') });
            }
        },

        emitOpenModalDelete() {
            this.$emit('sw-media-quickinfo-open-modal-delete');
        },

        emitOpenModalReplace() {
            this.$emit('sw-media-quickinfo-open-modal-replace');
        },

        emitOpenFolderSettings() {
            this.$emit('sw-media-quickinfo-open-folder-settings');
        },

        onSubmitTitleValue(value) {
            this.item.title = value;
            this.item.save();
        },

        onSubmitAltValue(value) {
            this.item.alt = value;
            this.item.save();
        }
    }
});
