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
        Mixin.getByName('placeholder')
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
        mediaStore() {
            return State.getStore('media');
        },

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

        emitOpenFolderDissolve() {
            this.$emit('sw-media-quickinfo-open-folder-dissolve');
        },

        emitOpenFolderMove() {
            this.$emit('sw-media-quickinfo-open-folder-move');
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

        onChangeFolderName(value) {
            this.item.name = value;
            this.item.save().catch(() => {
                this.$refs.inlineEditFieldName.cancelSubmit();
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
