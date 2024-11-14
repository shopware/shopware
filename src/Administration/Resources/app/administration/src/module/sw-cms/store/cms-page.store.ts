type CmsPageState = {
    currentPage: null | EntitySchema.Entity<'cms_page'>;
    currentPageType: null | string;
    currentMappingEntity: null | string;
    currentMappingTypes: Record<string, unknown>;
    currentDemoEntity: unknown;
    currentDemoProducts: unknown[];
    pageEntityName: string;
    defaultMediaFolderId: null | string;
    currentCmsDeviceView: 'desktop' | 'tablet-landscape' | 'mobile' | 'form';
    selectedSection: null | EntitySchema.Entity<'cms_section'>;
    selectedBlock: null | EntitySchema.Entity<'cms_block'>;
    isSystemDefaultLanguage: boolean;
};

/**
 * @private
 * @package buyers-experience
 */
const cmsPageStore = Shopware.Store.register({
    id: 'cmsPage',

    state: (): CmsPageState => ({
        currentPage: null,
        currentPageType: null,
        currentMappingEntity: null,
        currentMappingTypes: {},
        currentDemoEntity: null,
        currentDemoProducts: [],
        pageEntityName: 'cms_page',
        defaultMediaFolderId: null,
        currentCmsDeviceView: 'desktop',
        selectedSection: null,
        selectedBlock: null,
        isSystemDefaultLanguage: true,
    }),

    actions: {
        setCurrentPage(page: EntitySchema.Entity<'cms_page'>) {
            this.currentPage = page;
        },

        removeCurrentPage() {
            this.currentPage = null;
        },

        setCurrentPageType(type: string) {
            this.currentPageType = type;
        },

        setCurrentMappingEntity(entity: string) {
            this.currentMappingEntity = entity;
        },

        removeCurrentMappingEntity() {
            this.currentMappingEntity = null;
        },

        setCurrentMappingTypes(types: Record<string, unknown>) {
            this.currentMappingTypes = types;
        },

        removeCurrentMappingTypes() {
            this.currentMappingTypes = {};
        },

        setCurrentDemoEntity(entity: unknown) {
            this.currentDemoEntity = entity;
        },

        removeCurrentDemoEntity() {
            this.currentDemoEntity = null;
        },

        setCurrentDemoProducts(products: unknown[]) {
            this.currentDemoProducts = products;
        },

        removeCurrentDemoProducts() {
            this.currentDemoProducts = [];
        },

        setPageEntityName(entity: string) {
            this.pageEntityName = entity;
        },

        removePageEntityName() {
            this.pageEntityName = 'cms_page';
        },

        setDefaultMediaFolderId(folderId: string) {
            this.defaultMediaFolderId = folderId;
        },

        removeDefaultMediaFolderId() {
            this.defaultMediaFolderId = null;
        },

        setCurrentCmsDeviceView(view: CmsPageState['currentCmsDeviceView']) {
            this.currentCmsDeviceView = view;
        },

        removeCurrentCmsDeviceView() {
            this.currentCmsDeviceView = 'desktop';
        },

        setSelectedSection(section: EntitySchema.Entity<'cms_section'>) {
            this.selectedSection = section;
        },

        removeSelectedSection() {
            this.selectedSection = null;
        },

        setSelectedBlock(block: EntitySchema.Entity<'cms_block'>) {
            this.selectedBlock = block;
        },

        removeSelectedBlock() {
            this.selectedBlock = null;
        },

        setIsSystemDefaultLanguage(isSystemDefaultLanguage: boolean) {
            this.isSystemDefaultLanguage = isSystemDefaultLanguage;
        },

        resetCmsPageState() {
            this.removeCurrentPage();
            this.removeCurrentMappingEntity();
            this.removeCurrentMappingTypes();
            this.removeCurrentDemoEntity();
            this.removeCurrentDemoProducts();
        },

        setSection(section: EntitySchema.Entity<'cms_section'>) {
            this.removeSelectedBlock();
            this.setSelectedSection(section);
        },

        setBlock(block: EntitySchema.Entity<'cms_block'>) {
            this.removeSelectedSection();
            this.setSelectedBlock(block);
        },
    },
});

/**
 * @private
 */
export type CmsPageStore = ReturnType<typeof cmsPageStore>;

/**
 * @private
 */
export default cmsPageStore;
