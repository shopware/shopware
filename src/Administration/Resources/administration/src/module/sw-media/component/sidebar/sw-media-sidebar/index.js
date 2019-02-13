import { Component, Filter, State } from 'src/core/shopware';
import template from './sw-media-sidebar.html.twig';
import './sw-media-sidebar.scss';

Component.register('sw-media-sidebar', {
    template,

    props: {
        items: {
            required: true,
            type: Array,
            validator(value) {
                const invalidElements = value.filter((element) => {
                    return !['media', 'media_folder'].includes(element.entityName);
                });
                return invalidElements.length === 0;
            }
        },

        currentFolderId: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
            currentFolder: null
        };
    },

    computed: {
        mediaFolderStore() {
            return State.getStore('media_folder');
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
                if (this.firstEntity.entityName === 'media') {
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
        this.fetchCurrentFolder();
    },

    methods: {
        fetchCurrentFolder() {
            if (!this.currentFolderId) {
                this.currentFolder = null;
                return;
            }

            this.currentFolder = this.mediaFolderStore.getById(this.currentFolderId);
        }
    }
});
