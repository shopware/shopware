import template from './sw-media-folder-content.html.twig';
import './sw-media-folder-content.scss';

const { Context } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'filterItems',
        'repositoryFactory',
    ],

    props: {
        startFolderId: {
            type: String,
            required: false,
            default: null,
        },

        selectedId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            subFolders: [],
            parentFolder: null,
        };
    },

    computed: {
        mediaFolderRepository() {
            return this.repositoryFactory.create('media_folder');
        },
    },

    watch: {
        startFolderId() {
            this.getSubFolders(this.startFolderId);
            this.fetchParentFolder(this.startFolderId);
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.getSubFolders(this.startFolderId);
            this.fetchParentFolder(this.startFolderId);
        },

        async getSubFolders(parentId) {
            const criteria = new Criteria(1, 50)
                .addFilter(Criteria.equals('parentId', parentId))
                .addAssociation('children')
                .addSorting(Criteria.sort('name', 'asc'));

            const searchResult = await this.mediaFolderRepository.search(criteria, Context.api);
            this.subFolders = searchResult.filter(this.filterItems);
        },

        getChildCount(folder) {
            return folder.children.filter(this.filterItems).length;
        },

        async fetchParentFolder(folderId) {
            if (folderId !== null) {
                const folder = await this.mediaFolderRepository.get(folderId, Context.api);
                this.updateParentFolder(folder);
            } else {
                this.parentFolder = null;
            }
        },

        async updateParentFolder(child) {
            if (child.id === null) {
                this.parentFolder = null;
            } else if (child.parentId === null) {
                this.parentFolder = { id: null, name: this.$tc('sw-media.index.rootFolderName') };
            } else {
                this.parentFolder = await this.mediaFolderRepository.get(child.parentId, Context.api);
            }
        },

        emitInput(folder) {
            this.$emit('selected', folder);
        },
    },
};
