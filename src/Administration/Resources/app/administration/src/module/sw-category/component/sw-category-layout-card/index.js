import template from './sw-category-layout-card.html.twig';
import './sw-category-layout-card.scss';

const { Component } = Shopware;

Component.register('sw-category-layout-card', {
    template,

    props: {
        category: {
            type: Object,
            required: true
        },

        cmsPage: {
            type: Object,
            required: false,
            default: null
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            showLayoutSelectionModal: false
        };
    },

    computed: {
        cmsPageTypes() {
            return {
                page: this.$tc('sw-cms.detail.label.pageTypeShopPage'),
                landingpage: this.$tc('sw-cms.detail.label.pageTypeLandingpage'),
                product_list: this.$tc('sw-cms.detail.label.pageTypeCategory'),
                product_detail: this.$tc('sw-cms.detail.label.pageTypeProduct')
            };
        }
    },

    methods: {
        onLayoutSelect(selectedLayout) {
            this.category.cmsPageId = selectedLayout;
        },

        onLayoutReset() {
            this.onLayoutSelect(null);
        },

        openInPagebuilder() {
            if (!this.cmsPage) {
                this.$router.push({ name: 'sw.cms.create' });
            } else {
                this.$router.push({ name: 'sw.cms.detail', params: { id: this.category.cmsPageId } });
            }
        },

        openLayoutModal() {
            this.showLayoutSelectionModal = true;
        },

        closeLayoutModal() {
            this.showLayoutSelectionModal = false;
        }
    }
});
