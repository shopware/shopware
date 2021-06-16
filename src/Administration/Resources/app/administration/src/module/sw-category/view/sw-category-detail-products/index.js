import template from './sw-category-detail-products.html.twig';
import './sw-category-detail-products.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
const ShopwareError = Shopware.Classes.ShopwareError;

Component.register('sw-category-detail-products', {
    template,

    inject: ['repositoryFactory', 'acl'],

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
            productStreamFilter: null,
            productStreamInvalid: false,
            manualAssignedProductsCount: 0,
        };
    },

    computed: {
        category() {
            return Shopware.State.get('swCategoryDetail').category;
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
                    sortable: false,
                }, {
                    property: 'manufacturer.name',
                    label: this.$tc('sw-category.base.products.columnManufacturerLabel'),
                    routerLink: 'sw.manufacturer.detail',
                    sortable: false,
                },
            ];
        },

        manufacturerColumn() {
            return 'column-manufacturer.name';
        },

        nameColumn() {
            return 'column-name';
        },

        productCriteria() {
            return (new Criteria(1, 10))
                .addAssociation('options.group')
                .addAssociation('manufacturer')
                .addFilter(Criteria.equals('parentId', null));
        },

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
            'productStreamId',
            'productAssignmentType',
        ]),

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
        'category.productStreamId'(id) {
            if (!id) {
                this.productStreamFilter = null;
                return;
            }
            this.loadProductStreamPreview();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.category.productStreamId) {
                return;
            }
            this.loadProductStreamPreview();
        },

        loadProductStreamPreview() {
            this.productStreamRepository.get(this.category.productStreamId)
                .then((response) => {
                    this.productStreamFilter = response.apiFilter;
                    this.productStreamInvalid = response.invalid;
                }).catch(() => {
                    this.productStreamFilter = null;
                    this.productStreamInvalid = true;
                });
        },

        onPaginateManualProductAssignment(assignment) {
            this.manualAssignedProductsCount = assignment.total;
        },
    },
});
