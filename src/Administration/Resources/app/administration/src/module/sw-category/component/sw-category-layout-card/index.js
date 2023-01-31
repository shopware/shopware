import template from './sw-category-layout-card.html.twig';
import './sw-category-layout-card.scss';

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'cmsPageTypeService',
    ],

    props: {
        category: {
            type: Object,
            required: true,
        },

        cmsPage: {
            type: Object,
            required: false,
            default: null,
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },

        pageTypes: {
            type: Array,
            required: false,
            default() {
                return ['page', 'landingpage', 'product_list'];
            },
        },

        headline: {
            type: String,
            required: false,
            default: '',
        },
    },

    data() {
        return {
            showLayoutSelectionModal: false,
        };
    },

    computed: {
        pageTypeTitle() {
            const fallback = this.$tc('sw-category.base.cms.defaultDesc');
            if (!this.cmsPage) {
                return fallback;
            }

            const pageType = this.cmsPageTypeService.getType(this.cmsPage.type);
            return pageType ? this.$tc(this.cmsPageTypeService.getType(this.cmsPage.type).title) : fallback;
        },
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
                this.$router.push({ name: 'sw.cms.create', params: { type: 'category', id: this.category.id } });
            } else {
                this.$router.push({ name: 'sw.cms.detail', params: { id: this.category.cmsPageId } });
            }
        },

        openLayoutModal() {
            if (!this.acl.can('category.editor')) {
                return;
            }

            this.showLayoutSelectionModal = true;
        },

        closeLayoutModal() {
            this.showLayoutSelectionModal = false;
        },
    },
};
