import template from './sw-media-sidebar.html.twig';
import './sw-media-sidebar.scss';

const { Filter, Context } = Shopware;

/**
 * @package buyers-experience
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['repositoryFactory'],

    emits: ['media-sidebar-folder-renamed'],

    mixins: [Shopware.Mixin.getByName('notification')],

    props: {
        items: {
            required: true,
            type: Array,
            validator(value) {
                const invalidElements = value.filter((element) => {
                    return !['media', 'media_folder'].includes(element.getEntityName());
                });
                return invalidElements.length === 0;
            },
        },

        currentFolderId: {
            type: String,
            required: false,
            default: null,
        },

        editable: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            currentFolder: null,
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        mediaFolderRepository() {
            return this.repositoryFactory.create('media_folder');
        },

        mediaNameFilter() {
            return Filter.getByName('mediaName');
        },

        mediaSidebarClasses() {
            return {
                'no-headline': !this.headLine,
            };
        },

        isSingleFile() {
            return this.items.length === 1;
        },

        isMultipleFile() {
            return this.items.length > 1;
        },

        headLine() {
            if (this.isSingleFile) {
                if (this.firstEntity.getEntityName() === 'media') {
                    return this.mediaNameFilter(this.firstEntity);
                }
                return this.firstEntity.name;
            }

            if (this.isMultipleFile) {
                return this.getSelectedFilesCount;
            }

            if (this.currentFolder) {
                return this.currentFolder.name;
            }

            return null;
        },

        getSelectedFilesCount() {
            return `${this.$tc('sw-media.sidebar.labelHeadlineMultiple', this.items.length, { count: this.items.length })}`;
        },

        firstEntity() {
            return this.items[0];
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        listeners() {
            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                return this.$listeners;
            }

            return {};
        },

        filteredAttributes() {
            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                return {};
            }

            const filteredAttributes = {};

            Object.entries(this.$attrs).forEach(([key, value]) => {
                if (key.startsWith('on') && typeof value === 'function') {
                    filteredAttributes[key] = value;
                }
            });

            return filteredAttributes;
        },
    },

    watch: {
        currentFolderId() {
            this.fetchCurrentFolder();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchCurrentFolder();
        },

        async fetchCurrentFolder() {
            if (!this.currentFolderId) {
                this.currentFolder = null;
                return;
            }

            this.currentFolder = await this.mediaFolderRepository.get(this.currentFolderId, Context.api);
        },

        onMediaFolderRenamed() {
            this.$emit('media-sidebar-folder-renamed');
        },

        /**
         * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
         */
        async onFirstItemUpdated(newItem) {
            const firstItem = this.items[0];

            try {
                firstItem.isLoading = true;
                Object.assign(this.items[0], newItem);
                await this.mediaRepository.save(firstItem, Context.api);
                this.createNotificationSuccess({
                    message: this.$tc('global.sw-media-media-item.notification.settingsSuccess.message'),
                });
            } catch {
                this.createNotificationError({
                    message: this.$tc('global.notification.unspecifiedSaveErrorMessage'),
                });
            } finally {
                firstItem.isLoading = false;
            }
        },
    },
};
