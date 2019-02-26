import { State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-media-folder-content.html.twig';
import './sw-media-folder-content.scss';

export default {
    name: 'sw-media-folder-content',
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
            default: null
        }
    },

    data() {
        return {
            subFolders: [],
            parentFolder: null
        };
    },

    computed: {
        mediaFolderStore() {
            return State.getStore('media_folder');
        }
    },

    watch: {
        startFolderId() {
            this.getSubFolders(this.startFolderId);
            this.fetchParentFolder(this.startFolderId);
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
                criteria: CriteriaFactory.equals('media_folder.parentId', parentId),
                associations: {
                    children: {
                        page: 1,
                        limit: 50
                    }
                }
            }, true).then((response) => {
                this.subFolders = response.items.filter(this.filterItems);
            });
        },

        getChildCount(folder) {
            return folder.children.filter(this.filterItems).length;
        },

        fetchParentFolder(folderId) {
            if (folderId !== null) {
                this.mediaFolderStore.getByIdAsync(folderId).then((folder) => {
                    this.updateParentFolder(folder);
                });
            } else {
                this.parentFolder = null;
            }
        },

        updateParentFolder(child) {
            if (child.id === null) {
                this.parentFolder = null;
            } else if (child.parentId === null) {
                this.parentFolder = { id: null, name: this.$tc('sw-media.index.rootFolderName') };
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
};
