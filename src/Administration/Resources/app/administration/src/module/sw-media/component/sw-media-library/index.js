import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-media-library.html.twig';
import './sw-media-library.scss';

const { Component, Mixin, StateDeprecated } = Shopware;
const ItemLoader = Shopware.Helper.InfiniteScrollingHelper;

Component.register('sw-media-library', {
    template,

    model: {
        prop: 'selection',
        event: 'media-selection-change'
    },

    mixins: [
        Mixin.getByName('media-grid-listener')
    ],

    props: {
        selection: {
            type: Array,
            required: true
        },

        folderId: {
            type: String,
            required: false,
            default: null
        },

        pendingUploads: {
            type: Array,
            required: false,
            default() {
                return [];
            }
        },

        limit: {
            type: Number,
            required: false,
            default: 25,
            validValues: [1, 5, 25, 50, 100, 500],
            validator(value) {
                return [1, 5, 25, 50, 100, 500].includes(value);
            }
        },

        term: {
            type: String,
            required: false,
            default: ''
        },

        compact: {
            type: Boolean,
            required: false,
            default: false
        },

        editable: {
            type: Boolean,
            required: false,
            default: false
        },

        allowMultiSelect: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    data() {
        return {
            isLoading: false,
            selectedItems: this.selection,
            items: [],
            subFolders: [],
            currentFolder: null,
            parentFolder: null,
            presentation: 'medium-preview',
            sorting: { sortBy: 'fileName', sortDirection: 'asc' },
            folderSorting: { sortBy: 'name', sortDirection: 'asc' },
            done: false
        };
    },

    computed: {
        mediaFolderStore() {
            return StateDeprecated.getStore('media_folder');
        },

        mediaFolderConfigurationStore() {
            return StateDeprecated.getStore('media_folder_configuration');
        },

        folderLoader() {
            return new ItemLoader('media_folder', this.limit);
        },

        mediaLoader() {
            return new ItemLoader('media', this.limit);
        },

        selectableItems() {
            return [...this.subFolders, ...this.pendingUploads, ...this.items];
        },

        rootFolder() {
            const root = new this.mediaFolderStore.EntityClass(this.mediaFolderStore.getEntityName(), null, null, null);
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
            return !this.isLoading && !this.done;
        }
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
        }
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
        refreshList() {
            if (this.isLoading === true) {
                return;
            }
            this.isLoading = true;

            this.clearSelection();
            this.done = false;
            this.fetchAssociatedFolders();
            this.folderLoader.reset();
            this.subFolders = [];
            this.mediaLoader.reset();
            this.items = [];

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

        loadItems() {
            this.isLoading = true;
            this.nextFolders().then((doneLoadFolders) => {
                if (doneLoadFolders) {
                    const criteria = {
                        criteria: CriteriaFactory.equals('mediaFolderId', this.folderId),
                        term: this.term
                    };

                    return this.mediaLoader.next(Object.assign({}, this.sorting, criteria)).then((items) => {
                        this.items.push(...items);
                        this.done = this.mediaLoader.done;
                    });
                }

                return Promise.resolve();
            }).then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        loadNextItems() {
            if (this.isLoading === true) {
                return;
            }
            this.isLoading = true;
            this.loadItems();
        },

        nextFolders() {
            if (this.folderLoader.done) {
                return Promise.resolve(true);
            }

            const criteria = {
                criteria: CriteriaFactory.equals('parentId', this.folderId),
                term: this.term,
                associations: {
                    defaultFolder: {}
                }
            };

            return this.folderLoader.next(Object.assign({}, this.folderSorting, criteria), true).then((items) => {
                this.subFolders.push(...items);
                return this.folderLoader.done;
            });
        },

        fetchAssociatedFolders() {
            if (this.folderId === null) {
                this.currentFolder = null;
                this.parentFolder = null;
                return;
            }

            this.mediaFolderStore.getByIdAsync(this.folderId).then((currentFolder) => {
                this.currentFolder = currentFolder;
                if (this.currentFolder.parentId) {
                    this.mediaFolderStore.getByIdAsync(currentFolder.parentId).then((parentFolder) => {
                        this.parentFolder = parentFolder;
                    });
                    return;
                }

                this.parentFolder = this.rootFolder;
            });
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

        createFolder() {
            const newFolder = this.mediaFolderStore.create();

            newFolder.name = '';
            newFolder.parentId = this.folderId;
            if (this.folderId !== null) {
                newFolder.configurationId = this.currentFolder.configurationId;
                newFolder.useParentConfiguration = true;
            } else {
                const configuration = this.mediaFolderConfigurationStore.create();
                configuration.createThumbnails = true;
                configuration.keepProportions = true;
                configuration.thumbnailQuality = 80;
                newFolder.configuration = configuration;
                newFolder.useParentConfiguration = false;
            }
            this.subFolders.unshift(newFolder);
        },

        removeNewFolder() {
            this.subFolders.shift();
        }
    }
});
