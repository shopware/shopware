import template from './sw-media-quickinfo.html.twig';
import './sw-media-quickinfo.scss';

const { Component, Mixin, Context, Utils, Data } = Shopware;
const { dom, format } = Utils;
const { Criteria } = Data;

Component.register('sw-media-quickinfo', {
    template,

    inject: ['mediaService', 'repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('media-sidebar-modal-mixin'),
        Mixin.getByName('placeholder'),
    ],

    props: {
        item: {
            required: true,
            type: Object,
            validator(value) {
                return value.getEntityName() === 'media';
            },
        },

        editable: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            customFieldSets: [],
            isLoading: false,
            isSaveSuccessful: false,
            showModalReplace: false,
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
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
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getCustomFieldSets();
        },

        async getCustomFieldSets() {
            const criteria = new Criteria(1, 100)
                .addFilter(Criteria.equals('relations.entityName', 'media'))
                .addAssociation('customFields')
                .addSorting(Criteria.sort('config.customFieldPosition', 'ASC', true))
                .setLimit(100);

            const searchResult = await this.customFieldSetRepository.search(criteria);
            this.customFieldSets = searchResult.filter(set => set.customFields.length > 0);
        },

        async onSaveCustomFields(item) {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            await this.mediaRepository.save(item, Context.api);

            this.isSaveSuccessful = true;
            this.isLoading = false;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        copyLinkToClipboard() {
            if (this.item) {
                dom.copyToClipboard(this.item.url);
                this.createNotificationSuccess({
                    message: this.$tc('sw-media.general.notification.urlCopied.message'),
                });
            }
        },

        async onSubmitTitle(value) {
            this.item.title = value;

            try {
                await this.mediaRepository.save(this.item, Context.api);
            } catch {
                this.$refs.inlineEditFieldTitle.cancelSubmit();
            }
        },

        async onSubmitAltText(value) {
            this.item.alt = value;

            try {
                await this.mediaRepository.save(this.item, Context.api);
            } catch {
                this.$refs.inlineEditFieldAlt.cancelSubmit();
            }
        },

        async onChangeFileName(value) {
            const { item } = this;
            item.isLoading = true;

            try {
                await this.mediaService.renameMedia(item.id, value);
                item.fileName = value;

                this.createNotificationSuccess({
                    message: this.$tc('global.sw-media-media-item.notification.renamingSuccess.message'),
                });
                this.$emit('media-item-rename-success', item);
            } catch {
                this.createNotificationError({
                    message: this.$tc('global.sw-media-media-item.notification.renamingError.message'),
                });
            } finally {
                item.isLoading = false;
            }
        },

        openModalReplace() {
            if (!this.acl.can('media.editor')) {
                return;
            }

            this.showModalReplace = true;
        },

        closeModalReplace() {
            this.showModalReplace = false;
        },

        emitRefreshMediaLibrary() {
            this.closeModalReplace();

            this.$nextTick(() => {
                this.$emit('media-item-replaced');
            });
        },

        quickActionClasses(disabled) {
            return ['sw-media-sidebar__quickaction', {
                'sw-media-sidebar__quickaction--disabled': disabled,
            }];
        },
    },
});
