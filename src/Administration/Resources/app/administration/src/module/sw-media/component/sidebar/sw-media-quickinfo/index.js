import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-media-quickinfo.html.twig';
import './sw-media-quickinfo.scss';

const { Component, Mixin, StateDeprecated } = Shopware;
const { domUtils, format } = Shopware.Utils;

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
                return value.getEntityName() === 'media';
            }
        },

        editable: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            customFieldSets: [],
            isLoading: false,
            isSaveSuccessful: false,
            showModalReplace: false
        };
    },

    computed: {
        mediaStore() {
            return StateDeprecated.getStore('media');
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
        },
        customFieldSetStore() {
            return StateDeprecated.getStore('custom_field_set');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.customFieldSetStore.getList({
                page: 1,
                limit: 100,
                criteria: CriteriaFactory.equals('relations.entityName', 'media'),
                associations: {
                    customFields: {
                        limit: 100,
                        sort: 'config.customFieldPosition'
                    }
                }
            }, true).then(({ items }) => {
                this.customFieldSets = items.filter(set => set.customFields.length > 0);
            });
        },

        onSaveCustomFields(item) {
            this.isSaveSuccessful = false;
            this.isLoading = true;
            item.save().then(() => {
                this.isSaveSuccessful = true;
                this.isLoading = false;
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

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
        },

        openModalReplace() {
            this.showModalReplace = true;
        },

        closeModalReplace() {
            this.showModalReplace = false;
        }
    }
});
