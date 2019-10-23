import template from './sw-media-breadcrumbs.html.twig';
import './sw-media-breadcrumbs.scss';

const { Component, StateDeprecated } = Shopware;

Component.register('sw-media-breadcrumbs', {
    template,

    model: {
        prop: 'currentFolderId',
        event: 'media-folder-change'
    },

    props: {
        currentFolderId: {
            type: String,
            required: false,
            default: null
        },

        small: {
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

        rootFolder() {
            const root = new this.mediaFolderStore.EntityClass(this.mediaFolderStore.getEntityName(), null, null, null);
            root.name = this.$tc('sw-media.index.rootFolderName');

            return root;
        },

        parentFolder() {
            if (!this.currentFolder || this.currentFolder === this.rootFolder) {
                return null;
            }

            if (!this.currentFolder.parentId) {
                return this.rootFolder;
            }

            return this.mediaFolderStore.getById(this.currentFolder.parentId);
        },

        swMediaBreadcrumbsClasses() {
            return {
                'is--small': this.small
            };
        }
    },

    watch: {
        currentFolderId() {
            this.updateFolder();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateFolder();
        },

        updateFolder() {
            if (!this.currentFolderId) {
                this.currentFolder = this.rootFolder;
                return;
            }
            this.currentFolder = this.mediaFolderStore.getById(this.currentFolderId);
        },

        onBreadcrumbsItemClicked(id) {
            this.$emit('media-folder-change', id);
        }
    }
});
