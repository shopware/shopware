import template from './sw-media-quickinfo.html.twig';
import './sw-media-quickinfo.scss';

const { Mixin, Context, Utils } = Shopware;
const { dom, format } = Utils;

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['mediaService', 'repositoryFactory', 'acl', 'customFieldDataProviderService'],

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
            fileNameError: null,
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
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

        fileNameClasses() {
            return {
                'has--error': this.fileNameError,
            };
        },
    },

    watch: {
        'item.id': {
            handler() {
                this.fileNameError = null;
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadCustomFieldSets();
        },

        loadCustomFieldSets() {
            return this.customFieldDataProviderService.getCustomFieldSets('media').then((sets) => {
                this.customFieldSets = sets;
            });
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
            this.fileNameError = null;

            try {
                await this.mediaService.renameMedia(item.id, value).catch((error) => {
                    const fileNameErrorCodes = ['CONTENT__MEDIA_EMPTY_FILE', 'CONTENT__MEDIA_ILLEGAL_FILE_NAME'];

                    error.response.data.errors.forEach((e) => {
                        if (this.fileNameError || !fileNameErrorCodes.includes(e.code)) {
                            return;
                        }

                        this.fileNameError = e;
                    });

                    return Promise.reject(error);
                });
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

        onRemoveFileNameError() {
            this.fileNameError = null;
        },
    },
};
