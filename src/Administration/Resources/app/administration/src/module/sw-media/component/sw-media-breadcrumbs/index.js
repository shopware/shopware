import template from './sw-media-breadcrumbs.html.twig';
import './sw-media-breadcrumbs.scss';

const { Component, Context } = Shopware;

Component.register('sw-media-breadcrumbs', {
    template,

    inject: ['repositoryFactory'],

    model: {
        prop: 'currentFolderId',
        event: 'media-folder-change',
    },

    props: {
        currentFolderId: {
            type: String,
            required: false,
            default: null,
        },

        small: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            currentFolder: null,
            parentFolder: null,
        };
    },

    computed: {
        mediaFolderRepository() {
            return this.repositoryFactory.create('media_folder');
        },
        rootFolder() {
            const root = this.mediaFolderRepository.create(Context.api);
            root.name = this.$tc('sw-media.index.rootFolderName');
            root.id = null;
            return root;
        },

        swMediaBreadcrumbsClasses() {
            return {
                'is--small': this.small,
            };
        },
    },

    watch: {
        currentFolderId() {
            this.updateFolder();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateFolder();
        },

        async updateFolder() {
            if (!this.currentFolderId) {
                this.currentFolder = this.rootFolder;
                this.parentFolder = null;
            } else {
                this.currentFolder = await this.mediaFolderRepository.get(this.currentFolderId, Context.api);

                if (this.currentFolder && this.currentFolder.parentId) {
                    this.parentFolder = await this.mediaFolderRepository.get(this.currentFolder.parentId, Context.api);
                } else {
                    this.parentFolder = this.rootFolder;
                }
            }
        },

        onBreadcrumbsItemClicked(id) {
            this.$emit('media-folder-change', id);
        },
    },
});
