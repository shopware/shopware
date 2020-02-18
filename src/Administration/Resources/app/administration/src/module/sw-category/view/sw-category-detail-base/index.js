import template from './sw-category-detail-base.html.twig';
import './sw-category-detail-base.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState, mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-category-detail-base', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('placeholder')
    ],

    props: {
        isLoading: {
            type: Boolean,
            required: true
        }
    },

    computed: {
        categoryTypes() {
            return [
                { value: 'page', label: this.$tc('sw-category.base.general.types.page') },
                { value: 'folder', label: this.$tc('sw-category.base.general.types.folder') },
                { value: 'link', label: this.$tc('sw-category.base.general.types.link') }
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

        productColumns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('sw-category.base.products.columnNameLabel'),
                    dataIndex: 'name',
                    routerLink: 'sw.product.detail'
                }, {
                    property: 'manufacturer.name',
                    label: this.$tc('sw-category.base.products.columnManufacturerLabel'),
                    routerLink: 'sw.manufacturer.detail'
                }
            ];
        },

        manufacturerColumn() {
            return 'column-manufacturer.name';
        },

        productCriteria() {
            const productCriteria = new Criteria(1, 10);
            productCriteria.addAssociation('manufacturer')
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

        ...mapPropertyErrors('category', ['name'])
    }
});
