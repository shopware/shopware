import template from './sw-media-sidebar.html.twig';
import './sw-media-sidebar.scss';

const { Component, Filter, StateDeprecated } = Shopware;

Component.register('sw-media-sidebar', {
    template,

    props: {
        items: {
            required: true,
            type: Array,
            validator(value) {
                const invalidElements = value.filter((element) => {
                    return !['media', 'media_folder'].includes(element.getEntityName());
                });
                return invalidElements.length === 0;
            }
        },

        currentFolderId: {
            type: String,
            required: false,
            default: null
        },

        editable: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            currentFolder: null
        };
    },

    computed: {
        mediaFolderStore() {
            return StateDeprecated.getStore('media_folder');
        },

        mediaNameFilter() {
            return Filter.getByName('mediaName');
        },

        mediaSidebarClasses() {
            return {
                'no-headline': !this.headLine
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
        }
    },

    watch: {
        currentFolderId() {
            this.fetchCurrentFolder();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchCurrentFolder();
        },

        fetchCurrentFolder() {
            if (!this.currentFolderId) {
                this.currentFolder = null;
                return;
            }

            this.currentFolder = this.mediaFolderStore.getById(this.currentFolderId);
        }
    }
});
