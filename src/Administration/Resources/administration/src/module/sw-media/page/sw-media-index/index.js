import template from './sw-media-index.html.twig';
import './sw-media-index.scss';

const { Component, StateDeprecated } = Shopware;

Component.register('sw-media-index', {
    template,

    props: {
        routeFolderId: {
            type: String,
            default: null
        }
    },

    data() {
        return {
            isLoading: false,
            selectedItems: [],
            uploads: [],
            term: this.$route.query ? this.$route.query.term : '',
            uploadTag: 'upload-tag-sw-media-index'
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },


    computed: {
        mediaItemStore() {
            return StateDeprecated.getStore('media');
        },

        mediaFolderStore() {
            return StateDeprecated.getStore('media_folder');
        },

        uploadStore() {
            return StateDeprecated.getStore('upload');
        },

        currentFolder() {
            if (this.routeFolderId) {
                return this.mediaFolderStore.getById(this.routeFolderId);
            }

            return null;
        },

        parentFolder() {
            if (!this.currentFolder) {
                return null;
            }

            if (!this.currentFolder.parentId) {
                return this.rootFolder;
            }

            return this.mediaFolderStore.getById(this.currentFolder.parentId);
        },

        parentFolderName() {
            return this.parentFolder ? this.parentFolder.name : this.$tc('sw-media.index.rootFolderName');
        },

        currentFolderName() {
            return this.currentFolder ? this.currentFolder.name : this.$tc('sw-media.index.rootFolderName');
        },

        rootFolder() {
            const root = new this.mediaFolderStore.EntityClass(this.mediaFolderStore.getEntityName(), null, null, null);
            root.name = this.$tc('sw-media.index.rootFolderName');

            return root;
        }
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        destroyedComponent() {
            this.$root.$off('search', this.onSearch);
        },

        onUploadsAdded({ data }) {
            data.forEach((upload) => {
                const loadedEntity = this.mediaItemStore.getById(upload.targetId);
                this.uploads.push(loadedEntity);
            });

            this.uploadStore.runUploads(this.uploadTag);
        },

        onUploadFinished({ targetId }) {
            this.uploads = this.uploads.filter((upload) => {
                return upload.id !== targetId;
            });

            this.mediaItemStore.getByIdAsync(targetId).then((updatedItem) => {
                this.$refs.mediaLibrary.injectItem(updatedItem);
            });
        },

        onUploadFailed({ targetId }) {
            this.uploads = this.uploads.filter((upload) => {
                return targetId !== upload.id;
            });
        },

        onChangeLanguage() {
            this.clearSelection();
        },

        onSearch(value) {
            this.term = value;
            this.clearSelection();
        },

        onItemsDeleted(ids) {
            this.onMediaFoldersDissolved(ids.folderIds);
        },

        onMediaFoldersDissolved(ids) {
            this.clearSelection();
            if (ids.includes(this.routeFolderId)) {
                let routeId = null;
                if (this.parentFolder) {
                    routeId = this.parentFolder.id;
                }

                this.$router.push({
                    name: 'sw.media.index',
                    params: {
                        folderId: routeId
                    }
                });
                return;
            }

            this.$refs.mediaLibrary.refreshList();
        },

        reloadList() {
            this.$refs.mediaLibrary.refreshList();
        },

        clearSelection() {
            this.selectedItems.splice(0, this.selectedItems.length);
        },

        onMediaUnselect({ item }) {
            const index = this.selectedItems.findIndex((selected) => {
                return selected === item;
            });

            if (index > -1) {
                this.selectedItems.splice(index, 1);
            }
        },

        updateRoute(newFolderId) {
            this.term = this.$route.query ? this.$route.query.term : '';
            this.$router.push({
                name: 'sw.media.index',
                params: {
                    folderId: newFolderId
                }
            });
        }
    }
});
