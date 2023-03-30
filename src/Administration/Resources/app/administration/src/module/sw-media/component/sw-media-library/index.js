import template from './sw-media-library.html.twig';
import './sw-media-library.scss';

const { Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl', 'searchRankingService'],

    mixins: [
        Mixin.getByName('media-grid-listener'),
    ],

    model: {
        prop: 'selection',
        event: 'media-selection-change',
    },

    props: {
        selection: {
            type: Array,
            required: true,
        },

        folderId: {
            type: String,
            required: false,
            default: null,
        },

        pendingUploads: {
            type: Array,
            required: false,
            default() {
                return [];
            },
        },

        limit: {
            type: Number,
            required: false,
            default: 25,
            validValues: [1, 5, 25, 50, 100, 500],
            validator(value) {
                return [1, 5, 25, 50, 100, 500].includes(value);
            },
        },

        term: {
            type: String,
            required: false,
            default: '',
        },

        compact: {
            type: Boolean,
            required: false,
            default: false,
        },

        editable: {
            type: Boolean,
            required: false,
            default: false,
        },

        allowMultiSelect: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    data() {
        return {
            isLoading: false,
            selectedItems: this.selection,
            pageItem: 1,
            pageFolder: 1,
            itemLoaderDone: false,
            folderLoaderDone: false,
            items: [],
            subFolders: [],
            currentFolder: null,
            parentFolder: null,
            presentation: 'medium-preview',
            sorting: { sortBy: 'fileName', sortDirection: 'asc' },
            folderSorting: { sortBy: 'name', sortDirection: 'asc' },
        };
    },

    computed: {
        shouldDisplayEmptyState() {
            return !this.isLoading && (this.selectableItems.length === 0 || (
                this.isValidTerm(this.term) && this.selectableItems.length === 0
            ));
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        mediaFolderRepository() {
            return this.repositoryFactory.create('media_folder');
        },

        mediaFolderConfigurationRepository() {
            return this.repositoryFactory.create('media_folder_configuration');
        },

        selectableItems() {
            return [...this.subFolders, ...this.pendingUploads, ...this.items];
        },

        rootFolder() {
            const root = this.mediaFolderRepository.create(Context.api);
            root.id = '';
            root.name = this.$tc('sw-media.index.rootFolderName');

            return root;
        },

        gridPresentation() {
            if (this.compact) {
                return 'list-preview';
            }

            return this.presentation;
        },

        showItemsAsList() {
            return this.gridPresentation === 'list-preview';
        },

        showLoadMoreButton() {
            if (this.isLoading || this.shouldDisplayEmptyState) {
                return false;
            }

            return !(this.itemLoaderDone && this.folderLoaderDone);
        },

        nextMediaCriteria() {
            // always search without folderId criteria --> search for all items
            const criteria = new Criteria(this.pageItem, this.limit);

            criteria
                .addSorting(Criteria.sort(this.sorting.sortBy, this.sorting.sortDirection))
                .setTerm(this.term);

            // ToDo NEXT-22186 - will be replaced by a new overview
            [
                'tags',
                'productMedia.product',
                'categories',
                'productManufacturers.products',
                'mailTemplateMedia.mailTemplate',
                'documentBaseConfigs',
                'avatarUsers',
                'paymentMethods',
                'shippingMethods',
                'cmsBlocks.section.page',
                'cmsSections.page',
                'cmsPages',
            ].forEach(association => {
                const associationParts = association.split('.');

                criteria.addAssociation(association);

                let path = null;
                associationParts.forEach(currentPart => {
                    path = path ? `${path}.${currentPart}` : currentPart;

                    criteria.getAssociation(path).setLimit(25);
                });
            });

            return criteria;
        },

        nextFoldersCriteria() {
            const criteria = new Criteria(this.pageFolder, this.limit)
                .addSorting(Criteria.sort(this.folderSorting.sortBy, this.folderSorting.sortDirection))
                .setTerm(this.term);

            if (!this.term) {
                criteria.addFilter(Criteria.equals('parentId', this.folderId));
            }

            return criteria;
        },
    },

    watch: {
        selection() {
            this.selectedItems = this.selection;
            if (this.listSelectionStartItem !== null && !this.selectedItems.includes(this.listSelectionStartItem)) {
                this.listSelectionStartItem = this.selectedItems[0] || null;
            }
        },

        selectedItems() {
            this.$emit('media-selection-change', this.selectedItems);
        },

        sorting() {
            this.mapFolderSorting();
            this.refreshList();
        },

        folderId() {
            this.refreshList();
        },

        term() {
            this.refreshList();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.refreshList();

            if (this.allowMultiSelect) {
                return;
            }

            this.handleMediaItemClicked = ({ item }) => {
                this._singleSelect(item);
            };

            this.handleMediaGridItemSelected = () => {};
        },

        /*
         * Object fetching
         */
        async refreshList() {
            if (this.isLoading === true) {
                return;
            }

            this.subFolders = [];
            this.items = [];

            this.isLoading = true;

            this.clearSelection();
            await this.fetchAssociatedFolders();

            this.pageItem = 1;
            this.pageFolder = 1;

            this.itemLoaderDone = false;
            this.folderLoaderDone = false;

            this.loadItems();
        },

        isValidTerm(term) {
            return term?.trim()?.length > 1;
        },

        loadNextItems() {
            if (this.isLoading === true) {
                return;
            }
            this.isLoading = true;
            this.loadItems();
        },

        mapFolderSorting() {
            switch (this.sorting.sortBy) {
                case 'createdAt':
                    this.folderSorting.sortBy = 'createdAt';
                    this.folderSorting.sortDirection = this.sorting.sortDirection;
                    break;
                case 'fileName':
                    this.folderSorting.sortBy = 'name';
                    this.folderSorting.sortDirection = this.sorting.sortDirection;
                    break;
                default:
                    this.folderSorting.sortBy = 'name';
                    this.folderSorting.sortDirection = 'asc';
            }
        },

        isLoaderDone(criteria, data) {
            return criteria.limit >= data.total || criteria.limit > data.length;
        },

        async loadItems() {
            this.isLoading = true;
            const [nextFolders, nextMedia] = await Promise.allSettled([this.nextFolders(), this.nextMedia()]);

            if (nextMedia.status === 'fulfilled') {
                this.items.push(...nextMedia.value);
            } else {
                this.itemLoaderDone = false;
            }

            if (nextFolders.status === 'fulfilled') {
                this.subFolders.push(...nextFolders.value);
            } else {
                this.folderLoaderDone = false;
            }

            this.isLoading = false;
        },

        async nextMedia() {
            if (this.itemLoaderDone) {
                return [];
            }

            let criteria = this.nextMediaCriteria;

            if (this.isValidTerm(this.term)) {
                const searchRankingFields = await this.searchRankingService.getSearchFieldsByEntity('media');

                if (!searchRankingFields || Object.keys(searchRankingFields).length < 1) {
                    this.isLoading = false;
                    this.itemLoaderDone = true;

                    return [];
                }

                criteria = this.searchRankingService.buildSearchQueriesForEntity(
                    searchRankingFields,
                    this.term,
                    criteria,
                );
            }

            // only fetch items of current folder
            if (!this.isValidTerm(this.term)) {
                criteria.addFilter(Criteria.equals('mediaFolderId', this.folderId));
            }

            // search only in current and all subFolders
            if (this.folderId != null && this.isValidTerm(this.term)) {
                criteria.addFilter(Criteria.multi('OR', [
                    Criteria.equals('mediaFolderId', this.folderId),
                    Criteria.contains('mediaFolder.path', this.folderId),
                ]));
            }

            const media = await this.mediaRepository.search(criteria, Context.api);

            this.itemLoaderDone = this.isLoaderDone(criteria, media);

            this.pageItem += 1;

            return media;
        },

        async nextFolders() {
            if (this.folderLoaderDone) {
                return [];
            }

            const subFolders = await this.mediaFolderRepository.search(this.nextFoldersCriteria, Context.api);

            this.folderLoaderDone = this.isLoaderDone(this.nextFoldersCriteria, subFolders);

            this.pageFolder += 1;

            return subFolders;
        },

        async fetchAssociatedFolders() {
            if (this.folderId === null) {
                this.currentFolder = null;
                this.parentFolder = null;
                return;
            }

            this.currentFolder = await this.mediaFolderRepository.get(this.folderId, Context.api);

            if (this.currentFolder && this.currentFolder.parentId) {
                this.parentFolder = await this.mediaFolderRepository.get(this.currentFolder.parentId, Context.api);
            } else {
                this.parentFolder = this.rootFolder;
            }
        },

        goToParentFolder() {
            this.$emit('media-folder-change', this.parentFolder.id || null);
        },

        clearSelection() {
            this.selectedItems = [];
            this.listSelectionStartItem = null;
        },

        injectItem(item) {
            if (item.getEntityName() === 'media') {
                this.injectMedia(item);
                return;
            }

            throw new Error('Injected entity has to be of \'type media\'');
        },

        injectMedia(mediaEntity) {
            if (mediaEntity.mediaFolderId !== this.folderId) {
                return;
            }

            if (!this.items.some((alreadyListed) => {
                return alreadyListed.id === mediaEntity.id;
            })) {
                this.items.unshift(mediaEntity);
            }
        },

        async createFolder() {
            const newFolder = this.mediaFolderRepository.create(Context.api);
            newFolder.parentId = this.folderId;
            newFolder.name = '';

            if (this.folderId !== null) {
                newFolder.configurationId = this.currentFolder.configurationId;
                newFolder.useParentConfiguration = true;
            } else {
                const configuration = this.mediaFolderConfigurationRepository.create(Context.api);
                configuration.createThumbnails = true;
                configuration.keepProportions = true;
                configuration.thumbnailQuality = 80;

                await this.mediaFolderConfigurationRepository.save(configuration, Context.api);

                newFolder.configurationId = configuration.id;
                newFolder.useParentConfiguration = false;
            }

            this.subFolders.unshift(newFolder);
        },

        removeNewFolder() {
            this.subFolders.shift();
        },
    },
};
