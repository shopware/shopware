import template from './sw-media-library.html.twig';
import './sw-media-library.scss';

const { Mixin, Context, Feature } = Shopware;
const { Criteria } = Shopware.Data;

const MEDIA_LIBRARY_PREFERENCES_KEY = 'media.library.preferences';
const DEFAULT_PRESENTATION = 'medium-preview';
const DEFAULT_SORTING = { sortBy: 'createdAt', sortDirection: 'desc' };
const DEFAULT_TYPE_FILTERS = [
    'folder',
    'image',
    'document',
    'video',
    'audio',
    'spatial',
];
const VALID_PRESENTATIONS = [
    'small-preview',
    'medium-preview',
    'list-preview',
];
const VALID_SORT_FIELDS = [
    'createdAt',
    'fileName',
    'fileSize',
    'fileExtension',
];
const VALID_SORT_DIRECTIONS = [
    'asc',
    'desc',
];
const VALID_TYPE_FILTERS = [
    'all',
    'folder',
    'image',
    'document',
    'video',
    'audio',
    'spatial',
];
const DOCUMENT_FILE_EXTENSIONS = [
    'csv',
    'doc',
    'docx',
    'json',
    'ods',
    'odt',
    'pdf',
    'ppt',
    'pptx',
    'rtf',
    'txt',
    'xls',
    'xlsx',
    'xml',
];
const SPATIAL_FILE_EXTENSIONS = [
    'glb',
    'gltf',
    'obj',
    'step',
    'stl',
    'stp',
    'usdz',
];

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'searchRankingService',
        'feature',
        'userConfigService',
    ],

    emits: [
        'update:selection',
        'media-folder-change',
    ],

    mixins: [
        Mixin.getByName('media-grid-listener'),
    ],

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
            validValues: [
                1,
                5,
                25,
                50,
                100,
                500,
            ],
            validator(value) {
                return [
                    1,
                    5,
                    25,
                    50,
                    100,
                    500,
                ].includes(value);
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

        allowCreateFolder: {
            type: Boolean,
            required: false,
            default: false,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
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
            presentation: DEFAULT_PRESENTATION,
            sorting: { ...DEFAULT_SORTING },
            typeFilter: [],
            folderSorting: { sortBy: 'createdAt', sortDirection: 'desc' },
            userPreferences: {},
            isApplyingUserPreferences: false,
            hasScrolledContent: false,
            hasUnfilteredFolderItems: null,
        };
    },

    computed: {
        mediaLibraryClasses() {
            return {
                'sw-media-library--has-scroll-fade': this.hasScrolledContent,
            };
        },

        shouldDisplayEmptyState() {
            return !this.isLoading && this.itemLoaderDone && this.folderLoaderDone && this.selectableItems.length === 0;
        },

        emptyStateHeadline() {
            if (this.hasActiveTypeFilter && this.hasUnfilteredFolderItems !== false) {
                return this.$t('sw-media.mediaLibrary.titleFilteredEmptyState');
            }

            return this.$t('sw-media.mediaLibrary.titleFolderEmptyState');
        },

        emptyStateDescription() {
            if (this.hasActiveTypeFilter && this.hasUnfilteredFolderItems !== false) {
                return this.$t('sw-media.mediaLibrary.descriptionFilteredEmptyState');
            }

            return this.$t('sw-media.mediaLibrary.descriptionFolderEmptyState');
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
            return [
                ...this.subFolders,
                ...this.pendingUploads,
                ...this.items,
            ];
        },

        rootFolder() {
            const root = this.mediaFolderRepository.create(Context.api);
            root.id = '';
            root.name = this.$t('sw-media.index.rootFolderName');

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

            criteria.addSorting(Criteria.sort(this.sorting.sortBy, this.sorting.sortDirection)).setTerm(this.term);
            this.addTypeFilter(criteria);

            // eslint-disable-next-line no-warning-comments
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
            ].forEach((association) => {
                const associationParts = association.split('.');

                criteria.addAssociation(association);

                let path = null;
                associationParts.forEach((currentPart) => {
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

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        adminEsEnable() {
            if (!Feature.isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
                return false;
            }

            return Context.app.adminEsEnable ?? false;
        },

        normalizedTypeFilter() {
            return this.normalizeTypeFilter(this.typeFilter);
        },

        selectedMediaTypeFilters() {
            return this.normalizedTypeFilter.filter((type) => type !== 'folder');
        },

        hasActiveTypeFilter() {
            return this.normalizedTypeFilter.length > 0;
        },

        shouldLoadFolders() {
            return !this.hasActiveTypeFilter || this.normalizedTypeFilter.includes('folder');
        },

        shouldLoadMedia() {
            return !this.hasActiveTypeFilter || this.selectedMediaTypeFilters.length > 0;
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
            this.$emit('update:selection', this.selectedItems);
        },

        sorting() {
            this.mapFolderSorting();
            this.saveUserPreferences();
            this.refreshList();
        },

        presentation() {
            this.saveUserPreferences();
        },

        typeFilter() {
            this.saveUserPreferences();
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

    mounted() {
        this.updateScrollPosition();
    },

    beforeUnmount() {
        this.beforeUnmountedComponent();
    },

    methods: {
        async createdComponent() {
            Shopware.Utils.EventBus.on('sw-media-library-item-updated', this.refreshItem);

            await this.loadUserPreferences();
            this.refreshList();

            if (this.allowMultiSelect) {
                return;
            }

            this.handleMediaItemClicked = ({ item }) => {
                this._singleSelect(item);
            };

            this.handleMediaGridItemSelected = () => {};
        },

        beforeUnmountedComponent() {
            Shopware.Utils.EventBus.off('sw-media-library-item-updated', this.refreshItem);
        },

        async loadUserPreferences() {
            if (!this.userConfigService?.search) {
                return;
            }

            this.isApplyingUserPreferences = true;

            try {
                const response = await this.userConfigService.search([MEDIA_LIBRARY_PREFERENCES_KEY]);
                const userPreferences = response?.data?.[MEDIA_LIBRARY_PREFERENCES_KEY];

                if (!userPreferences || typeof userPreferences !== 'object') {
                    return;
                }

                this.userPreferences = userPreferences;

                if (VALID_PRESENTATIONS.includes(userPreferences.presentation)) {
                    this.presentation = userPreferences.presentation;
                }

                if (this.isValidSorting(userPreferences.sorting)) {
                    this.sorting = { ...userPreferences.sorting };
                    this.mapFolderSorting();
                }

                if (this.isValidTypeFilter(userPreferences.typeFilter)) {
                    this.typeFilter = this.normalizeTypeFilter(userPreferences.typeFilter);
                }
            } catch {
                this.userPreferences = {};
            } finally {
                await this.$nextTick();
                this.isApplyingUserPreferences = false;
            }
        },

        async getStoredUserPreferences() {
            try {
                const response = await this.userConfigService.search([MEDIA_LIBRARY_PREFERENCES_KEY]);
                const userPreferences = response?.data?.[MEDIA_LIBRARY_PREFERENCES_KEY];

                if (!userPreferences || typeof userPreferences !== 'object') {
                    return {};
                }

                return userPreferences;
            } catch {
                return this.userPreferences;
            }
        },

        async saveUserPreferences() {
            if (this.isApplyingUserPreferences || !this.userConfigService?.upsert) {
                return Promise.resolve();
            }

            const storedUserPreferences = await this.getStoredUserPreferences();

            this.userPreferences = {
                ...storedUserPreferences,
                presentation: this.presentation,
                sorting: this.sorting,
                typeFilter: this.normalizedTypeFilter,
            };

            return this.userConfigService
                .upsert({
                    [MEDIA_LIBRARY_PREFERENCES_KEY]: this.userPreferences,
                })
                .catch(() => {});
        },

        isValidSorting(sorting) {
            return (
                sorting &&
                VALID_SORT_FIELDS.includes(sorting.sortBy) &&
                VALID_SORT_DIRECTIONS.includes(sorting.sortDirection)
            );
        },

        isValidTypeFilter(typeFilter) {
            if (Array.isArray(typeFilter)) {
                return typeFilter.every((type) => VALID_TYPE_FILTERS.includes(type));
            }

            return VALID_TYPE_FILTERS.includes(typeFilter);
        },

        normalizeTypeFilter(typeFilter) {
            if (typeFilter === 'all') {
                return [];
            }

            if (Array.isArray(typeFilter)) {
                return typeFilter.filter((type) => DEFAULT_TYPE_FILTERS.includes(type));
            }

            if (DEFAULT_TYPE_FILTERS.includes(typeFilter)) {
                return [typeFilter];
            }

            return [];
        },

        addTypeFilter(criteria) {
            if (!this.hasActiveTypeFilter) {
                return;
            }

            const selectedMediaTypeFilters = this.selectedMediaTypeFilters;

            if (selectedMediaTypeFilters.length === DEFAULT_TYPE_FILTERS.length - 1) {
                return;
            }

            const filters = selectedMediaTypeFilters
                .map((typeFilter) => {
                    switch (typeFilter) {
                        case 'image':
                            return Criteria.prefix('mimeType', 'image/');
                        case 'document':
                            return Criteria.equalsAny('fileExtension', DOCUMENT_FILE_EXTENSIONS);
                        case 'video':
                            return Criteria.prefix('mimeType', 'video/');
                        case 'audio':
                            return Criteria.prefix('mimeType', 'audio/');
                        case 'spatial':
                            return Criteria.multi('OR', [
                                Criteria.prefix('mimeType', 'model/'),
                                Criteria.equalsAny('fileExtension', SPATIAL_FILE_EXTENSIONS),
                            ]);
                        default:
                            return null;
                    }
                })
                .filter(Boolean);

            if (filters.length === 0) {
                return;
            }

            if (filters.length === 1) {
                criteria.addFilter(filters[0]);
                return;
            }

            criteria.addFilter(Criteria.multi('OR', filters));
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
            this.hasUnfilteredFolderItems = null;

            this.isLoading = true;

            this.clearSelection();
            await this.fetchAssociatedFolders();

            this.pageItem = 1;
            this.pageFolder = 1;

            this.itemLoaderDone = false;
            this.folderLoaderDone = false;

            return this.loadItems();
        },

        isValidTerm(term) {
            return this.searchRankingService.isValidTerm(term);
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
            const [
                nextFolders,
                nextMedia,
            ] = await Promise.allSettled([
                this.nextFolders(),
                this.nextMedia(),
            ]);

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

            if (this.hasActiveTypeFilter && this.selectableItems.length === 0) {
                await this.updateHasUnfilteredFolderItems();
            }

            this.isLoading = false;
            this.$nextTick(() => {
                this.updateScrollPosition();
            });
        },

        async updateHasUnfilteredFolderItems() {
            const [
                folders,
                media,
            ] = await Promise.allSettled([
                this.hasUnfilteredFolders(),
                this.hasUnfilteredMedia(),
            ]);

            this.hasUnfilteredFolderItems = [
                folders,
                media,
            ].some((result) => {
                return result.status === 'rejected' || result.value === true;
            });
        },

        async hasUnfilteredFolders() {
            const criteria = new Criteria(1, 1);

            if (!this.term) {
                criteria.addFilter(Criteria.equals('parentId', this.folderId));
            }

            const folders = await this.mediaFolderRepository.search(criteria, Context.api);

            return folders.length > 0;
        },

        async hasUnfilteredMedia() {
            const criteria = new Criteria(1, 1);

            if (!this.isValidTerm(this.term)) {
                criteria.addFilter(Criteria.equals('mediaFolderId', this.folderId));
            }

            if (this.folderId != null && this.isValidTerm(this.term)) {
                criteria.addFilter(
                    Criteria.multi('OR', [
                        Criteria.equals('mediaFolderId', this.folderId),
                        Criteria.contains('mediaFolder.path', this.folderId),
                    ]),
                );
            }

            const media = await this.mediaRepository.search(criteria, Context.api);

            return media.length > 0;
        },

        async nextMedia() {
            if (this.itemLoaderDone) {
                return [];
            }

            if (!this.shouldLoadMedia) {
                this.itemLoaderDone = true;
                return [];
            }

            let criteria = this.nextMediaCriteria;

            if (this.adminEsEnable) {
                criteria.setTerm(this.term);
            } else if (this.isValidTerm(this.term)) {
                const searchRankingFields = await this.searchRankingService.getSearchFieldsByEntity('media');

                if (!searchRankingFields || Object.keys(searchRankingFields).length < 1) {
                    this.isLoading = false;
                    this.itemLoaderDone = true;

                    return [];
                }

                criteria = this.searchRankingService.buildSearchQueriesForEntity(searchRankingFields, this.term, criteria);
            }

            if (!this.isValidTerm(this.term)) {
                criteria.addFilter(Criteria.equals('mediaFolderId', this.folderId));
            }

            if (this.folderId != null && this.isValidTerm(this.term)) {
                criteria.addFilter(
                    Criteria.multi('OR', [
                        Criteria.equals('mediaFolderId', this.folderId),
                        Criteria.contains('mediaFolder.path', this.folderId),
                    ]),
                );
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

            if (!this.shouldLoadFolders) {
                this.folderLoaderDone = true;
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

        updateScrollPosition() {
            this.hasScrolledContent = (this.$refs.scrollContainer?.scrollTop ?? 0) > 0;
        },

        injectItem(item) {
            if (item.getEntityName() === 'media') {
                this.injectMedia(item);
                return;
            }

            throw new Error("Injected entity has to be of 'type media'");
        },

        injectMedia(mediaEntity) {
            if (mediaEntity.mediaFolderId !== this.folderId) {
                return;
            }

            if (
                !this.items.some((alreadyListed) => {
                    return alreadyListed.id === mediaEntity.id;
                })
            ) {
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

        setListSelectionStartItem(item) {
            this.listSelectionStartItem = item;
        },

        async refreshItem(mediaId) {
            const itemsIndex = this.items.findIndex((item) => item.id === mediaId);
            const selectedItemsIndex = this.selectedItems.findIndex((item) => item.id === mediaId);

            this.isLoading = true;

            try {
                const media = await this.mediaRepository.get(mediaId, Context.api);

                if (itemsIndex !== -1) {
                    this.items.splice(itemsIndex, 1, media);
                }

                if (selectedItemsIndex !== -1) {
                    this.selectedItems.splice(selectedItemsIndex, 1, media);
                }
            } finally {
                this.isLoading = false;
            }
        },
    },
};
