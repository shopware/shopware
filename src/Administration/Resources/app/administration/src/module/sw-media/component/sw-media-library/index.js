import template from './sw-media-library.html.twig';
import './sw-media-library.scss';

const { Component, Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-media-library', {
    template,

    inject: ['repositoryFactory', 'acl'],

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
            default: true,
        },
    },

    data() {
        return {
            isLoading: false,
            selectedItems: this.selection,
            pageItem: 0,
            pageFolder: 0,
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
            return !this.isLoading && (!this.itemLoaderDone || !this.folderLoaderDone);
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
            this.isLoading = true;

            this.clearSelection();
            await this.fetchAssociatedFolders();
            this.subFolders = [];
            this.items = [];

            this.pageItem = 0;
            this.pageFolder = 0;
            this.itemLoaderDone = false;
            this.folderLoaderDone = false;

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
            await this.nextFolders();

            if (this.folderLoaderDone) {
                this.pageItem += 1;

                const criteria = new Criteria(this.pageItem, this.limit);
                criteria
                    .addFilter(Criteria.equals('mediaFolderId', this.folderId))
                    .addAssociation('tags')
                    .addAssociation('productMedia.product')
                    .addAssociation('categories')
                    .addAssociation('productManufacturers.products')
                    .addAssociation('mailTemplateMedia.mailTemplate')
                    .addAssociation('documentBaseConfigs')
                    .addAssociation('avatarUser')
                    .addAssociation('paymentMethods')
                    .addAssociation('shippingMethods')
                    .addSorting(Criteria.sort(this.sorting.sortBy, this.sorting.sortDirection))
                    .setTerm(this.term);

                const items = await this.mediaRepository.search(criteria, Context.api);

                this.items.push(...items);
                this.itemLoaderDone = this.isLoaderDone(criteria, items);
            }

            this.isLoading = false;
        },

        loadNextItems() {
            if (this.isLoading === true) {
                return;
            }
            this.isLoading = true;
            this.loadItems();
        },

        async nextFolders() {
            if (this.folderLoaderDone) {
                return;
            }

            this.pageFolder += 1;

            const criteria = new Criteria(this.pageFolder)
                .addFilter(Criteria.equals('parentId', this.folderId))
                .addSorting(Criteria.sort(this.folderSorting.sortBy, this.folderSorting.sortDirection))
                .setTerm(this.term);

            const subFolders = await this.mediaFolderRepository.search(criteria, Context.api);
            this.subFolders.push(...subFolders);

            this.folderLoaderDone = this.isLoaderDone(criteria, subFolders);
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
});
