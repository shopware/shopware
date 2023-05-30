import template from './sw-cms-layout-modal.html.twig';
import './sw-cms-layout-modal.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 * @package content
 */
export default {
    template,

    inject: [
        'repositoryFactory',
        'systemConfigApiService',
        'acl',
        'cmsPageTypeService',
    ],

    mixins: [
        Mixin.getByName('listing'),
    ],

    props: {
        headline: {
            type: String,
            required: false,
            default: '',
        },

        cmsPageTypes: {
            type: Array,
            required: false,
            default() {
                return [];
            },
        },

        preSelection: {
            type: Object,
            required: false,
            default: () => {},
        },
    },

    data() {
        return {
            listMode: 'grid',
            disableRouteParams: true,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            limit: 10,
            selectedPageObject: null,
            isLoading: false,
            term: null,
            total: null,
            pages: [],
            defaultCategoryId: '',
            defaultProductId: '',
        };
    },

    computed: {
        pageRepository() {
            return this.repositoryFactory.create('cms_page');
        },

        cmsPageCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria
                .addAssociation('previewMedia')
                .addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            if (this.cmsPageTypes.length) {
                criteria.addFilter(Criteria.equalsAny('type', this.cmsPageTypes));
            }

            if (this.term !== null) {
                criteria.setTerm(this.term);
            }

            return criteria;
        },

        columnConfig() {
            return [{
                property: 'name',
                label: this.$tc('sw-cms.list.gridHeaderName'),
                inlineEdit: 'string',
                primary: true,
            }, {
                property: 'type',
                label: this.$tc('sw-cms.list.gridHeaderType'),
            }, {
                property: 'createdAt',
                label: this.$tc('sw-cms.list.gridHeaderCreated'),
            }, {
                property: 'updatedAt',
                label: this.$tc('sw-cms.list.gridHeaderUpdated'),
            }];
        },

        gridPreSelection() {
            if (!this.selectedPageObject?.id) {
                return {};
            }

            return { [this.selectedPageObject.id]: this.selectedPageObject };
        },
    },

    watch: {
        preSelection: {
            handler: function handler(newSelection) {
                this.selectedPageObject = newSelection;
            },
            immediate: true,
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.acl.can('system_config.read')) {
                this.getDefaultLayouts();
            }
        },

        getList() {
            this.isLoading = true;

            return this.pageRepository.search(this.cmsPageCriteria).then((searchResult) => {
                this.total = searchResult.total;
                this.pages = searchResult;
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        selectLayout() {
            this.$emit('modal-layout-select', this.selectedPageObject?.id, this.selectedPageObject);
            this.closeModal();
        },

        selectInGrid(column) {
            const columnEntries = Object.values(column);
            if (columnEntries.length === 0) {
                this.selectedPageObject = null;
                return;
            }

            this.selectedPageObject = columnEntries[0];
        },

        selectItem(page) {
            this.selectedPageObject = page;
        },

        onSearch(value) {
            if (!value.length || value.length <= 0) {
                this.term = null;
            }

            this.page = 1;
            this.getList();
        },

        toggleListMode() {
            this.listMode = this.listMode === 'grid' ? 'list' : 'grid';
        },

        gridItemClasses(pageId, index) {
            return [
                {
                    'is--selected': pageId === this.selectedPageObject?.id,
                },
                'sw-cms-layout-modal__content-item',
                `sw-cms-layout-modal__content-item--${index}`,
            ];
        },

        closeModal() {
            this.selectedPageObject = null;
            this.term = null;
            this.$emit('modal-close');
        },

        getPageType(page) {
            const isDefault = [this.defaultProductId, this.defaultCategoryId].includes(page.id);
            const defaultText = this.$tc('sw-cms.components.cmsListItem.defaultLayout');
            const typeLabel = this.$tc(this.cmsPageTypeService.getType(page.type)?.title);
            return isDefault ? `${defaultText} - ${typeLabel}` : typeLabel;
        },

        async getDefaultLayouts() {
            const response = await this.systemConfigApiService.getValues('core.cms');

            this.defaultCategoryId = response['core.cms.default_category_cms_page'];
            this.defaultProductId = response['core.cms.default_product_cms_page'];
        },
    },
};
