import template from './sw-cms-layout-modal.html.twig';
import './sw-cms-layout-modal.scss';

const { Mixin, Feature } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 * @package content
 */
export default {
    template,

    inject: ['repositoryFactory', 'systemConfigApiService', 'acl'],

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
            selected: null,
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

        pageTypes() {
            return {
                page: this.$tc('sw-cms.sorting.labelSortByShopPages'),
                landingpage: this.$tc('sw-cms.sorting.labelSortByLandingPages'),
                product_list: this.$tc('sw-cms.sorting.labelSortByCategoryPages'),
                product_detail: this.$tc('sw-cms.sorting.labelSortByProductPages'),
            };
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
                this.selected = newSelection?.id;
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

                /** @deprecated tag:v6.5.0 - Use this.pages directly */
                return this.pages;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        selectLayout() {
            this.$emit('modal-layout-select', this.selected, this.selectedPageObject);
            this.closeModal();
        },

        selectInGrid(column) {
            const columnEntries = Object.entries(column);
            if (columnEntries.length === 0) {
                [this.selected, this.selectedPageObject] = [null, null];
                return;
            }

            // replace with page.id
            [this.selected, this.selectedPageObject] = columnEntries[0];
        },

        /* @deprecated tag:v6.5.0 layoutId is redundant and should be removed as an argument */
        selectItem(layoutId, page) {
            this.selected = layoutId; // replace with page.id
            this.selectedPageObject = page;
        },

        onSearch(value) {
            if (Feature.isActive('FEATURE_NEXT_16271')) {
                if (!value.length || value.length <= 0) {
                    this.term = null;
                }
            } else if (!value.length || value.length <= 0) {
                this.term = null;
            } else {
                this.term = value;
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

        /* @deprecated tag:v6.5.0 layoutId is redundant and should be removed as an argument */
        onSelection(layoutId, page) {
            this.selected = layoutId; // replace with page.id
            this.selectedPageObject = page;
        },

        closeModal() {
            this.$emit('modal-close');
            this.selected = null;
            this.selectedPageObject = null;
            this.term = null;
        },

        getPageType(page) {
            const isDefault = [this.defaultProductId, this.defaultCategoryId].includes(page.id);
            const defaultText = this.$tc('sw-cms.components.cmsListItem.defaultLayout');
            return isDefault ? `${defaultText} - ${this.pageTypes[page.type]}` : this.pageTypes[page.type];
        },

        async getDefaultLayouts() {
            const response = await this.systemConfigApiService.getValues('core.cms');

            this.defaultCategoryId = response['core.cms.default_category_cms_page'];
            this.defaultProductId = response['core.cms.default_product_cms_page'];
        },
    },
};
