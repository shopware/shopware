import { Component, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-media-folder-content.html.twig';
import './sw-media-folder-content.less';

Component.register('sw-media-folder-content', {
    template,

    inject: [
        'filterItems'
    ],

    props: {
        startFolderId: {
            type: String,
            required: false
        },

        selectedId: {
            type: String,
            required: false,
            default: ''
        }
    },

    data() {
        return {
            subFolders: [],
            parentFolder: null
        };
    },

    watch: {
        startFolderId(newId) {
            this.getSubFolders(newId);
            this.fetchParentFolder(newId);
        }
    },

    computed: {
        mediaFolderStore() {
            return State.getStore('media_folder');
        }
    },

    mounted() {
        this.onMountedComponent();
    },

    methods: {
        onMountedComponent() {
            this.getSubFolders(this.startFolderId);
            this.fetchParentFolder(this.startFolderId);
        },

        getSubFolders(parentId) {
            this.mediaFolderStore.getList({
                limit: 50,
                sortBy: 'name',
                criteria: CriteriaFactory.equals('parentId', parentId || null)
            }).then((response) => {
                this.subFolders = response.items.filter(this.filterItems);
            });
        },

        fetchParentFolder(folderId) {
            if (folderId !== '' && folderId !== null) {
                this.mediaFolderStore.getByIdAsync(folderId).then((folder) => {
                    this.updateParentFolder(folder);
                });
            } else {
                this.parentFolder = null;
            }
        },

        updateParentFolder(child) {
            if (child.id === '') {
                this.parentFolder = null;
            } else if (child.parentId === null) {
                this.parentFolder = { id: '', name: 'Medien' };
            } else {
                this.mediaFolderStore.getByIdAsync(child.parentId).then((parent) => {
                    this.parentFolder = parent;
                });
            }
        },

        emitInput(folder) {
            this.$emit('selected', folder);
        }
    }
});
