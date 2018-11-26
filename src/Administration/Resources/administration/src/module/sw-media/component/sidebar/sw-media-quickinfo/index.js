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
        url() {
            if (this.item === null) {
                return '';
            }

            return this.item.url;
        },

        fileSize() {
            return format.fileSize(this.item.fileSize);
        },

        createdAt() {
            let date = this.item.uploadedAt;

            if (!date) {
                date = this.item.createdAt;
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
        }
    }
});
