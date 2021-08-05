import template from './sw-category-detail-base.html.twig';
import './sw-category-detail-base.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState, mapPropertyErrors } = Shopware.Component.getComponentHelper();
const ShopwareError = Shopware.Classes.ShopwareError;

Component.register('sw-category-detail-base', {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        'placeholder',
    ],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            // @deprecated tag:v6.5.0 - can be removed completely
            productStreamFilter: null,

            // @deprecated tag:v6.5.0 - can be removed completely
            productStreamInvalid: false,

            // @deprecated tag:v6.5.0 - can be removed completely
            manualAssignedProductsCount: 0,
        };
    },

    computed: {
        categoryTypes() {
            return [
                {
                    value: 'page',
                    label: this.$tc('sw-category.base.general.types.page'),
                },
                {
                    value: 'folder',
                    label: this.$tc('sw-category.base.general.types.folder'),
                },
                {
                    value: 'link',
                    label: this.typeLinkLabel,
                    disabled: this.isSalesChannelEntryPoint,
                },
            ];
        },

        typeLinkLabel() {
            if (this.isSalesChannelEntryPoint) {
                return this.$tc('sw-category.base.general.types.linkUnavailable');
            }

            return this.$tc('sw-category.base.general.types.link');
        },

        categoryTypeHelpText() {
            if (['page', 'folder', 'link'].includes(this.category.type)) {
                return this.$tc(`sw-category.base.general.types.helpText.${this.category.type}`);
            }

            return null;
        },

        // @deprecated tag:v6.5.0 - can be removed completely
        productAssignmentTypes() {
            return [
                {
                    value: 'product',
                    label: this.$tc('sw-category.base.products.productAssignmentTypeManualLabel'),
                },
                {
                    value: 'product_stream',
                    label: this.$tc('sw-category.base.products.productAssignmentTypeStreamLabel'),
                },
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

        // @deprecated tag:v6.5.0 - can be removed completely
        cmsPage() {
            return Shopware.State.get('cmsPageState').currentPage;
        },

        // @deprecated tag:v6.5.0 - can be removed completely
        productStreamRepository() {
            return this.repositoryFactory.create('product_stream');
        },

        // @deprecated tag:v6.5.0 - can be removed completely
        productColumns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('sw-category.base.products.columnNameLabel'),
                    dataIndex: 'name',
                    routerLink: 'sw.product.detail',
                    sortable: false,
                }, {
                    property: 'manufacturer.name',
                    label: this.$tc('sw-category.base.products.columnManufacturerLabel'),
                    routerLink: 'sw.manufacturer.detail',
                    sortable: false,
                },
            ];
        },

        // @deprecated tag:v6.5.0 - can be removed completely
        manufacturerColumn() {
            return 'column-manufacturer.name';
        },

        // @deprecated tag:v6.5.0 - can be removed completely
        nameColumn() {
            return 'column-name';
        },

        // @deprecated tag:v6.5.0 - can be removed completely
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
            },
        }),

        // @deprecated tag:v6.5.0 - can be removed completely
        productStreamInvalidError() {
            if (this.productStreamInvalid) {
                return new ShopwareError({
                    code: 'PRODUCT_STREAM_INVALID',
                    detail: this.$tc('sw-category.base.products.dynamicProductGroupInvalidMessage'),
                });
            }
            return null;
        },

        ...mapPropertyErrors('category', [
            'name',
            'type',

            // @deprecated tag:v6.5.0 - can be removed completely
            'productStreamId',
            'productAssignmentType',
        ]),

        // @deprecated tag:v6.5.0 - can be removed completely
        dynamicProductGroupHelpText() {
            const link = {
                name: 'sw.product.stream.index',
            };

            const helpText = this.$tc('sw-category.base.products.dynamicProductGroupHelpText.label', 0, {
                link: `<sw-internal-link
                           :router-link=${JSON.stringify(link)}
                           :inline="true">
                           ${this.$tc('sw-category.base.products.dynamicProductGroupHelpText.linkText')}
                       </sw-internal-link>`,
            });

            try {
                // eslint-disable-next-line no-new
                new URL(this.$tc('sw-category.base.products.dynamicProductGroupHelpText.videoUrl'));
            } catch {
                return helpText;
            }

            return `${helpText}
                    <br>
                    <sw-external-link
                        href="${this.$tc('sw-category.base.products.dynamicProductGroupHelpText.videoUrl')}">
                        ${this.$tc('sw-category.base.products.dynamicProductGroupHelpText.videoLink')}
                    </sw-external-link>`;
        },
    },

    watch: {
        // @deprecated tag:v6.5.0 - can be removed completely
        'category.productStreamId'(id) {
            if (!id) {
                this.productStreamFilter = null;
                return;
            }
            this.loadProductStreamPreview();
        },
    },

    // @deprecated tag:v6.5.0 - can be removed completely
    created() {
        this.createdComponent();
    },

    methods: {
        // @deprecated tag:v6.5.0 - can be removed completely
        createdComponent() {
            if (!this.category.productStreamId) {
                return;
            }
            this.loadProductStreamPreview();
        },

        // @deprecated tag:v6.5.0 - can be removed completely
        loadProductStreamPreview() {
            this.productStreamRepository.get(this.category.productStreamId)
                .then((response) => {
                    this.productStreamFilter = response.apiFilter;
                    this.productStreamInvalid = response.invalid;
                });
        },

        // @deprecated tag:v6.5.0 - can be removed completely
        onPaginateManualProductAssignment(assignment) {
            this.manualAssignedProductsCount = assignment.total;
        },
    },
});
