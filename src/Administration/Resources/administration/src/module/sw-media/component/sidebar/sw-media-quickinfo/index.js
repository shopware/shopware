import { Component, Mixin, State } from 'src/core/shopware';
import { format } from 'src/core/service/util.service';
import domUtils from 'src/core/service/utils/dom.utils';
import template from './sw-media-quickinfo.html.twig';
import './sw-media-quickinfo.scss';

Component.register('sw-media-quickinfo', {
    template,

    inject: ['mediaService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('media-sidebar-modal-mixin'),
        Mixin.getByName('placeholder')
    ],

    props: {
        item: {
            required: true,
            type: Object,
            validator(value) {
                return value.entityName === 'media';
            }
        },

        editable: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        mediaStore() {
            return State.getStore('media');
        },

        isMediaObject() {
            return this.item.type === 'media';
        },

        fileSize() {
            return format.fileSize(this.item.fileSize);
        },

        createdAt() {
            const date = this.item.uploadedAt || this.item.createdAt;
            return format.date(date);
        }
    },

    methods: {
        copyLinkToClipboard() {
            if (this.item) {
                domUtils.copyToClipboard(this.item.url);
                this.createNotificationSuccess({
                    title: this.$tc('sw-media.general.notification.urlCopied.title'),
                    message: this.$tc('sw-media.general.notification.urlCopied.message')
                });
            }
        },

        onSubmitTitle(value) {
            this.item.title = value;
            this.item.save().catch(() => {
                this.$refs.inlineEditFieldTitle.cancelSubmit();
            });
        },

        onSubmitAltText(value) {
            this.item.alt = value;
            this.item.save().catch(() => {
                this.$refs.inlineEditFieldAlt.cancelSubmit();
            });
        },

        onChangeFileName(value) {
            this.item.isLoading = true;
            const oldFileName = this.item.fileName;

            return this.mediaService.renameMedia(this.item.id, value).then(() => {
                this.mediaStore.getByIdAsync(this.item.id);
            }).catch(() => {
                this.item.fileName = oldFileName;
                this.item.isLoading = false;
                this.$refs.inlineEditFieldName.cancelSubmit();
                this.createNotificationError({ message: 'Could not rename FileName' });
            });
        }
    }
});
