import template from './sw-category-detail-base.html.twig';
import './sw-category-detail-base.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState, mapPropertyErrors } = Shopware.Component.getComponentHelper();
const ShopwareError = Shopware.Classes.ShopwareError;

Component.register('sw-category-detail-base', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            productStreamFilter: null,
            productStreamInvalid: false,
            manualAssignedProductsCount: 0
        };
    },

    props: {
        isLoading: {
            type: Boolean,
            required: true
        }
    },

    watch: {
        'category.productStreamId'(id) {
            if (!this.next9278) {
                return;
            }

            if (!id) {
                this.productStreamFilter = null;
                return;
            }
            this.loadProductStreamPreview();
        }
    },

    created() {
        this.createdComponent();
    },

    computed: {
        categoryTypes() {
            return [
                { value: 'page', label: this.$tc('sw-category.base.general.types.page') },
                { value: 'folder', label: this.$tc('sw-category.base.general.types.folder') },
                { value: 'link', label: this.$tc('sw-category.base.general.types.link') }
            ];
        },

        productAssignmentTypes() {
            return [
                {
                    value: 'product',
                    label: this.$tc('sw-category.base.products.productAssignmentTypeManualLabel')
                },
                {
                    value: 'product_stream',
                    label: this.$tc('sw-category.base.products.productAssignmentTypeStreamLabel')
                }
            ];
        },

        isSalesChannelEntryPoint() {
            return this.category.navigationSalesChannels.length > 0
                || this.category.serviceSalesChannels.length > 0
                || this.category.footerSalesChannels.length > 0;
        },

        category() {
            return Shopware.State.get('swCategoryDetail').category;
        },

        cmsPage() {
            return Shopware.State.get('cmsPageState').currentPage;
        },

        productStreamRepository() {
            return this.repositoryFactory.create('product_stream');
        },

        productColumns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('sw-category.base.products.columnNameLabel'),
                    dataIndex: 'name',
                    routerLink: 'sw.product.detail',
                    sortable: false
                }, {
                    property: 'manufacturer.name',
                    label: this.$tc('sw-category.base.products.columnManufacturerLabel'),
                    routerLink: 'sw.manufacturer.detail',
                    sortable: false
                }
            ];
        },

        manufacturerColumn() {
            return 'column-manufacturer.name';
        },

        nameColumn() {
            return 'column-name';
        },

        productCriteria() {
            const productCriteria = new Criteria(1, 10);
            productCriteria
                .addAssociation('options.group')
                .addAssociation('manufacturer')
                .addFilter(Criteria.equals('parentId', null));
            return productCriteria;
        },

        ...mapState('swCategoryDetail', {
            customFieldSetsArray: state => {
                if (!state.customFieldSets) {
                    return [];
                }

                return state.customFieldSets;
            }
        }),

        productStreamInvalidError() {
            if (this.productStreamInvalid) {
                return new ShopwareError({
                    code: 'PRODUCT_STREAM_INVALID',
                    detail: this.$tc('sw-category.base.products.dynamicProductGroupInvalidMessage')
                });
            }
            return null;
        },

        ...mapPropertyErrors('category', [
            'name',
            'productStreamId',
            'productAssignmentType'
        ])
    },

    methods: {
        createdComponent() {
            if (!this.next9278) {
                return;
            }

            if (!this.category.productStreamId) {
                return;
            }
            this.loadProductStreamPreview();
        },

        loadProductStreamPreview() {
            this.productStreamRepository
                .get(this.category.productStreamId, Shopware.Context.api)
                .then((response) => {
                    this.productStreamFilter = response.apiFilter;
                    this.productStreamInvalid = response.invalid;
                });
        },

        onPaginateManualProductAssignment(assignment) {
            this.manualAssignedProductsCount = assignment.total;
        }
    }
});
